# Infraestructura como código (Fase 14)

Terraform que materializa la arquitectura descrita en
[`docs/arquitectura-banca-digital-bp.md`](../../docs/arquitectura-banca-digital-bp.md).
Cada módulo mapea 1:1 a una decisión de la sección 3 (o a una consideración
transversal de la sección 9) de ese documento — es la fuente de verdad de
*por qué* existe cada recurso; este README solo explica *cómo* operar el
código.

## Estructura

```
infra/terraform/
├── main.tf              # wiring de todos los módulos
├── providers.tf          # providers aws.primary/aws.secondary/aws.us_east_1/auth0
├── variables.tf           # variables del root module
├── outputs.tf             # outputs del root module
├── versions.tf             # constraints de Terraform y providers
├── environments/
│   ├── dev.tfvars
│   └── prod.tfvars        # sin secretos -- ver "Secretos" abajo
└── modules/
    ├── network/           # VPC, subredes, NAT, security groups (14.2)
    ├── compute/           # ECS Fargate, ECR, Auto Scaling, API Gateway (14.2)
    ├── data/               # Aurora Global DB, DynamoDB Global Tables, Redis, S3 Object Lock (14.3)
    ├── messaging/          # EventBridge, SQS + DLQ, Pinpoint, SES (14.4)
    ├── security/           # KMS, Secrets Manager, IAM Task Roles, mTLS (App Mesh), OIDC de GitHub Actions (14.5)
    ├── edge/               # WAF + CloudFront + Shield Advanced (14.5, separado de security -- ver más abajo)
    ├── ha-dr/              # Route 53 failover primario/secundario (14.6)
    ├── observability/      # CloudWatch, X-Ray, Synthetics, GuardDuty, Security Hub (14.7)
    └── identity/           # Auth0 real (Authorization Server) (14.8)
```

`.github/workflows/deploy.yml` (14.9) es el pipeline que construye y
despliega las imágenes de los 7 servicios usando el rol de IAM que expone
`module.security.github_actions_deploy_role_arn` (federación OIDC, sin
credenciales de larga duración en GitHub).

### Por qué `edge` es un módulo aparte de `security`

El WAF/CloudFront necesitan el endpoint de API Gateway (`module.compute`),
y `compute` necesita los IAM Task Roles (`module.security`) para las task
definitions. Si CloudFront/WAF vivieran dentro de `security`, se formaría
un ciclo `security → compute → security`, que Terraform no permite entre
módulos. Se extrajo `edge` (que depende de `compute`, no al revés) para
romper el ciclo sin inventar un dato falso.

### Por qué `security` no recibe outputs de `data`/`messaging`

Los IAM Task Roles necesitan ARNs de tablas DynamoDB, colas SQS y el bus de
EventBridge que viven en `data`/`messaging`. Pasarlos como outputs habría
creado el mismo tipo de ciclo (`data`/`messaging` no necesitan nada de
`security`, pero `compute` sí necesita los roles de `security`, y `data`
necesita las security groups de `network`, etc. — el grafo se vuelve
circular en cuanto `security` intenta "mirar hacia adelante"). En cambio,
`modules/security/iam_task_roles.tf` construye esos ARNs por convención de
nombre (`data.aws_caller_identity`/`data.aws_region` + los mismos patrones
de nombre que usan los recursos reales en `data`/`messaging`). Es un
acoplamiento implícito documentado explícitamente en ese archivo — si se
renombra una tabla/cola en `data`/`messaging`, hay que actualizar el
patrón en `security` a mano.

## Requisitos previos

- Terraform >= 1.7.0
- Credenciales de AWS con permisos suficientes (no disponibles en este
  entorno de desarrollo — ver "Qué se verificó" abajo)
- Para el módulo `identity`: un tenant de Auth0 real con una aplicación
  Machine-to-Machine con scope de Management API (opcional — ver más abajo)

## Backend de estado

