# Reglas de trabajo para Claude en este repositorio

Este archivo gobierna cómo debo (Claude) trabajar en este proyecto. Se aplica a toda sesión futura, no solo a la actual.

## Fuente de verdad

1. **`docs/arquitectura-banca-digital-bp.md`** es la fuente de verdad de las decisiones de arquitectura (stack, patrones, servicios AWS). Ninguna decisión de esa lista se cambia "sobre la marcha" al programar: si el código exige desviarse de una decisión, se detiene el trabajo, se explica el conflicto al usuario y se actualiza el documento primero.
2. **`CHECKLIST.md`** es la fuente de verdad de qué falta por construir y en qué orden.
3. **`WORKLOG.md`** es la fuente de verdad de qué se hizo realmente y cómo se verificó.
4. **`README.md`** es la fuente de verdad de cómo levantar el proyecto de cero (requisitos previos + pasos de instalación/arranque) para cualquiera que clone el repo, en local o en un servidor. Es la única de las cuatro pensada para un lector externo, no para gobernar el propio trabajo de desarrollo.

Antes de tocar código en una sesión nueva: leer `CHECKLIST.md` (qué sigue) y las últimas 2-3 entradas de `WORKLOG.md` (qué se hizo y qué quedó pendiente).

## Disciplina de avance (regla central)

- Se trabaja **un ítem del checklist a la vez**, en orden, respetando las dependencias entre fases.
- Un ítem se marca `[x]` **solo** cuando está implementado y verificado (build/lint/test corridos y en verde, no solo "el código parece correcto"). Si algo no se puede verificar automáticamente, se dice explícitamente cómo se verificó manualmente.
- No se avanza al siguiente ítem con el anterior a medias. Si aparece un bloqueo, se marca `[!]` en el checklist, se registra el motivo en `WORKLOG.md`, y se pregunta al usuario en vez de improvisar una solución que se desvíe de la arquitectura.
- Si durante el desarrollo aparece trabajo no previsto (una dependencia faltante, un ajuste de diseño), se agrega como ítem nuevo al checklist en vez de hacerlo "silenciosamente" y seguir de largo.
- Al cerrar un ítem (o un grupo pequeño de ítems relacionados de la misma fase), se agrega la entrada correspondiente en `WORKLOG.md` **antes** de pasar al siguiente ítem.
- No completar fases enteras sin pausar: al terminar una fase completa del checklist, resumir al usuario lo hecho y esperar confirmación antes de iniciar la siguiente fase.
- **Ninguna fase se da por cerrada sin actualizar `README.md`.** Si la fase agrega algo que alguien necesitaría para levantar el proyecto en una máquina limpia (un nuevo servicio, una nueva variable de entorno, un nuevo comando, un nuevo requisito previo como una versión de lenguaje o una herramienta), eso tiene que quedar reflejado en la sección de instalación del README en el mismo commit que cierra la fase — no después, no "cuando se acuerde". El README debe poder seguirse de punta a punta por alguien que nunca vio el proyecto, sin tener que adivinar pasos a partir del código o del checklist.

## Convenciones técnicas del proyecto

