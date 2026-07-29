# Checklist de Desarrollo — Banca Digital BP

> **Reglas de uso de este documento (obligatorias):**
> 1. Se trabaja **un ítem a la vez**, en el orden en que aparece, respetando el orden de las fases.
> 2. Un ítem solo se marca `[x]` cuando está **implementado Y verificado** (ver "Criterio de aceptación" de cada fase) — nunca por estar "casi listo".
> 3. No se avanza al siguiente ítem sin haber cerrado el anterior. Si aparece trabajo nuevo no previsto, se agrega como ítem nuevo en la fase correspondiente; no se hace "por el camino" sin registrarlo aquí.
> 4. Cada vez que se cierra un ítem (o un bloqueo impide cerrarlo), se agrega una entrada en [`WORKLOG.md`](WORKLOG.md) antes de seguir.
> 5. Si una fase completa queda bloqueada por una decisión del usuario, se detiene el trabajo y se pregunta — no se asume.

**Leyenda de estado:** `[ ]` pendiente · `[~]` en progreso · `[x]` hecho y verificado · `[!]` bloqueado (ver WORKLOG)

---

## Decisiones base de esta etapa (ya confirmadas)

- Backend: **microservicios Laravel separados desde el inicio** (1 app por servicio, tal como en el diagrama de contenedores).
- Persistencia relacional: **MySQL real local** (Docker) desde ya — es el mismo protocolo que Aurora MySQL, así que pasar a producción es solo cambiar host/credenciales en `.env`.
- Todo lo demás (DynamoDB, EventBridge/SQS, S3, Auth0/OIDC, KYC, Rekognition, red interbancaria, Pinpoint/SES) se implementa **contra una interfaz (Repository/Adapter)** con un driver **local** (LocalStack, mocks, fakes) y un driver **real** (AWS SDK / proveedor real), seleccionable por variables en `.env`. Nunca se hardcodea el proveedor dentro de la lógica de negocio.
- Cada servicio trae su propio `.env.example` versionado; el `.env` real siempre va en `.gitignore`.
- **Amazon API Gateway** (borde de la arquitectura en AWS) no se emula en local con una herramienta aparte: su función de autorización JWT la cubre el middleware compartido de `bp-common` (Fase 1) en cada servicio, y su función de enrutamiento/throttling se resuelve llamando directo al puerto de cada servicio en `docker-compose.yml`. El API Gateway real como recurso de AWS se modela en la Fase 13 (IaC).

---

## Fase 0 — Fundación del monorepo y entorno local

**Criterio de aceptación de la fase:** `docker compose up` levanta todos los contenedores de infraestructura sin error y cada uno responde a un chequeo básico (ping/health).

- [x] 0.1 Crear estructura de carpetas: `services/`, `packages/`, `frontend-web/`, `frontend-mobile/`, `infra/`
- [x] 0.2 `docker-compose.yml` raíz con: MySQL 8, Redis, LocalStack (DynamoDB, SQS, EventBridge, S3), servicio mock-oidc (emisor JWT local)
- [x] 0.3 `.env.example` raíz documentando variables compartidas (puertos, credenciales de desarrollo, endpoints de LocalStack)
- [x] 0.4 `Makefile` (o script equivalente) con `make up`, `make down`, `make logs`
- [x] 0.5 Verificación manual: todos los contenedores arriba y responden

---

## Fase 1 — Paquete compartido `packages/bp-common`

**Criterio de aceptación:** el paquete tiene tests unitarios en verde y puede instalarse como path-repository desde un servicio de prueba.

