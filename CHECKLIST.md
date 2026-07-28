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

---

## Fase 0 — Fundación del monorepo y entorno local

**Criterio de aceptación de la fase:** `docker compose up` levanta todos los contenedores de infraestructura sin error y cada uno responde a un chequeo básico (ping/health).

- [ ] 0.1 Crear estructura de carpetas: `services/`, `packages/`, `frontend-web/`, `frontend-mobile/`, `infra/`
- [ ] 0.2 `docker-compose.yml` raíz con: MySQL 8, Redis, LocalStack (DynamoDB, SQS, EventBridge, S3), servicio mock-oidc (emisor JWT local)
- [ ] 0.3 `.env.example` raíz documentando variables compartidas (puertos, credenciales de desarrollo, endpoints de LocalStack)
- [ ] 0.4 `Makefile` (o script equivalente) con `make up`, `make down`, `make logs`
- [ ] 0.5 Verificación manual: todos los contenedores arriba y responden

---

## Fase 1 — Paquete compartido `packages/bp-common`

**Criterio de aceptación:** el paquete tiene tests unitarios en verde y puede instalarse como path-repository desde un servicio de prueba.

- [ ] 1.1 Scaffold del paquete Composer (`packages/bp-common`)
- [ ] 1.2 Middleware de validación JWT con dos modos por `.env` (`OAUTH_MODE=local` con clave RSA propia / `OAUTH_MODE=auth0` con JWKS remoto)
- [ ] 1.3 Middleware de Correlation-Id para trazabilidad entre servicios
- [ ] 1.4 Formato estándar de respuesta y de error de la API (envelope común)
- [ ] 1.5 Trait/endpoint de healthcheck reutilizable (`GET /health`)
- [ ] 1.6 Tests unitarios del paquete
- [ ] 1.7 Confirmar que un servicio puede requerirlo vía path-repository en `composer.json`

---

## Fase 2 — Servicio Datos Básicos (`services/svc-datos-basicos`)

**Criterio de aceptación:** expone un endpoint que compone datos de dos fuentes simuladas (Core + complementario) y sus tests pasan.

- [ ] 2.1 Scaffold Laravel + Octane + Dockerfile
- [ ] 2.2 Cliente fake del Core Bancario (fixture/HTTP fake) tras interfaz `CoreBancarioClient`
- [ ] 2.3 Cliente fake del Sistema Complementario tras interfaz `ClienteComplementarioClient`
- [ ] 2.4 Endpoint de composición `GET /clientes/{id}` (patrón API Composition)
- [ ] 2.5 Aplicar middleware de `bp-common` (auth, correlation-id, healthcheck)
- [ ] 2.6 Tests (unitarios + endpoint)
- [ ] 2.7 `.env.example` documentando cómo apuntar los clientes fake a los sistemas reales el día que existan

---

## Fase 3 — Servicio Movimientos (`services/svc-movimientos`)

**Criterio de aceptación:** el patrón Cache-Aside se puede demostrar con un test (primera lectura pega a la "base", segunda lectura pega a caché) y los tests pasan.

- [ ] 3.1 Scaffold Laravel + Octane + Dockerfile
- [ ] 3.2 Repository de movimientos: driver `dynamodb-local` (vía LocalStack/DynamoDB Local) y driver `dynamodb` real, tras interfaz `MovimientosRepository`
- [ ] 3.3 Cache-Aside con Redis (ElastiCache-compatible) para últimos movimientos
- [ ] 3.4 Endpoint `GET /cuentas/{id}/movimientos`
- [ ] 3.5 Publicación del evento `MovementRegistered` al bus (interfaz `EventPublisher`, driver local EventBridge/SQS vía LocalStack)
- [ ] 3.6 Tests (incluye test explícito del comportamiento Cache-Aside)
- [ ] 3.7 `.env.example`

---

## Fase 4 — Servicio Transferencias (`services/svc-transferencias`)

**Criterio de aceptación:** tests cubren el camino feliz, el rechazo por idempotencia repetida, y la compensación ante fallo del banco destino (circuito abierto o error).

- [ ] 4.1 Scaffold Laravel + Octane + Dockerfile
- [ ] 4.2 Modelo/migraciones MySQL: cuentas, saldos, transferencias, idempotency_keys
- [ ] 4.3 Middleware de Idempotency-Key (Redis)
- [ ] 4.4 Orquestador Saga (débito → llamada externa → commit/compensación)
- [ ] 4.5 Cliente interbancario fake tras interfaz `InterbankClient`, envuelto en Circuit Breaker (retry + backoff + apertura de circuito)
- [ ] 4.6 Publicación de `TransferCompleted` / `TransferFailed` al bus
- [ ] 4.7 Endpoint `POST /transfers`
- [ ] 4.8 Tests (camino feliz, idempotencia, compensación, circuito abierto)
- [ ] 4.9 `.env.example`