**Backend (Laravel, PHP 8.3+):**
- Un microservicio = una app Laravel independiente bajo `services/<nombre>`, con su propio `composer.json`, `Dockerfile` y `.env.example`.
- En cada servicio, fijar `"config": {"platform": {"php": "8.3.99"}}` en `composer.json` (y commitear su `composer.lock`). Sin esto, `composer install/update` resuelve versiones según el PHP del host de quien lo corra — si el host tiene un PHP más nuevo que el 8.3 de la imagen Docker (pasó con `svc-datos-basicos`: host 8.5, imagen 8.3), el lock queda con paquetes que no instalan dentro del contenedor.
- Código compartido entre servicios va en `packages/bp-common` (paquete Composer local vía path-repository) — nunca se copia/pega middleware o utilidades entre servicios. Los helpers de testing reutilizables entre servicios (como `RsaKeyPair`/`FakeJwksProvider` para firmar JWTs de prueba) van en `packages/bp-common/src/Testing/` (autoload normal), no en `tests/` del paquete (autoload-dev no es visible para otros consumidores).
- El `Dockerfile` de cada servicio se construye con el **contexto en la raíz del monorepo**, no en la carpeta del servicio, porque necesita copiar `packages/bp-common` (dependencia local vía path-repository): `docker build -f services/<nombre>/Dockerfile -t bp/<nombre> .`
- Ningún ítem de checklist que agregue un `Dockerfile` se marca `[x]` sin haber corrido `docker build` (y, si expone HTTP, `docker run` + un curl real) — un Dockerfile que "se ve bien" pero no se construyó no cuenta como verificado.
- Estilo de código: PSR-12, aplicado con Laravel Pint antes de dar por cerrado cualquier ítem de backend.
- Cómputo pensado para Octane (Swoole): evitar estado mutable en propiedades de clases que Octane mantiene vivas entre requests (singletons, estáticos) salvo que sea intencional.
- Cualquier integración externa (DynamoDB, EventBridge/SQS, S3, Auth0, KYC, red interbancaria, Pinpoint/SES) se escribe contra una **interfaz** (Repository/Adapter). Cada interfaz tiene como mínimo un driver `local` (mock/fake/LocalStack) y un driver real, seleccionados por variable de entorno — nunca un `if (app()->environment('local'))` disperso en la lógica de negocio.
- MySQL es real desde el día 1 (no se mockea): es el mismo protocolo que Aurora MySQL en producción, así que promoverlo a AWS es solo cambiar host/credenciales.
- DynamoDB (local vía LocalStack, real en AWS) usa el `DynamoDbClient`/`Marshaler` ya registrados como singleton en `bp-common` (`bp-common.dynamodb.*` por `.env`) — no crear un cliente nuevo por servicio. Ojo: `Marshaler::unmarshalItem()` deserializa un `Number` sin parte decimal como `int` en vez de `float` (ej. `30` en vez de `30.0`); si el campo es un monto u otro decimal, forzar `(float)` explícitamente al leerlo (visto en `svc-movimientos`, aplica también a la auditoría en DynamoDB de la Fase 5).
- El bus de eventos usa el `EventPublisherInterface`/`EventBridgeEventPublisher` de `bp-common` (`bp-common.events.*` por `.env`) — no reimplementar la publicación a EventBridge en cada servicio. En local, el bus de EventBridge (`bp-domain-events`) y cualquier tabla DynamoDB que un servicio necesite no existen solos: cada servicio que los use debe traer su propio comando artisan idempotente de provisión (ver `movimientos:setup-table` / `events:setup-bus` en `svc-movimientos` como referencia) y documentarlo en su sección del README.
- Todo servicio expone `GET /health` (del paquete `bp-common`) y valida JWT con el middleware compartido. **Excepción:** los servicios "worker puro" que solo consumen SQS (Auditoría, Notificaciones) no sirven HTTP y por lo tanto no necesitan Octane/Swoole ni exponen ese `/health` — su Dockerfile corre directo el comando de consumo como proceso principal.
- Un servicio descrito en la arquitectura como "Laravel Horizon Worker" no siempre implica usar Horizon en sentido estricto: Horizon supervisa la cola *interna* de Laravel (Redis/database); si la fuente real de trabajo es una cola SQS con el sobre de EventBridge (no un job de Laravel), el patrón correcto es un comando Artisan de larga duración que hace long-polling directo contra el SDK de AWS (ver `audit:consume` en `svc-auditoria`, con una opción `--once` para poder probarlo sin loop infinito). Aplica igual para Notificaciones en la Fase 6.
- Un comando/worker que consume mensajes de una cola nunca debe quedar en silencio total al terminar de procesar uno con éxito — si solo loguea en el `catch`, un "no pasó nada" es indistinguible de un "no llegó nada". Loguear explícitamente tanto el éxito como el error.
- Cada servicio commitea su `.env.example`; el `.env` real siempre va en `.gitignore`.

**Entorno local (Docker):**
- El entorno de infraestructura se maneja con `make up` / `make down` / `make logs` / `make ps` (nunca `docker compose` directo salvo para depurar), para que el flujo sea el mismo sin importar cuántos servicios se agreguen.
- Si una imagen Docker de terceros crashea en bucle en esta máquina (Apple Silicon) con errores que huelen a incompatibilidad nativa (p. ej. `FileLoadException` de .NET, "exec format error"), probar primero fijando `platform: linux/amd64` en el servicio antes de buscar otra imagen — resolvió el caso de `oidc-server-mock`.
- Al levantar varios servicios a la vez con comandos encadenados (`cd servicio-a && php artisan serve ... &` seguido de otro `cd servicio-b && ...`), el directorio de trabajo del shell persiste entre llamadas de la herramienta Bash **aunque el comando se haya lanzado en segundo plano** — si el siguiente comando no vuelve a hacer `cd` explícito a su propio servicio, arranca en el directorio equivocado. Se detectó en la Fase 7 porque cada `/health` imprime el nombre de su propio servicio: verificar siempre el *contenido* de `/health` al levantar varios procesos juntos, no solo el código de estado 200.