- [x] 1.1 Scaffold del paquete Composer (`packages/bp-common`)
- [x] 1.2 Middleware de validación JWT vía JWKS remoto, con el emisor configurable por `.env` (`OIDC_ISSUER`/`OAUTH_MODE=local` apunta al mock-oidc, que expone un JWKS real; `OAUTH_MODE=auth0` apunta al tenant real) — un solo flujo de validación para ambos modos, sin lógica de clave local separada
- [x] 1.3 Soporte de verificación DPoP (Proof-of-Possession) en el middleware de auth, activable por `.env` (`DPOP_ENFORCED=true|false`) — decisión 3.6 del documento de arquitectura; puede quedar deshabilitado por defecto en local si el mock-oidc no emite DPoP, pero la validación debe existir y tener test
- [x] 1.4 Middleware de Correlation-Id para trazabilidad entre servicios
- [x] 1.5 Formato estándar de respuesta y de error de la API (envelope común)
- [x] 1.6 Trait/endpoint de healthcheck reutilizable (`GET /health`)
- [x] 1.7 Tests unitarios del paquete
- [x] 1.8 Confirmar que un servicio puede requerirlo vía path-repository en `composer.json`
- [x] 1.9 *(agregado durante la Fase 3, por ser código genuinamente compartido)* `Events\EventPublisherInterface` + `EventBridgeEventPublisher`, y clientes compartidos `Aws\DynamoDb\DynamoDbClient` / `Aws\EventBridge\EventBridgeClient` configurados por `.env` (mismo patrón local/AWS real que el resto del paquete) — evita que cada servicio productor de eventos (Movimientos, Transferencias) o consumidor de DynamoDB (Auditoría) reimplemente esta configuración
- [x] 1.10 *(agregado durante la Fase 7)* `Auth\JwtClaims::bearerToken(Request)` — extrae el JWT crudo del header `Authorization` para que un BFF lo reenvíe tal cual a un microservicio interno
- [x] 1.11 *(agregado durante la Fase 8, por ser código genuinamente compartido)* `Clients\{DatosBasicosClient,MovimientosClient,TransferenciasClient}` + implementaciones HTTP + `HttpUpstreamClient` (base con manejo de errores) + `UpstreamServiceException`, y el trait `Http\HandlesUpstreamErrors` — movidos desde BFF Web al detectarse que BFF Móvil necesitaba exactamente lo mismo

---

## Fase 2 — Servicio Datos Básicos (`services/svc-datos-basicos`)

**Criterio de aceptación:** expone un endpoint que compone datos de dos fuentes simuladas (Core + complementario) y sus tests pasan.

- [x] 2.1 Scaffold Laravel + Octane + Dockerfile
- [x] 2.2 Cliente fake del Core Bancario (fixture/HTTP fake) tras interfaz `CoreBancarioClient`
- [x] 2.3 Cliente fake del Sistema Complementario tras interfaz `ClienteComplementarioClient`
- [x] 2.4 Endpoint de composición `GET /clientes/{id}` (patrón API Composition)
- [x] 2.5 Aplicar middleware de `bp-common` (auth, correlation-id, healthcheck)
- [x] 2.6 Tests (unitarios + endpoint)
- [x] 2.7 `.env.example` documentando cómo apuntar los clientes fake a los sistemas reales el día que existan

---

## Fase 3 — Servicio Movimientos (`services/svc-movimientos`)

**Criterio de aceptación:** el patrón Cache-Aside se puede demostrar con un test (primera lectura pega a la "base", segunda lectura pega a caché) y los tests pasan.

- [x] 3.1 Scaffold Laravel + Octane + Dockerfile
- [x] 3.2 Repository de movimientos sobre DynamoDB (`DynamoDbClient` compartido de `bp-common`, configurado por `.env`: endpoint de LocalStack en local, endpoint real de AWS en producción — mismo código, mismo patrón que la validación JWT vía JWKS), tras interfaz `MovimientosRepository`
- [x] 3.3 Cache-Aside con Redis (ElastiCache-compatible) para últimos movimientos, con invalidación activa de la clave al registrarse un nuevo movimiento (decisión 3.8, no solo TTL)
- [x] 3.4 Endpoints `GET /cuentas/{id}/movimientos` (consulta) y `POST /cuentas/{id}/movimientos` (registro) — el POST se agrega además de lo previsto originalmente porque sin un punto de escritura propio del servicio no hay forma de ejercer ni probar 3.3 (invalidación de caché) ni 3.5 (publicación del evento); en el diagrama de contenedores el propio Servicio Movimientos "lee/escribe" en DynamoDB, no solo lee
- [x] 3.5 Publicación del evento `MovementRegistered` al bus al registrar un movimiento (usa `EventPublisherInterface` de `bp-common`, ya implementado sobre EventBridge/SQS vía LocalStack en local)
- [x] 3.6 Tests (incluye test explícito del comportamiento Cache-Aside)
- [x] 3.7 `.env.example`

