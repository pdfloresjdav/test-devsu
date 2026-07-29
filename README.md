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
└── infra/                   # Infraestructura como código para AWS (Fase 14)
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
- `GET /customers/{id}` — requiere `Authorization: Bearer <JWT>` válido contra el emisor configurado (mock-oidc en local, Auth0 en producción). Prueba con `1001` o `1002`.

Correr sus tests: `php artisan test` (10 tests: fakes, composición y el endpoint completo, incluyendo el rechazo sin token).

Para apuntar a los sistemas reales cuando existan: `CORE_BANKING_DRIVER=http` + `CORE_BANKING_BASE_URL=...` y/o `CUSTOMER_PROFILE_DRIVER=http` + `CUSTOMER_PROFILE_BASE_URL=...` en su `.env` — no requiere cambiar código.

Construir su imagen (Octane + Swoole, igual que en producción) desde la **raíz del repo** (necesita `packages/bp-common` en el contexto):

```bash
docker build -f services/svc-datos-basicos/Dockerfile -t bp/svc-datos-basicos .
```

#### `services/svc-movimientos` (Fase 3)

Histórico de movimientos con patrón **Cache-Aside** (Redis) sobre un repositorio de **DynamoDB** (LocalStack en local, AWS real en producción — mismo código), y publicación del evento `MovementRegistered` a EventBridge al registrar un movimiento nuevo.

```bash
cd services/svc-movimientos
cp .env.example .env
composer install

# Provisionar la infraestructura local (una sola vez; make up ya debe estar corriendo):
php artisan movements:setup-table     # crea la tabla DynamoDB en LocalStack si no existe
php artisan events:setup-bus          # crea el bus de EventBridge "bp-domain-events" si no existe

php artisan serve --port=8002
```

Endpoints (requieren `Authorization: Bearer <JWT>`):
- `GET /accounts/{id}/movements` — historial, más reciente primero (Cache-Aside: la primera lectura pega a DynamoDB, las siguientes a Redis hasta que se registre un movimiento nuevo o expire el TTL).
- `POST /accounts/{id}/movements` — registra un movimiento (`type`: `debit`|`credit`, `amount`, `description`), invalida la caché de esa cuenta y publica `MovementRegistered`.

Correr sus tests: `php artisan test` (9 tests, corren contra el Redis y el LocalStack reales de `docker-compose` — no hay mocks del SDK de AWS).

Construir su imagen: `docker build -f services/svc-movimientos/Dockerfile -t bp/svc-movimientos .` desde la raíz del repo.

#### `services/svc-transferencias` (Fase 4)

Orquesta transferencias propias e interbancarias con patrón **Saga** (débito → llamada externa → confirmación o compensación), **Idempotency-Key** (Redis), **Circuit Breaker** con retry/backoff sobre el cliente interbancario, y **autenticación reforzada (step-up)** obligatoria para montos grandes.

```bash
cd services/svc-transferencias
cp .env.example .env
composer install
php artisan migrate   # crea las tablas en la base MySQL dedicada "svc_transfers"
php artisan serve --port=8003
```

> La base `svc_transfers` se crea sola la primera vez que se levanta el contenedor de MySQL (`infra/mysql-init/01-databases.sql`). Si tu MySQL local ya existía antes de este script, créala a mano una vez: `docker exec bp-mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS svc_transfers;"`.

Endpoint: `POST /transfers` (requiere `Authorization: Bearer <JWT>` **e** `Idempotency-Key`), body `{source_account, destination_account, amount, description}`.
- Reintentar con la misma `Idempotency-Key` devuelve la misma respuesta sin volver a debitar.
- Si `destination_account` empieza con `FAIL-`, el cliente interbancario fake simula un rechazo del banco destino → la transferencia queda `failed` y el saldo se compensa (se revierte el débito).
- Si `amount` supera `STEP_UP_THRESHOLD` (1000 por defecto) y el JWT no trae `acr=step-up` (ni `amr` con `mfa`), responde `403 step_up_required`.

Correr sus tests: `php artisan test` (11 tests, contra el MySQL/Redis/LocalStack reales de `docker-compose`, con cada test envuelto en una transacción que se revierte al final).

Construir su imagen: `docker build -f services/svc-transferencias/Dockerfile -t bp/svc-transferencias .` desde la raíz del repo.

#### `services/svc-auditoria` (Fase 5)