**Frontend (React + TypeScript / React Native + TypeScript):**
- ESLint + Prettier obligatorios, sin warnings bloqueantes al cerrar un ítem. **Adaptación (Fase 9):** el scaffold actual de `npm create vite@latest -- --template react-ts` trae `oxlint` (linter Rust, reglas compatibles con ESLint/React/TypeScript) en vez de ESLint clásico — se usa `oxlint` como reemplazo funcional de esa regla, no se reinstala ESLint clásico encima. Prettier se mantiene igual.
- El `tsconfig` de ese mismo scaffold trae `erasableSyntaxOnly: true`, que prohíbe *parameter properties* de TypeScript (`constructor(public readonly x: T) {}`) porque generan código no "erasable" en tiempo de compilación — declarar las propiedades explícitamente y asignarlas en el cuerpo del constructor (visto en `BffError` de `frontend-web`).
- Lógica de negocio compartible entre SPA y móvil (validaciones, formateo, llamadas a API) se aísla en módulos reutilizables, no se duplica entre ambos proyectos.
- Nunca se hardcodea la URL de un BFF ni el modo de OAuth: todo por variables de entorno (`.env` / `.env.local` según el bundler), con su `.env.example`.
- El `client_id` y `redirect_uri` de OAuth de cada frontend deben coincidir exactamente con los ya registrados en `CLIENTS_CONFIGURATION_INLINE` de `mock-oidc` (`docker-compose.yml`) — ahí ya está preconfigurado un cliente público `bp-web` con PKCE obligatorio y `redirect_uri` `http://localhost:5173/callback` (SPA) y `http://localhost:19006/callback` (reservado para la app móvil/Expo, Fase 10). No inventar un `client_id` nuevo sin agregarlo también a esa configuración.
- Sin navegador disponible en este entorno, un flujo OAuth se puede verificar de forma real (más allá de tests unitarios) construyendo a mano la URL de `/connect/authorize` con los mismos parámetros que generaría la librería del cliente (`client_id`, `redirect_uri`, `code_challenge`/`code_challenge_method=S256` para PKCE) y confirmando con `curl` que el emisor OIDC la acepta sin `invalid_client`/`invalid_scope` y redirige a su propia pantalla de login — no reemplaza un click real, pero prueba que el cableado (cliente registrado, redirect_uri, scopes, PKCE) es correcto de punta a punta.

**Tests de frontend (Vitest + React Testing Library):**
- Cuando un mock de función (`vi.fn()`) se define a nivel de módulo (dentro de `vi.mock(...)`) y se usa en varios `it()` del mismo archivo, resetear sus llamadas/implementaciones encoladas en `beforeEach` con `vi.mocked(fn).mockReset()` — si no, los conteos de llamadas y los `mockResolvedValueOnce`/`mockRejectedValueOnce` de un test se filtran al siguiente (pasó en `TransferenciaPage.test.tsx`: un test veía 3 llamadas acumuladas donde esperaba 2, arrastradas del test anterior). `mockClear()` no alcanza porque no vacía la cola de implementaciones `Once` pendientes de un mock ya consumido a medias.

**Pruebas:**
- Ningún ítem de checklist que agregue una regla de negocio (idempotencia, compensación de Saga, invalidación de caché, apertura de Circuit Breaker, etc.) se marca `[x]` sin al menos un test automatizado que la ejerza.
- Los tests corren contra la infraestructura local (`docker compose`), nunca contra servicios reales de AWS o proveedores externos.
- MySQL se limpia solo entre tests con `Illuminate\Foundation\Testing\DatabaseTransactions` (cada test corre en una transacción que se revierte al final). **Redis no se revierte solo** — cualquier test que escriba estado compartido en Redis con una clave fija (locks, contadores de Circuit Breaker, etc., no claves con un id único por test) tiene que limpiarlo explícitamente en `tearDown()`, o contamina al siguiente test que use la misma clave (pasó en `svc-transferencias` con el estado del Circuit Breaker). Cuando la clave sí incluye un identificador único por test (un `cuentaId` con UUID, por ejemplo), no hace falta limpieza porque no hay colisión posible.

**Seguridad:**
- Nunca commitear secretos, tokens o credenciales reales — solo valores de ejemplo en `.env.example`.
- Antes de un `git add` amplio, revisar que no se cuele un `.env` real ni credenciales de prueba con aspecto de reales.

## Git y control de versiones

- Nunca crear commits ni hacer push sin que el usuario lo pida explícitamente para esa tanda de cambios (regla general ya vigente en este proyecto, no específica de código).
- Mensajes de commit en español, estilo conventional commits (`feat:`, `fix:`, `docs:`, `chore:`, `test:`), describiendo el *por qué* del cambio, no solo el qué.
- No usar `--force`, `--no-verify` ni reescritura de historia sin pedirlo explícitamente el usuario.

## Comunicación con el usuario

- Ser explícito sobre qué fase/ítem del checklist se está trabajando en cada momento.
- Si una decisión de la arquitectura resulta poco práctica al implementarla (por ejemplo, una limitación real de LocalStack o de Octane), decirlo y proponer alternativa — no decidir en silencio.
- Resúmenes de fin de turno: qué ítems quedaron en `[x]`, cuáles en progreso, y cuál es el siguiente paso concreto.