---

## Fase 4 — Servicio Transferencias (`services/svc-transferencias`)

**Criterio de aceptación:** tests cubren el camino feliz, el rechazo por idempotencia repetida, y la compensación ante fallo del banco destino (circuito abierto o error).

- [x] 4.1 Scaffold Laravel + Octane + Dockerfile
- [x] 4.2 Modelo/migraciones MySQL: `cuentas` (saldo) y `transferencias`. *Simplificación respecto al plan original:* no se creó una tabla `idempotency_keys` separada — la idempotencia la resuelve Redis (ítem 4.3, igual que documenta el diagrama de componentes 3b) y, como red de seguridad a nivel de base de datos, `transferencias.idempotency_key` es `unique`, así que un duplicado que por algún motivo saltara la capa de Redis igual rebotaría en la base de datos en vez de crear un registro duplicado
- [x] 4.3 Middleware de Idempotency-Key (Redis) — exige el header `Idempotency-Key` en el request
- [x] 4.4 Verificación de autenticación reforzada (step-up) para transferencias sobre un umbral configurable (`.env`): valida `acr`/`amr` del JWT y rechaza con un código (`step_up_required`) que le indique al cliente que debe reautenticar (decisión 3.6). Corre *antes* del middleware de idempotencia a propósito, para que un rechazo por falta de step-up nunca quede cacheado bajo la misma Idempotency-Key
- [x] 4.5 Orquestador Saga (débito → llamada externa → commit/compensación)
- [x] 4.6 Cliente interbancario fake tras interfaz `InterbankClient`, envuelto en Circuit Breaker (retry con backoff exponencial + jitter, apertura de circuito tras un umbral de fallas, estado en Redis para ser consistente entre workers/instancias)
- [x] 4.7 Publicación de `TransferCompleted` / `TransferFailed` al bus (vía `EventPublisherInterface` de `bp-common`)
- [x] 4.8 Endpoint `POST /transfers`
- [x] 4.9 Tests (camino feliz, idempotencia, compensación, circuito abierto, rechazo y aceptación por step-up, saldo insuficiente)
- [x] 4.10 `.env.example`

---

## Fase 5 — Servicio Auditoría (`services/svc-auditoria`)

**Criterio de aceptación:** un evento publicado por Transferencias o Movimientos aparece persistido en el store de auditoría local.

- [x] 5.1 Scaffold Laravel + Dockerfile. *Ajuste respecto al plan original:* no se instaló Laravel Octane ni se usa Laravel Horizon en sentido estricto — este servicio no sirve HTTP (no hay nada que Octane acelere) y su fuente de trabajo es una cola SQS con el sobre de EventBridge, no la cola interna de Laravel que Horizon supervisa. El "worker" es un comando Artisan de larga duración (`audit:consume`) que hace long-polling directo contra el SDK de AWS; es el equivalente funcional al "Laravel Horizon Worker" del diagrama de contenedores, corriendo como proceso principal del contenedor
- [x] 5.2 Consumidor de cola (SQS local vía LocalStack) para eventos de dominio (`App\Console\Commands\ConsumeAuditEvents`, con `--once` para tests/depuración)
- [x] 5.3 Repository de auditoría sobre DynamoDB (`DynamoDbClient` compartido de `bp-common`, mismo patrón local/AWS real que el resto del proyecto), tras interfaz `AuditRepository`
- [x] 5.4 Registro de auditoría con actor, acción, timestamp y hash del evento (`DynamoDbAuditRepository`)
- [x] 5.5 WORM Archiver: en local, copia el registro a un bucket S3 de LocalStack como stand-in (`App\Services\WormArchiver`); documentado explícitamente en el código que la inmutabilidad real (`Object Lock` modo Compliance) solo aplica en AWS (Fase 13)
- [x] 5.6 Tests (consumo de evento de prueba → registro persistido → archivo en el bucket stand-in — incluye un test end-to-end real: publica en EventBridge, corre `audit:consume --once`, verifica el registro en DynamoDB)
- [x] 5.7 `.env.example`
- [x] 5.8 *(agregado durante esta fase)* `App\Console\Commands\SetupAuditInfrastructure`: provisión idempotente en local de la cola SQS + su DLQ (con redrive policy), la regla de EventBridge que rutea el bus hacia esa cola, la tabla DynamoDB y el bucket S3
- [x] 5.9 *(agregado durante esta fase, retroactivo en `bp-common`)* `Aws\Sqs\SqsClient` como singleton compartido en `bp-common` (`bp-common.sqs.*`), reutilizable también por Notificaciones en la Fase 6