Consumidor de eventos de dominio: registra en DynamoDB (con hash de integridad) toda acción publicada por otros servicios, y archiva cada registro en un bucket S3 (stand-in local del WORM Object Lock de AWS). No expone HTTP — es un worker puro.

```bash
cd services/svc-auditoria
cp .env.example .env
composer install

# Provisionar la infraestructura local (una sola vez; make up ya debe estar corriendo):
php artisan audit:setup-infrastructure   # crea la cola SQS + su DLQ, la regla de EventBridge, la tabla DynamoDB y el bucket S3
```

> Ojo: a diferencia de la tabla DynamoDB (que persiste entre reinicios de `docker-compose`), el bus/regla de EventBridge de LocalStack **no sobrevive** un reinicio del contenedor — si `make down && make up` se corrió después de provisionar, hay que volver a correr `audit:setup-infrastructure` (y el `events:setup-bus` de `svc-movimientos`) antes de seguir.

Correr el consumidor:

```bash
php artisan audit:consume          # long-polling continuo (Ctrl+C para parar)
php artisan audit:consume --once   # procesa un solo ciclo y termina (para probar a mano)
```

Correr sus tests: `php artisan test` (9 tests contra el DynamoDB/S3/SQS/EventBridge reales de `docker-compose`, incluyendo un test end-to-end: publica un evento real → lo consume → lo verifica persistido).

Construir su imagen: `docker build -f services/svc-auditoria/Dockerfile -t bp/svc-auditoria .` desde la raíz del repo (no lleva Swoole: es un worker, no sirve HTTP).

#### `services/svc-notificaciones` (Fase 6)

Consumidor de eventos de dominio (mismo patrón que Auditoría, con su propia cola SQS): decide el canal según el tipo de evento (`ChannelRouter`), genera el contenido (`TemplateEngine`), lo "envía" y registra el resultado de entrega (`DeliveryTracker` en DynamoDB). No expone HTTP.

```bash
cd services/svc-notificaciones
cp .env.example .env
composer install

# Provisionar la infraestructura local (una sola vez; make up ya debe estar corriendo):
php artisan notifications:setup-infrastructure   # crea la cola SQS + su DLQ, la regla de EventBridge y la tabla DynamoDB
```

> Mismo recordatorio que en Auditoría: el bus/regla de EventBridge no sobrevive un reinicio de `docker-compose`; si se reinició, hay que volver a correr este comando de provisión.

Correr el consumidor: `php artisan notifications:consume` (continuo) o `php artisan notifications:consume --once` (un ciclo, para probar a mano).

Con `NOTIFICATION_DRIVER=log` (el default en `.env.example`), cada notificación queda en `storage/logs/laravel.log` con el canal, destinatario y contenido — no requiere credenciales de Pinpoint/SES. Cambiar a `NOTIFICATION_DRIVER=aws` activa Pinpoint (push/sms) y SES (email) reales sin tocar código. *Nota: Pinpoint no tiene soporte gratuito en LocalStack, así que el driver `aws` no se prueba en local, solo el `log`.*

Correr sus tests: `php artisan test` (13 tests, incluyendo un end-to-end real contra el SQS/EventBridge/DynamoDB de `docker-compose`).

Construir su imagen: `docker build -f services/svc-notificaciones/Dockerfile -t bp/svc-notificaciones .` desde la raíz del repo (worker puro, sin Swoole).

#### `services/bff-web` (Fase 7)

Agrega los 3 servicios de negocio en contratos pensados para la SPA. Propaga el mismo JWT del cliente hacia cada servicio interno (cada uno lo valida por su cuenta — no hay credenciales de servicio a servicio aparte).

```bash
cd services/bff-web
cp .env.example .env   # ya apunta a los puertos 8001/8002/8003 de los servicios de negocio
composer install
php artisan serve --port=8010
```

Para probarlo de punta a punta hace falta tener corriendo `svc-datos-basicos` (puerto 8001), `svc-movimientos` (8002) y `svc-transferencias` (8003) — ver sus propias secciones de este README.

Endpoints (todos requieren `Authorization: Bearer <JWT>`):
- `GET /dashboard/{accountId}` — compone Datos Básicos + Movimientos en un solo contrato (el único endpoint que agrega de verdad; los otros dos son pass-through adaptado).
- `GET /accounts/{accountId}/movements` — reenvía a Movimientos.
- `POST /transfers` (requiere además `Idempotency-Key`) — reenvía a Transferencias.