---

## Fase 5 — Servicio Auditoría (`services/svc-auditoria`)

**Criterio de aceptación:** un evento publicado por Transferencias o Movimientos aparece persistido en el store de auditoría local.

- [ ] 5.1 Scaffold Laravel + Horizon + Dockerfile
- [ ] 5.2 Consumidor de cola (SQS local vía LocalStack) para eventos de dominio
- [ ] 5.3 Repository de auditoría: driver local (DynamoDB Local) y driver real (DynamoDB + Streams a S3 Object Lock), tras interfaz `AuditRepository`
- [ ] 5.4 Registro de auditoría con actor, acción, timestamp y hash del evento
- [ ] 5.5 Tests (consumo de evento de prueba → registro persistido)
- [ ] 5.6 `.env.example`

---

## Fase 6 — Servicio Notificaciones (`services/svc-notificaciones`)

**Criterio de aceptación:** un evento de prueba dispara un log de notificación con el canal y contenido correctos; el cambio a AWS real es solo de configuración.

- [ ] 6.1 Scaffold Laravel + Horizon + Dockerfile
- [ ] 6.2 Consumidor de cola para eventos de dominio
- [ ] 6.3 `ChannelRouter` que decide push/SMS/email según tipo de evento
- [ ] 6.4 Adaptadores de canal tras interfaz `NotificationChannel`: driver `log` (dev) y driver `aws` (Pinpoint + SES)
- [ ] 6.5 Registro de estado de entrega (`DeliveryTracker`)
- [ ] 6.6 Tests
- [ ] 6.7 `.env.example`

---

## Fase 7 — BFF Web (`services/bff-web`)

**Criterio de aceptación:** agrega datos de los 3 servicios de negocio en contratos pensados para la SPA, con tests de integración contra los servicios (o sus fakes).

- [ ] 7.1 Scaffold Laravel + Octane + Dockerfile
- [ ] 7.2 Clientes HTTP hacia Datos Básicos, Movimientos y Transferencias
- [ ] 7.3 Endpoints agregados para la SPA
- [ ] 7.4 Middleware de auth (`bp-common`)
- [ ] 7.5 Tests
- [ ] 7.6 `.env.example`

---

## Fase 8 — BFF Móvil (`services/bff-mobile`)

**Criterio de aceptación:** además de lo del BFF Web, orquesta el flujo de onboarding KYC de punta a punta con el proveedor fake.

- [ ] 8.1 Scaffold Laravel + Octane + Dockerfile
- [ ] 8.2 Clientes hacia los 3 servicios de negocio
- [ ] 8.3 Cliente KYC fake tras interfaz `KycProvider` (driver fake / driver Onfido-iProov real)
- [ ] 8.4 Endpoint de onboarding (`POST /onboarding`) que orquesta: envío a KYC → alta en Auth0/mock-oidc → respuesta al cliente
- [ ] 8.5 Tests (aprobado, rechazado)
- [ ] 8.6 `.env.example`

---

## Fase 9 — Frontend SPA (`frontend-web`, React + TypeScript)

**Criterio de aceptación:** login funcional contra el mock-oidc local, y las 3 pantallas (movimientos, transferencia, confirmación) funcionan de punta a punta contra el BFF Web.

- [ ] 9.1 Scaffold (Vite + React + TypeScript) + ESLint/Prettier
- [ ] 9.2 Login con Authorization Code + PKCE contra `OAUTH_MODE` configurado por `.env`
- [ ] 9.3 Pantalla de histórico de movimientos
- [ ] 9.4 Pantalla/formulario de transferencia (con manejo de estado de error/compensación)
- [ ] 9.5 Manejo de sesión/refresh token
- [ ] 9.6 Tests (al menos de los flujos críticos)
- [ ] 9.7 `.env.example`

---

## Fase 10 — Frontend Móvil (`frontend-mobile`, React Native + TypeScript)

**Criterio de aceptación:** flujo de onboarding completo (captura simulada + llamada a BFF Móvil) y login biométrico simulado funcionando en el emulador.

- [ ] 10.1 Scaffold (React Native + TypeScript) + ESLint/Prettier
- [ ] 10.2 Pantalla de onboarding (captura de documento/selfie simulada) → llamada a `bff-mobile`
- [ ] 10.3 Registro de credencial (usuario/clave) y stub de biometría nativa
- [ ] 10.4 Login recurrente (PKCE + biometría simulada)
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

- [ ] 13.1 Definir herramienta de IaC (Terraform o AWS CDK)
- [ ] 13.2 Módulos para VPC, ECS Fargate, Aurora, ElastiCache, DynamoDB, EventBridge/SQS, S3, API Gateway
- [ ] 13.3 Pipeline de despliegue