---

## Fase 6 — Servicio Notificaciones (`services/svc-notificaciones`)

**Criterio de aceptación:** un evento de prueba dispara un log de notificación con el canal y contenido correctos; el cambio a AWS real es solo de configuración.

- [x] 6.1 Scaffold Laravel + Dockerfile. Mismo ajuste que Auditoría (Fase 5): sin Octane ni Horizon en sentido estricto — worker puro con `notifications:consume` (long-polling directo a SQS) como proceso principal
- [x] 6.2 Consumidor de cola para eventos de dominio — cola y regla de EventBridge **propias** (`notification-events-queue`, distinta de la de Auditoría), patrón Pub/Sub con Competing Consumers: cada consumidor recibe su copia de cada evento del mismo bus
- [x] 6.3 `ChannelRouter` que decide push/SMS/email según tipo de evento (mapa configurable en `.env`/`config/services.php`: eventos críticos van por push + email, informativos solo por push)
- [x] 6.4 `TemplateEngine` que genera el contenido de la notificación según el tipo de evento (Blade), con plantilla genérica de respaldo. *Limitación conocida:* no soporta idioma del cliente todavía (fijo en español) porque los eventos de Movimientos/Transferencias no traen ese dato — se documenta en el `.env.example` como pendiente para cuando exista un perfil de cliente consultable
- [x] 6.5 Adaptadores de canal tras interfaz `NotificationChannel`: driver `log` (dev, deja la notificación en el log — no requiere Pinpoint/SES reales) y driver `aws` (Pinpoint para push/sms, SES para email)
- [x] 6.6 Registro de estado de entrega (`DeliveryTracker` sobre DynamoDB, mismo patrón que el resto del proyecto)
- [x] 6.7 Tests (13, incluyendo un end-to-end real: publica un evento en EventBridge → se consume → se verifica el log Y el registro de entrega en DynamoDB)
- [x] 6.8 `.env.example`
- [x] 6.9 *(agregado durante esta fase)* `App\Console\Commands\SetupNotificationInfrastructure`: provisión idempotente de la cola SQS + DLQ + regla de EventBridge + tabla DynamoDB propias de este servicio

---

## Fase 7 — BFF Web (`services/bff-web`)

**Criterio de aceptación:** agrega datos de los 3 servicios de negocio en contratos pensados para la SPA, con tests de integración contra los servicios (o sus fakes).

- [x] 7.1 Scaffold Laravel + Octane + Dockerfile
- [x] 7.2 Clientes HTTP hacia Datos Básicos, Movimientos y Transferencias (`DatosBasicosClient`/`MovimientosClient`/`TransferenciasClient`, cada uno propaga el mismo JWT del cliente hacia el servicio interno — decisión 3.5: cada servicio valida su propio token, sin credenciales de servicio a servicio aparte). *Actualización en la Fase 8:* se movieron a `packages/bp-common` (`BP\Common\Clients\*`) al detectarse que BFF Móvil necesitaba exactamente los mismos — este servicio se actualizó para consumir la versión compartida en vez de mantener su propia copia
- [x] 7.3 Endpoints agregados para la SPA: `GET /dashboard/{cuentaId}` (compone Datos Básicos + Movimientos en un solo contrato — el único endpoint que agrega de verdad), `GET /cuentas/{id}/movimientos` y `POST /transferencias` (pass-through adaptado, con envelope y manejo de errores consistentes)
- [x] 7.4 Middleware de auth (`bp-common`) en las 3 rutas
- [x] 7.5 Tests (9, con `GuzzleHttp\Handler\MockHandler` simulando los 3 servicios — opción explícitamente permitida por el criterio de aceptación de la fase — más una verificación manual real levantando los 4 procesos juntos)
- [x] 7.6 `.env.example`