Correr sus tests: `php artisan test` (9 tests, usando `GuzzleHttp\Handler\MockHandler` para simular los 3 servicios de negocio — opción que permite explícitamente el criterio de aceptación de esta fase, en vez de levantar 3 servidores reales en cada corrida de tests).

Construir su imagen: `docker build -f services/bff-web/Dockerfile -t bp/bff-web .` desde la raíz del repo.

#### `services/bff-mobile` (Fase 8)

Igual que BFF Web (los 3 clientes de negocio vienen de `bp-common`, compartidos entre ambos BFFs), más la orquestación de **onboarding biométrico**: verificación KYC → alta de identidad en el proveedor de identidad → publicación de `OnboardingCompleted`/`OnboardingRejected`. También expone la revalidación de liveness para operaciones sensibles.

```bash
cd services/bff-mobile
cp .env.example .env
composer install
php artisan serve --port=8004
```

Endpoints:
- `POST /onboarding` — **sin autenticación** (un cliente nuevo no tiene token todavía; el control de acceso real es la verificación KYC). Body: `{customer_id, name, email, identity_document, selfie}`. Con `KYC_DRIVER=fake` (default), cualquier `identity_document` que empiece con `REJECT-` simula un rechazo del proveedor KYC.
- `POST /revalidate-liveness` (requiere JWT) — `{reference_selfie, new_selfie}`. Con `LIVENESS_DRIVER=fake` (default), `new_selfie: "REJECT"` simula una revalidación fallida.
- `GET /dashboard/{accountId}`, `GET /accounts/{accountId}/movements`, `POST /transfers` — igual que en BFF Web.

Correr sus tests: `php artisan test` (11 tests: onboarding aprobado/rechazado/validación/sin-token-necesario, liveness aprobada/rechazada/sin-token, y el dashboard agregado).

Construir su imagen: `docker build -f services/bff-mobile/Dockerfile -t bp/bff-mobile .` desde la raíz del repo.

*(El resto de los servicios backend ya está completo — 5 microservicios de negocio/workers + 2 BFFs. A partir de aquí, las Fases 9 y 10 son del lado de los frontends.)*

### Frontends (`frontend-web/`, `frontend-mobile/`)

#### `frontend-web` (Fase 9)

SPA en React + TypeScript (Vite). Login por Authorization Code + PKCE contra el emisor OIDC configurado (`mock-oidc` local o Auth0/Okta CIC real), histórico de movimientos y transferencias contra `bff-web`.

```bash
cd frontend-web
cp .env.example .env   # ya apunta al mock-oidc (puerto 4011 vía docker-compose, client_id "bp-web") y a bff-web (8010)
npm install
npm run dev             # sirve en http://localhost:5173
```

Requiere `bff-web` corriendo (ver su propia sección) y el `mock-oidc` de `docker-compose` (`make up`) para poder iniciar sesión en local.

Variables de `.env.example`: `VITE_OIDC_ISSUER`, `VITE_OIDC_CLIENT_ID`, `VITE_BFF_WEB_URL`, `VITE_DEMO_ACCOUNT_ID` (cuenta precargada en el formulario de consulta, solo para demos locales).

Scripts disponibles:
- `npm run dev` / `npm run build` / `npm run preview`
- `npm run lint` — `oxlint` (el scaffold actual de Vite trae este linter moderno en vez de ESLint clásico)
- `npm run format` / `npm run format:check` — Prettier
- `npm run test` — Vitest + React Testing Library (8 tests: manejo de la `Idempotency-Key`, pantalla de movimientos, formulario de transferencia incluyendo el reenvío ante rechazo por autenticación reforzada / step-up)

Pantallas: `/login`, `/callback` (retorno del flujo PKCE), `/` (movimientos, protegida), `/transfers` y `/transfers/confirmation` (protegidas).

> Nota de verificación: en este entorno no hay herramienta de automatización de navegador disponible, así que la fase se verificó con la suite de tests automatizados más una comprobación real de que `npm run dev` sirve el shell de la SPA y sus rutas cliente (`curl` a `/`, `/login`, `/transfers`) — no con un recorrido manual de clics en navegador.

#### `frontend-mobile` (Fase 10)

App React Native + TypeScript (Expo). Onboarding con captura de documento/selfie simulada, registro de credencial atado a biometría nativa del dispositivo, login recurrente por biometría sin repetir el KYC, e historial de movimientos/transferencias contra `bff-mobile`.