El bloque `backend "s3"` en `versions.tf` está comentado a propósito: el
bucket S3 + tabla DynamoDB de locking para el *remote state* no existen
todavía y no pueden crearse con el propio Terraform que los va a usar
(problema de bootstrap del huevo y la gallina). Antes del primer `apply`
real contra una cuenta de AWS:

1. Crear a mano (o con un Terraform aparte, de un solo uso) un bucket S3
   con versionado + una tabla DynamoDB para locking.
2. Descomentar el bloque `backend "s3"` en `versions.tf` y completarlo con
   esos nombres.
3. Correr `terraform init -migrate-state`.

Hasta entonces, el estado es local (`terraform.tfstate`, en `.gitignore`).

## Uso

```bash
cd infra/terraform
terraform init
terraform fmt -recursive   # normaliza el HCL antes de cualquier commit
terraform validate

# Plan por ambiente (requiere credenciales reales de AWS, no disponibles aca)
terraform plan -var-file=environments/dev.tfvars
terraform plan -var-file=environments/prod.tfvars
```

### Secretos

`prod.tfvars` (commiteado) nunca contiene secretos. Todo lo sensible
(`auth0_management_client_secret`, `third_party_api_keys`, etc.) se inyecta
por variables de entorno `TF_VAR_<nombre>` en el pipeline de CI/CD, nunca
en un archivo `.tfvars` versionado.

### Módulo `identity` (Auth0) es opcional

`module "identity"` tiene `count = var.auth0_domain != "" ? 1 : 0`: si no
se proveen credenciales de gestión de Auth0 (por ejemplo, en un ambiente
que todavía no tiene un tenant real), ese módulo simplemente no se
instancia, y `module.compute` cae a un `auth0_issuer_url` de placeholder
(documentado en `main.tf`) en vez de fallar el `plan`.

## Qué se verificó (y qué no) en este entorno

Este entorno de desarrollo no tiene credenciales reales de AWS ni de Auth0,
así que la verificación se limitó a lo que no requiere una cuenta real:

- `terraform fmt -recursive` — todo el árbol normalizado.
- `terraform init` — los 4 providers (`aws`, `auth0`, `random`, `tls`) se
  descargan e instalan sin error; los 10 módulos se inicializan.
- `terraform validate` — la configuración es sintácticamente válida y el
  grafo de dependencias entre los 10 módulos resuelve sin ciclos.
- `terraform plan -var-file=environments/dev.tfvars` — se ejecutó a
  propósito para confirmar que el grafo completo (interpolaciones entre
  módulos, `for_each`, `count` condicionales) resuelve de punta a punta;
  llegó a planear el primer recurso real (`random_password` en `data`,
  que no depende de AWS) y falló recién al intentar autenticar contra AWS
  (`No valid credential sources found`), que es el punto exacto donde se
  esperaba que fallara sin credenciales.

**No se corrió `terraform apply`** contra ninguna cuenta real — no hay una
cuenta de AWS ni un tenant de Auth0 disponibles en este ejercicio. Antes de
un `apply` real hay que además:

- Completar el bootstrap del backend S3 (ver arriba).
- Confirmar cuotas de servicio (Elastic IPs para NAT Gateway por AZ,
  límites de VPC) en la cuenta destino.
- Revisar el comentario en `modules/security/mtls.tf` sobre la validación
  de confianza del lado *listener* de App Mesh (STRICT mTLS): usa una
  fuente `file` con una ruta convencional (`/certs/internal-ca.pem`)
  porque App Mesh no admite `acm` como fuente de confianza para validar al
  cliente en el listener (sólo lo admite del lado `client_policy`, para
  validar al servidor) — falta agregar a `modules/compute` el paso que
  efectivamente escribe ese certificado dentro de cada tarea antes de que
  el mTLS de extremo a extremo funcione en la práctica.
- Activar manualmente la suscripción a Shield Advanced en la cuenta (costo
  fijo mensual, no se puede crear vía Terraform — ver comentario en
  `modules/edge/main.tf`).