---

## Fase 8 — BFF Móvil (`services/bff-mobile`)

**Criterio de aceptación:** además de lo del BFF Web, orquesta el flujo de onboarding KYC de punta a punta con el proveedor fake.

- [x] 8.1 Scaffold Laravel + Octane + Dockerfile
- [x] 8.2 Clientes hacia los 3 servicios de negocio — **reutilizados de `bp-common`** (`BP\Common\Clients\*`): al ser la segunda vez que se necesitaban exactamente iguales (la primera fue BFF Web, Fase 7), se movieron a `bp-common` en vez de duplicarlos; `bff-web` se actualizó retroactivamente para consumir la misma versión compartida (sus 9 tests se re-corrieron en verde)
- [x] 8.3 Cliente KYC fake tras interfaz `KycProvider` (driver fake / driver Onfido-iProov real vía HTTP)
- [x] 8.4 Endpoint de onboarding (`POST /onboarding`, sin `JwtAuthMiddleware` a propósito — un cliente nuevo no tiene token todavía, el control de acceso real es la verificación KYC) que orquesta: envío a KYC → alta de identidad (`IdentityProviderClient`, driver fake / Auth0 Management API real) → publica `OnboardingCompleted`/`OnboardingRejected`. *Simplificación de alcance respecto al diagrama de secuencia 8.2:* no se valida la existencia del cliente en el Core antes de crear la identidad — esa llamada requeriría que el BFF se autentique como servicio (`client_credentials`) en vez de con el JWT del usuario final, un flujo de autenticación máquina-a-máquina completo que esta fase no pedía explícitamente; queda documentado en el código (`OnboardingService`) para retomarlo si hace falta
- [x] 8.5 Cliente de liveness ligero tras interfaz `LivenessProvider` (driver fake / driver AWS Rekognition real vía `CompareFaces`, simplificación documentada de la API completa de Face Liveness) — expuesto en `POST /revalidar-liveness`, **mediado por el BFF y sí protegido con JWT** (a diferencia del diagrama de secuencia, que muestra la app llamando a Rekognition directo — se corrigió a propósito para no distribuir credenciales de AWS en el cliente móvil)
- [x] 8.6 Tests (11: onboarding sin token/aprobado/rechazado/validación, liveness aprobada/rechazada/sin token, dashboard agregando los 3 servicios)
- [x] 8.7 `.env.example`

---

## Fase 9 — Frontend SPA (`frontend-web`, React + TypeScript)

**Criterio de aceptación:** login funcional contra el mock-oidc local, y las 3 pantallas (movimientos, transferencia, confirmación) funcionan de punta a punta contra el BFF Web.

- [x] 9.1 Scaffold (Vite + React + TypeScript) + ESLint/Prettier — el scaffold de Vite trae `oxlint` (linter moderno compatible, reemplaza a ESLint clásico) en vez de ESLint; se mantiene Prettier. Ver nota de adaptación en CLAUDE.md.
- [x] 9.2 Login con Authorization Code + PKCE contra `OAUTH_MODE` configurado por `.env` — vía `oidc-client-ts` (genera PKCE automáticamente), configurable por `VITE_OIDC_ISSUER`/`VITE_OIDC_CLIENT_ID`.
- [x] 9.3 Pantalla de histórico de movimientos — consume `GET /dashboard/{cuentaId}` del BFF Web.
- [x] 9.4 Pantalla/formulario de transferencia: genera un `Idempotency-Key` por intento y lo reenvía igual ante reintentos por timeout; maneja el estado de error/compensación y el rechazo por step-up (redirige a reautenticación)
- [x] 9.5 Manejo de sesión/refresh token — `AuthProvider` con `automaticSilentRenew` (scope `offline_access`).
- [x] 9.6 Tests (al menos de los flujos críticos) — 8 tests Vitest+RTL: idempotency key (creación/reuso/reset), movimientos (éxito/error), transferencia (envío con Idempotency-Key, reuso de la clave en reintento, redirección a reautenticación por step-up).
- [x] 9.7 `.env.example`