```bash
cd frontend-mobile
cp .env.example .env   # ya apunta al mock-oidc (puerto 4011, client_id "bp-web") y a bff-mobile (8004)
npm install
npm start               # abre el menú de Expo (i = iOS, a = Android, w = web)
```

Requiere `bff-mobile` corriendo (ver su propia sección) y el `mock-oidc` de `docker-compose` (`make up`) para poder iniciar sesión.

Variables de `.env.example` (con prefijo `EXPO_PUBLIC_`, que Expo inlinea en build automáticamente — equivalente al `VITE_` de frontend-web): `EXPO_PUBLIC_OIDC_ISSUER`, `EXPO_PUBLIC_OIDC_CLIENT_ID`, `EXPO_PUBLIC_BFF_MOBILE_URL`, `EXPO_PUBLIC_DEMO_ACCOUNT_ID`.

Scripts disponibles:
- `npm start` / `npm run ios` / `npm run android` / `npm run web`
- `npm run lint` — `eslint-config-expo` (el scaffold de Expo trae este preset de ESLint en vez de configurarlo a mano)
- `npm run format` / `npm run format:check` — Prettier
- `npm run test` — Jest (`jest-expo`) + React Native Testing Library (11 tests: Idempotency-Key, onboarding con aprobación/rechazo KYC, movimientos, transferencia incluyendo el reintento con la misma Idempotency-Key y el disparo de reautenticación ante rechazo por step-up)

Pantallas (enrutadas con un router simple basado en estado de React, no `@react-navigation` — ver WORKLOG.md): login/onboarding inicial, registro de credencial, movimientos, transferencia y confirmación.

> **Limitación importante de este entorno:** no hay Xcode Simulator, Android SDK/emulador, `adb` ni `watchman` instalados acá, así que el criterio de aceptación de esta fase ("funcionando en el emulador") no se pudo verificar de esa forma. Se verificó con lint/type-check/tests automatizados reales, más un smoke test real sirviendo la app con `npm run web` (Expo con react-native-web, en el puerto 19006 ya reservado para esta app en `mock-oidc`) y una comprobación manual del flujo OAuth contra el emisor real (ver WORKLOG.md). **Face ID/BiometricPrompt real y `expo-secure-store` nativo quedan pendientes de probarse en un dispositivo o development build real** — no funcionan en la vista web de desarrollo (documentado en `src/auth/biometric.ts` y `src/storage/secureStorage.ts`).

## Orquestación completa (Fase 11)

Además de `make up` (solo infraestructura, el flujo de desarrollo de las Fases 2-10 con cada servicio corriendo a mano vía `php artisan serve`/`octane:start`), `make dev` levanta **todo**: infraestructura + los 7 servicios backend, cada uno buildeado desde su propio `Dockerfile`.

```bash
make dev       # infra + los 7 servicios backend (build + up -d)
make dev-ps    # estado de los 11 contenedores
make dev-logs  # logs en vivo de todo el stack
make dev-down  # apaga todo (los datos de MySQL/LocalStack persisten en sus volúmenes)
```

Cada contenedor corre sus migraciones y sus comandos de provisión idempotentes (tabla DynamoDB, bus/reglas de EventBridge, colas SQS) automáticamente al arrancar — no hace falta ningún paso manual aparte de `make dev`. Puertos expuestos: `svc-datos-basicos` 8001, `svc-movimientos` 8002, `svc-transferencias` 8003, `bff-mobile` 8004, `bff-web` 8010 (`svc-auditoria`/`svc-notificaciones` son workers puros, sin puerto HTTP).

Con el stack completo arriba, el criterio de aceptación de esta fase (login → ver movimientos → completar una transferencia → verlo reflejado en auditoría/notificaciones) se verificó de punta a punta contra los contenedores reales — incluido un login real (Authorization Code + PKCE) contra `mock-oidc` sin necesitar un navegador, ver [`WORKLOG.md`](WORKLOG.md) para el detalle completo de cómo.

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
- Desarrollo: Fases 0 a 8 completas — entorno local, `packages/bp-common`, los 5 microservicios de negocio/workers (Datos Básicos, Movimientos, Transferencias, Auditoría, Notificaciones) y los 2 BFFs (Web y Móvil, con onboarding biométrico). Queda 9-10 (frontends), 11-12 (orquestación/CI) y 13 (IaC). Ver `CHECKLIST.md`.
