# Banca Digital BP

Diseño de arquitectura (modelo C4) e implementación progresiva del sistema de banca por internet de la entidad **BP**: consulta de histórico de movimientos, transferencias y pagos entre cuentas propias e interbancarias, con onboarding móvil biométrico y autenticación OAuth 2.0.

El desarrollo avanza por fases siguiendo [`CHECKLIST.md`](CHECKLIST.md); el detalle de qué se hizo y cómo se verificó en cada fase queda en [`WORKLOG.md`](WORKLOG.md); las reglas de trabajo (incluida la obligación de mantener esta sección de instalación al día) están en [`CLAUDE.md`](CLAUDE.md).

## Contenido del repositorio

```
.
├── README.md
├── CHECKLIST.md            # Plan de desarrollo por fases
├── WORKLOG.md              # Bitácora de lo hecho y cómo se verificó
├── CLAUDE.md               # Reglas de trabajo para el desarrollo asistido
├── .gitignore
├── .env.example            # Variables del entorno local (docker-compose)
├── docker-compose.yml      # Infraestructura local: MySQL, Redis, LocalStack, mock-oidc
├── Makefile                # make up / down / logs / ps / restart
├── docs/
│   ├── arquitectura-banca-digital-bp.md   # Documento de arquitectura completo
│   └── arquitectura-banca-digital-bp.pdf  # Mismo documento en PDF
├── diagrams/                # Fuente .mmd de cada diagrama C4 + diagrams/png/ ya renderizado
├── packages/
│   └── bp-common/          # Paquete compartido: JWT/DPoP, correlation-id, envelope, healthcheck
├── services/                # Un microservicio Laravel por carpeta (Fase 2 en adelante)
├── frontend-web/            # SPA React + TypeScript (Fase 9)
├── frontend-mobile/         # App React Native + TypeScript (Fase 10)
└── infra/                   # Infraestructura como código para AWS (Fase 13)
```

## Requisitos previos

Para levantar el entorno de desarrollo en una máquina limpia (local o un servidor) se necesita:

| Herramienta | Versión mínima | Para qué |
|---|---|---|
| [Docker](https://docs.docker.com/get-docker/) + Docker Compose v2 | Docker 24+ | Infraestructura local: MySQL, Redis, LocalStack, mock-oidc, y luego cada microservicio |
| [PHP](https://www.php.net/) | 8.3+ | Correr/testear el paquete compartido y los microservicios Laravel fuera de sus contenedores |
| [Composer](https://getcomposer.org/) | 2.x | Gestión de dependencias PHP |
| [Node.js](https://nodejs.org/) | 20+ | Solo si se regeneran los diagramas (`npx @mermaid-js/mermaid-cli`) o el PDF del documento de arquitectura |

> En Apple Silicon: si alguna imagen de Docker de terceros falla en bucle con errores que huelen a incompatibilidad nativa, ver la nota correspondiente en `CLAUDE.md` (`platform: linux/amd64`).

## Puesta en marcha (entorno limpio)

```bash
# 1. Clonar el repositorio
git clone git@github.com:pdfloresjdav/test-devsu.git
cd test-devsu

# 2. Copiar las variables de entorno de ejemplo
cp .env.example .env

# 3. Levantar la infraestructura local (MySQL, Redis, LocalStack, mock-oidc)
make up

# 4. Verificar que los 4 servicios quedaron sanos
make ps
```

Salida esperada de `make ps` (los 4 en estado `healthy`):

```
NAME            IMAGE                                    STATUS
bp-localstack   localstack/localstack:3                  Up (healthy)
bp-mock-oidc    ghcr.io/soluto/oidc-server-mock:latest    Up (healthy)
bp-mysql        mysql:8.0                                Up (healthy)
bp-redis        redis:7-alpine                           Up (healthy)
```

Chequeos manuales rápidos de cada servicio:

```bash
docker exec bp-mysql mysqladmin ping -h localhost -uroot -proot
docker exec bp-redis redis-cli ping
curl -s http://localhost:4566/_localstack/health   # dynamodb/sqs/events/s3 deben estar "available"
curl -s http://localhost:4011/.well-known/openid-configuration   # discovery OIDC del mock-oidc
```

Para bajar el entorno: `make down`.

### Paquete compartido `packages/bp-common`

No se instala por separado: cada microservicio lo requiere vía path-repository en su propio `composer.json` (`"bp/common": "@dev"` apuntando a `../../packages/bp-common`). Para correr sus tests de forma aislada:

```bash
cd packages/bp-common
composer install
./vendor/bin/phpunit
```

### Microservicios (`services/*`)

#### `services/svc-datos-basicos` (Fase 2)

Compone datos del Core Bancario y del Sistema Complementario de Cliente (patrón API Composition). Mientras esos sistemas no existan, usa clientes fake con datos fijos (clientes de prueba `1001` y `1002`).

```bash
cd services/svc-datos-basicos
cp .env.example .env   # ya viene con OAUTH_MODE=local apuntando al mock-oidc
composer install
php artisan serve --port=8001
```

Endpoints:
- `GET /health` — healthcheck (automático, provisto por `bp-common`).
- `GET /clientes/{id}` — requiere `Authorization: Bearer <JWT>` válido contra el emisor configurado (mock-oidc en local, Auth0 en producción). Prueba con `1001` o `1002`.

Correr sus tests: `php artisan test` (10 tests: fakes, composición y el endpoint completo, incluyendo el rechazo sin token).

Para apuntar a los sistemas reales cuando existan: `CORE_BANCARIO_DRIVER=http` + `CORE_BANCARIO_BASE_URL=...` y/o `CLIENTE_COMPLEMENTARIO_DRIVER=http` + `CLIENTE_COMPLEMENTARIO_BASE_URL=...` en su `.env` — no requiere cambiar código.

Construir su imagen (Octane + Swoole, igual que en producción) desde la **raíz del repo** (necesita `packages/bp-common` en el contexto):

```bash
docker build -f services/svc-datos-basicos/Dockerfile -t bp/svc-datos-basicos .
```

*(El resto de los servicios se agrega aquí a medida que se construyen — Fase 3 en adelante.)*

### Frontends (`frontend-web/`, `frontend-mobile/`)

*(Se agrega en las Fases 9 y 10 del checklist.)*

## Stack propuesto

- **Frontend:** React + TypeScript (SPA) y React Native + TypeScript (app móvil).
- **Backend:** Laravel (PHP) sobre Amazon ECS Fargate, con Laravel Octane (Swoole) para baja latencia y Laravel Horizon para procesamiento asíncrono.
- **Identidad:** Auth0 / Okta CIC (OAuth 2.0 + OIDC, flujo Authorization Code + PKCE) en producción; `oidc-server-mock` como stand-in local.
- **Biometría/KYC:** Onfido / iProov para onboarding, AWS Rekognition para revalidaciones ligeras, WebAuthn/FIDO2 para login recurrente.
- **Datos:** Amazon Aurora MySQL (transaccional), Amazon DynamoDB (movimientos y auditoría), Amazon ElastiCache Redis (caché). En local: MySQL real, y DynamoDB/SQS/EventBridge/S3 vía LocalStack.
- **Mensajería:** Amazon EventBridge + SQS.
- **Notificaciones:** Amazon Pinpoint (push/SMS) + Amazon SES (email).

## El documento de arquitectura

El documento completo (decisiones justificadas, diagramas C4 hasta nivel de componentes, diagrama de despliegue y diagramas dinámicos) está en [`docs/arquitectura-banca-digital-bp.md`](docs/arquitectura-banca-digital-bp.md), con las imágenes ya embebidas, y su versión exportada en [`docs/arquitectura-banca-digital-bp.pdf`](docs/arquitectura-banca-digital-bp.pdf). Los `.mmd` en `diagrams/` son la fuente editable de cada diagrama; `diagrams/png/` es el resultado ya renderizado que usan el documento y el PDF.

Para regenerar el PDF tras editar un diagrama: renderizar el `.mmd` correspondiente a PNG con `@mermaid-js/mermaid-cli` (`npx @mermaid-js/mermaid-cli -i diagrams/X.mmd -o diagrams/png/X.png -b white -s 2`) y luego reconvertir el `.md` a PDF (Chrome headless a partir de un HTML intermedio, o cualquier herramienta equivalente como Pandoc + `mermaid-filter`).

## Estado

- Documento de arquitectura v1.1 — completo.
- Desarrollo: Fase 0 (entorno local), Fase 1 (`packages/bp-common`) y Fase 2 (`services/svc-datos-basicos`) completas. Ver `CHECKLIST.md` para el resto de fases pendientes.