---

## Fase 10 — Frontend Móvil (`frontend-mobile`, React Native + TypeScript)

**Criterio de aceptación:** flujo de onboarding completo (captura simulada + llamada a BFF Móvil) y login biométrico simulado funcionando en el emulador.

- [ ] 10.1 Scaffold (React Native + TypeScript) + ESLint/Prettier
- [ ] 10.2 Pantalla de onboarding (captura de documento/selfie simulada) → llamada a `bff-mobile`
- [ ] 10.3 Registro de credencial: usuario/clave y/o WebAuthn/FIDO2 (passkey) atado a biometría nativa del dispositivo (Face ID / BiometricPrompt, vía stub en el emulador)
- [ ] 10.4 Login recurrente (Authorization Code + PKCE + biometría nativa simulada, sin volver a pasar por el proveedor KYC)
- [ ] 10.5 Pantallas de movimientos y transferencia (reutilizando contratos del BFF Móvil)
- [ ] 10.6 Tests
- [ ] 10.7 `.env.example`

---

## Fase 11 — Orquestación end-to-end local

**Criterio de aceptación:** desde `docker compose up` (infraestructura + los 7 servicios) se puede hacer login en la SPA, ver movimientos y completar una transferencia, viendo el registro correspondiente en auditoría y el log de notificación.

- [ ] 11.1 `docker-compose.yml` extendido con los 7 servicios backend
- [ ] 11.2 Script de arranque único (`make dev` o similar)
- [ ] 11.3 Prueba manual de extremo a extremo documentada en WORKLOG

---

## Fase 12 — Pruebas automatizadas y CI

- [ ] 12.1 GitHub Actions: lint + test por cada servicio backend
- [ ] 12.2 GitHub Actions: lint + test para frontend-web y frontend-mobile
- [ ] 12.3 Badge de estado en el README

---

## Fase 13 — Infraestructura como código (futuro / al conectar AWS real)

**Criterio de aceptación:** cada módulo de IaC mapea 1:1 a una decisión de la sección 3 o a una consideración transversal de la sección 9 del documento de arquitectura — no se agrega infraestructura que no esté documentada, ni queda documentado algo sin su módulo.

- [ ] 13.1 Definir herramienta de IaC (Terraform o AWS CDK)
- [ ] 13.2 Red y cómputo: VPC Multi-AZ, subredes públicas/privadas, ECS Fargate (cluster + task definitions + Auto Scaling por CPU/latencia/profundidad de cola), API Gateway (JWT Authorizer + throttling)
- [ ] 13.3 Datos: Aurora MySQL (Multi-AZ + Global Database para DR), DynamoDB (Streams habilitado + Global Tables), ElastiCache Redis (Multi-AZ), S3 con Object Lock (modo Compliance) + Cross-Region Replication
- [ ] 13.4 Mensajería y notificaciones: EventBridge, SQS (con DLQ por consumidor), Pinpoint, SES
- [ ] 13.5 Seguridad: WAF + Shield Advanced en el borde, KMS (llaves separadas por dominio de dato), Secrets Manager con rotación automática, mTLS entre servicios (App Mesh o equivalente), IAM Task Roles de mínimo privilegio por servicio (uno por cada `services/*`)
- [ ] 13.6 Alta disponibilidad y DR: Route 53 con failover routing + health checks hacia la región secundaria pasiva, validación de RTO/RPO objetivo (sección 9.3)
- [ ] 13.7 Monitoreo y excelencia operativa: CloudWatch (métricas/logs/alarmas), X-Ray (tracing distribuido), CloudWatch Synthetics (canarios de login/movimientos/transferencia), GuardDuty + Security Hub
- [ ] 13.8 Identidad: Auth0/Okta CIC como Authorization Server real (reemplaza al mock-oidc), configuración de MFA adaptativo y WebAuthn/passkeys
- [ ] 13.9 Pipeline de despliegue (build de imágenes, push a ECR, deploy a ECS Fargate por ambiente)
