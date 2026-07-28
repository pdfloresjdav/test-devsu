# Work Log — Banca Digital BP

Bitácora cronológica del desarrollo. Cada entrada corresponde a uno o más ítems cerrados en [`CHECKLIST.md`](CHECKLIST.md). No se agregan entradas por trabajo que no esté verificado — ver reglas en ese mismo documento.

**Formato de cada entrada:**

```
## AAAA-MM-DD

- **Ítem(s) del checklist:** referencia (ej. 0.1, 0.2)
- **Qué se hizo:** resumen concreto
- **Cómo se verificó:** comando/test ejecutado y resultado
- **Desviaciones respecto a la arquitectura o al checklist:** si las hubo, y por qué
- **Bloqueos / pendientes para retomar:** si aplica
```

---

## 2026-07-28

- **Ítem(s) del checklist:** N/A (preparación previa a la Fase 0)
- **Qué se hizo:**
  - Se cerró y publicó el documento de arquitectura (`docs/arquitectura-banca-digital-bp.md`) con las 14 decisiones justificadas, los 3 niveles C4, el diagrama de despliegue y los diagramas dinámicos de transferencia y onboarding.
  - Se confirmaron con el usuario las decisiones de implementación para esta etapa:
    1. Backend como **microservicios Laravel separados desde el inicio** (7 servicios: `svc-datos-basicos`, `svc-movimientos`, `svc-transferencias`, `svc-auditoria`, `svc-notificaciones`, `bff-web`, `bff-mobile`).
    2. **MySQL real en local** (Docker) desde ya para la persistencia relacional, con posibilidad de apuntar a Aurora MySQL en AWS cambiando solo `.env`.
    3. El resto de integraciones (DynamoDB, EventBridge/SQS, S3, Auth0/OIDC, KYC, Rekognition, red interbancaria, Pinpoint/SES) se implementan contra interfaces (Repository/Adapter) con un driver local (mocks/LocalStack) y un driver real (AWS/proveedor), seleccionable por `.env`. Cada servicio debe traer su `.env.example`.
  - Se crearon `CHECKLIST.md` (plan de desarrollo por fases) y `CLAUDE.md` (reglas de trabajo) para gobernar el resto del desarrollo.
- **Cómo se verificó:** revisión manual del contenido junto con el usuario (aprobación explícita de las decisiones anteriores).
- **Desviaciones:** ninguna respecto a lo acordado.
- **Bloqueos / pendientes para retomar:** comenzar por la Fase 0 del checklist (fundación del monorepo y entorno local).

## 2026-07-28 (continuación — Fase 0)

- **Ítem(s) del checklist:** 0.1, 0.2, 0.3, 0.4, 0.5
- **Qué se hizo:**
  - Se creó la estructura de carpetas del monorepo: `services/`, `packages/`, `frontend-web/`, `frontend-mobile/`, `infra/` (cada una con `.gitkeep` por ahora).
  - Se creó `docker-compose.yml` en la raíz con 4 servicios de infraestructura: `mysql` (8.0), `redis` (7-alpine), `localstack` (3, con DynamoDB/SQS/EventBridge/S3 habilitados) y `mock-oidc` (`ghcr.io/soluto/oidc-server-mock`, como stand-in local de Auth0/Okta con un cliente `bp-web` configurado para Authorization Code + PKCE y un usuario de prueba).
  - Se creó `.env.example` en la raíz documentando las variables de los 4 servicios y el punto de conmutación a AWS/Auth0 real (`OAUTH_MODE=local|auth0`).
  - Se creó `Makefile` con `make up` / `make down` / `make logs` / `make ps` / `make restart`.
- **Cómo se verificó:**
  - `make up` levantó los 4 contenedores sin error.
  - `docker compose ps` reporta los 4 como `healthy`.
  - `mysqladmin ping` → `mysqld is alive`.
  - `redis-cli ping` → `PONG`.
  - `curl http://localhost:4566/_localstack/health` → `dynamodb`, `sqs`, `events`, `s3` en estado `available`.
  - `curl http://localhost:4011/.well-known/openid-configuration` → documento de discovery OIDC válido con `authorization_endpoint`, `token_endpoint` y `jwks_uri`.
- **Desviaciones respecto a la arquitectura o al checklist:** el contenedor `mock-oidc` (`ghcr.io/soluto/oidc-server-mock:latest`) crasheaba en bucle bajo la imagen nativa `arm64` (`FileLoadException` de una dependencia .NET, aparenta ser un problema del build multi-arch de esa imagen). Se resolvió fijando `platform: linux/amd64` en el servicio, que corre vía emulación y arranca sano. Sin impacto en la arquitectura documentada — es un detalle de la imagen Docker usada solo en desarrollo local.
- **Bloqueos / pendientes para retomar:** Fase 0 completa. Siguiente paso: Fase 1 (paquete compartido `packages/bp-common`).

## 2026-07-28 (continuación — auditoría del checklist contra la arquitectura)

- **Ítem(s) del checklist:** N/A (revisión transversal, no un ítem de fase puntual)
- **Qué se hizo:** a pedido del usuario, se revisó `CHECKLIST.md` completo contra `docs/arquitectura-banca-digital-bp.md` para detectar decisiones documentadas que no tenían un ítem correspondiente. Se encontraron y corrigieron 8 vacíos:
  1. DPoP (decisión 3.6) no tenía ítem — agregado en Fase 1 (1.3).
  2. Autenticación reforzada / step-up para transferencias grandes (decisión 3.6) no tenía ítem — agregado en Fase 4 (4.4), incluyendo el caso de test correspondiente.
  3. Invalidación activa de caché (no solo TTL, decisión 3.8) no estaba explícita — aclarado en 3.3.
  4. `Idempotency-Key` generado del lado del cliente no estaba explícito — aclarado en 9.4.
  5. El `WORM Archiver` (S3 Object Lock) no tenía una estrategia clara para desarrollo local (LocalStack no soporta Object Lock real) — aclarado en 5.5.
  6. `Template Engine` del servicio de notificaciones (visible en el diagrama de componentes) no tenía ítem propio — agregado en Fase 6 (6.4).
  7. AWS Rekognition para revalidación de liveness en operaciones sensibles (diagrama de secuencia 8.2) no tenía ítem — agregado en Fase 8 (8.5).
  8. La Fase 13 (IaC) era muy genérica y no cubría explícitamente las consideraciones transversales de la sección 9 del documento (WAF/Shield, KMS/Secrets Manager, IAM Task Roles, Route 53 DR, CloudWatch/X-Ray/Synthetics, GuardDuty/Security Hub) — se expandió de 3 a 9 ítems, cada uno mapeado a su sección del documento.
  - También se agregó una nota en "Decisiones base de esta etapa" aclarando que Amazon API Gateway no se emula con una herramienta local aparte: su función de autorización JWT la cubre el middleware de `bp-common` (Fase 1) y su función de enrutamiento se resuelve con los puertos de `docker-compose.yml`; el recurso real de API Gateway se modela en la Fase 13.
- **Cómo se verificó:** relectura completa de las 14 decisiones (sección 3) y las 7 consideraciones transversales (sección 9) del documento de arquitectura, cruzadas ítem por ítem contra las 13 fases del checklist.
- **Desviaciones:** ninguna — son adiciones de alcance ya documentado en la arquitectura, no cambios de arquitectura.
- **Bloqueos / pendientes para retomar:** ninguno. Se confirmó además que este `WORKLOG.md` está al día (toda la Fase 0 tiene su entrada) y que `CLAUDE.md` sigue vigente; se le agregaron dos notas de convenciones (ver commit correspondiente): pin de `platform: linux/amd64` para imágenes Docker que fallen en Apple Silicon, y uso de `make up/down/logs` en vez de `docker compose` directo.

## 2026-07-28 (continuación — Fase 1: `packages/bp-common`)

- **Ítem(s) del checklist:** 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8
- **Qué se hizo:**
  - Se creó el paquete Composer `bp/common` (`packages/bp-common`) con PSR-4 `BP\Common\`.
  - `Auth\JwtValidator` + `Auth\DiscoveryJwksProvider`: validan un JWT (firma, `exp`, `iss`, `aud`) resolviendo el JWKS del emisor vía el documento de discovery OIDC estándar (`/.well-known/openid-configuration`), con cache en memoria de proceso (`ArrayJwksCache`, TTL configurable). El mismo código sirve para el mock-oidc local y para Auth0 real — solo cambia el emisor por `.env`.
  - `Auth\DpopValidator`: valida un DPoP proof (RFC 9449) — firma con la clave embebida en el header, `htm`/`htu` contra la request real, frescura de `iat`, anti-replay por `jti` (`InMemoryDpopReplayStore`), y opcionalmente el amarre `cnf.jkt` del access token vía JWK Thumbprint (RFC 7638). Activable por `.env` (`DPOP_ENFORCED`).
  - `Auth\JwtAuthMiddleware`: ata JWT + DPoP en un solo middleware de Laravel, devolviendo el envelope de error estándar en 401.
  - `Http\CorrelationIdMiddleware` y `Http\ApiResponse`: correlation-id por request (genera o propaga `X-Correlation-Id`) y envelope común `{data, meta}` / `{error: {code, message, errors}}`.
  - `Health\HealthCheckController` + `BpCommonServiceProvider`: cualquier servicio que instale el paquete obtiene `GET /health` automáticamente (auto-discovery de Laravel), sin declarar nada.
  - 20 tests (PHPUnit + Orchestra Testbench): unitarios de `JwtValidator` (firma válida/inválida, expiración, issuer/audience) y `DpopValidator` (proof válido, método/URL/iat/replay/cnf.jkt incorrectos), más tests de integración del middleware JWT, Correlation-Id y `/health` sobre una app Laravel real en memoria.
- **Cómo se verificó:**
  - `composer install` sin errores (ver desviación de seguridad abajo).
  - `./vendor/bin/phpunit` → 20 tests, 34 assertions, en verde, sin warnings.
  - Verificación end-to-end del ítem 1.8: se creó una app Laravel 12 desechable fuera del repo (`composer create-project laravel/laravel`), se agregó `packages/bp-common` como path-repository (`composer config repositories.bp-common path ...`), se corrió `composer require bp/common:@dev` (instaló por symlink), `php artisan route:list` mostró `GET /health` auto-registrada, y `php artisan serve` + `curl http://127.0.0.1:8321/health` devolvió `{"data":{"status":"ok",...}}`. La app desechable se borró después de verificar (no es parte del repo).
- **Desviaciones respecto a la arquitectura o al checklist:**
  - `firebase/php-jwt` en la franja `^6.10` tiene un advisory de seguridad activo (`PKSA-y2cr-5h3j-g3ys`); se subió la dependencia a `^7.0` (segura) en vez de ignorar el advisory. Sin impacto funcional.
  - El ítem 1.2 original planteaba un modo local con "clave RSA propia" separado del modo Auth0. Al implementarlo se detectó que el mock-oidc ya expone un JWKS real (visto en la Fase 0), así que se unificó a un solo flujo de validación por JWKS para ambos modos — más simple y más fiel a producción. Ya se había ajustado la redacción del ítem en el checklist antes de empezar a programar (ver auditoría previa).
- **Bloqueos / pendientes para retomar:** Fase 1 completa. Siguiente paso: Fase 2 (`services/svc-datos-basicos`).

## 2026-07-28 (continuación — README.md como fuente de verdad de instalación)

- **Ítem(s) del checklist:** N/A (proceso transversal, a pedido del usuario)
- **Qué se hizo:** el usuario notó que `README.md` seguía describiendo solo el documento de arquitectura y no tenía pasos de instalación/arranque para un entorno limpio (local o servidor), a pesar de que ya existen `docker-compose.yml`, `Makefile`, `.env.example` y `packages/bp-common` desde las Fases 0 y 1. Se reescribió `README.md` con: tabla de requisitos previos (Docker, PHP 8.3+, Composer, Node), pasos de puesta en marcha (`git clone` → `.env` → `make up` → `make ps` + chequeos manuales de cada servicio), instrucciones para correr los tests de `packages/bp-common`, y placeholders explícitos para las secciones de `services/*` y de los frontends que se irán llenando fase a fase. También se agregó `README.md` como cuarta "fuente de verdad" en `CLAUDE.md` (junto a la arquitectura, el checklist y el worklog) y una regla explícita: ninguna fase se da por cerrada sin actualizar la sección de instalación del README con lo que esa fase haya agregado.
- **Cómo se verificó:** revisión manual de que los pasos descritos en el README coinciden exactamente con los comandos ya verificados en las entradas de Fase 0 y Fase 1 de este mismo archivo.
- **Desviaciones:** ninguna.
- **Bloqueos / pendientes para retomar:** ninguno. A partir de ahora, cerrar la Fase 2 debe incluir agregar su propia sub-sección de instalación en el README (cómo levantar `services/svc-datos-basicos`).

## 2026-07-28 (continuación — Fase 2: `services/svc-datos-basicos`)

- **Ítem(s) del checklist:** 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7
- **Qué se hizo:**
  - Scaffold de una app Laravel 12 real (`composer create-project laravel/laravel`) + Laravel Octane, requiriendo `bp/common` vía path-repository (`../../packages/bp-common`).
  - `App\Contracts\CoreBancarioClient` / `ClienteComplementarioClient`: interfaces del patrón API Composition. Cada una con implementación `Fake*` (fixture en memoria con clientes `1001`/`1002`) e implementación `Http*` (real, vía Guzzle), seleccionables por `.env` (`CORE_BANCARIO_DRIVER`/`CLIENTE_COMPLEMENTARIO_DRIVER` = `fake`|`http`) desde `AppServiceProvider`.
  - `App\Services\ClienteCompositionService`: compone ambas fuentes en un solo contrato de salida.
  - `GET /clientes/{id}` (`ClienteController`) protegido con `BP\Common\Auth\JwtAuthMiddleware`; `GET /health` queda disponible automáticamente por `bp-common`.
  - `Dockerfile` (PHP 8.3 + Swoole vía PECL, instalado dentro del contenedor Linux — ver desviación abajo) pensado para construirse con el **contexto en la raíz del monorepo** (`docker build -f services/svc-datos-basicos/Dockerfile .`), porque necesita copiar `packages/bp-common`.
  - 10 tests (PHPUnit): fakes (2), `ClienteCompositionService` (1, con mocks), y feature del endpoint completo (3: sin token → 401, cliente existente → 200 compuesto, cliente inexistente → 404), más los 4 tests de ejemplo del skeleton de Laravel.
  - **Refactor retroactivo en `packages/bp-common`:** se movieron `RsaKeyPair` y `FakeJwksProvider` de `tests/Support/` (autoload-dev, solo visible dentro del propio paquete) a `src/Testing/` (autoload normal), para que los 7 servicios puedan reutilizar el mismo helper de JWT de prueba en sus propios tests sin duplicarlo. Se re-corrieron los 20 tests de `bp-common` tras el cambio — siguen en verde.
- **Cómo se verificó:**
  - `php artisan test` en el servicio → 10 passed, 23 assertions.
  - `php artisan route:list` → confirma `GET /clientes/{clienteId}` y `GET /health` registradas.
  - Prueba manual con `php artisan serve`: `/health` responde con el nombre del servicio; `/clientes/1001` sin token → 401.
  - **Build real de la imagen Docker** (`docker build -f services/svc-datos-basicos/Dockerfile -t bp/svc-datos-basicos:test .` desde la raíz) → exitoso tras dos ajustes (ver desviaciones). Se corrió el contenedor (`docker run ... -p 8342:8000`) y `curl http://127.0.0.1:8342/health` respondió correctamente sobre Swoole real (no RoadRunner), confirmando que la imagen productiva funciona de punta a punta.
- **Desviaciones respecto a la arquitectura o al checklist:**
  1. Swoole no se pudo compilar en el host (macOS, falta `pkg-config` del lado del sistema operativo) — no es un problema de la imagen Docker. Se usa **RoadRunner** para `php artisan octane:start` en el host durante desarrollo local (Octane lo soporta igual de bien; el swap es solo configuración, no código de aplicación), y **Swoole real dentro del Dockerfile** (que sí compila en el contenedor Linux), preservando la decisión de arquitectura documentada para lo que realmente se despliega.
  2. El primer intento de build de la imagen falló por faltar `libbrotli-dev` (dependencia de compilación de Swoole) — se agregó al `apt-get install` del Dockerfile.
  3. El segundo intento falló porque `composer.lock` se había generado con el PHP del host (8.5.8) y resolvió paquetes Symfony 8.x que exigen PHP ≥8.4.1, incompatibles con el PHP 8.3 de la imagen. Se fijó `"config.platform.php": "8.3.99"` en `composer.json` y se regeneró el lock — asegura que cualquiera que corra `composer install/update` en este servicio (sin importar su PHP local) resuelva versiones compatibles con el mínimo documentado (PHP 8.3+), evitando este mismo problema a futuro en los otros 6 servicios.
- **Bloqueos / pendientes para retomar:** Fase 2 completa. Siguiente paso: Fase 3 (`services/svc-movimientos`, patrón Cache-Aside).

## 2026-07-28 (continuación — Fase 3: `services/svc-movimientos`)

- **Ítem(s) del checklist:** 1.9 (retroactivo en bp-common), 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7
- **Qué se hizo:**
  - **Extensión de `packages/bp-common`** (ítem 1.9, agregado porque es código genuinamente compartido, no específico de este servicio): `Events\EventPublisherInterface` + `EventBridgeEventPublisher` (publica en Amazon EventBridge; LocalStack en local y AWS real en producción son solo un cambio de endpoint/config, mismo patrón que la validación JWT vía JWKS), y clientes compartidos `Aws\DynamoDb\DynamoDbClient` + `Aws\DynamoDb\Marshaler` + `Aws\EventBridge\EventBridgeClient` registrados como singletons en `BpCommonServiceProvider`, configurados por `bp-common.events.*` / `bp-common.dynamodb.*`. 3 tests nuevos (con Mockery, mockeando el cliente de EventBridge) — bp-common pasó de 20 a 23 tests, todos en verde.
  - Scaffold de `services/svc-movimientos` (Laravel 12 + Octane, mismo patrón que `svc-datos-basicos`: `platform.php=8.3.99`, RoadRunner en local, Swoole en el Dockerfile).
  - `App\Repositories\DynamoDbMovimientosRepository`: persiste en DynamoDB con clave `cuenta_id` (partition) + `sort_key` = `fecha_iso8601#uuid` (range), para poder listar en orden cronológico descendente sin colisiones.
  - `App\Repositories\CachedMovimientosRepository`: decorador Cache-Aside sobre Redis usando **tags** (`Cache::tags(["movimientos:{cuentaId}"])`) — en lectura sirve de caché si existe; en `registrar()`, hace `flush()` del tag de esa cuenta (invalidación activa, decisión 3.8, no depende solo del TTL).
  - `GET /cuentas/{id}/movimientos` y `POST /cuentas/{id}/movimientos` (este último no estaba en el checklist original — se agregó porque sin un punto de escritura no había forma de ejercer ni probar la invalidación de caché ni la publicación del evento; ver nota en `CHECKLIST.md` 3.4). El `POST` publica `MovementRegistered` vía el `EventPublisherInterface` de `bp-common`.
  - Dos comandos artisan de provisión local idempotentes: `movimientos:setup-table` (crea la tabla DynamoDB en LocalStack si no existe) y `events:setup-bus` (crea el bus `bp-domain-events` en LocalStack si no existe).
  - 9 tests, corriendo contra la infraestructura **real** de `docker-compose` (Redis y LocalStack), no contra mocks del SDK de AWS — consistente con la convención ya escrita en `CLAUDE.md`.
- **Cómo se verificó:**
  - `php artisan test` → 9 passed, 16 assertions. Incluye el test explícito del criterio de aceptación de la fase: `test_la_segunda_lectura_no_vuelve_a_pegarle_al_repositorio_interno` (Cache-Aside) y `test_registrar_invalida_la_cache_de_la_cuenta` (invalidación activa).
  - Verificación manual: `php artisan movimientos:setup-table` y `events:setup-bus` contra el LocalStack real, confirmados con `awslocal dynamodb describe-table` y `awslocal events list-event-buses` dentro del contenedor `bp-localstack`.
  - Build real de la imagen Docker + `docker run` conectado a la red de `docker-compose` (`test_default`), apuntando a `bp-redis`/`bp-localstack`/`bp-mock-oidc` por nombre de contenedor — `/health` respondió correctamente y `/cuentas/1001/movimientos` sin token devolvió 401, confirmando que la imagen productiva (Swoole) también resuelve bien la red interna de Docker, no solo `localhost`.
- **Desviaciones respecto a la arquitectura o al checklist:**
  1. Se agregó `POST /cuentas/{id}/movimientos` (no estaba en el checklist original) — ver justificación arriba y en `CHECKLIST.md`.
  2. El `Marshaler` de DynamoDB deserializa un `Number` sin parte decimal como `int` (ej. `30` en vez de `30.0`), lo que rompía la consistencia del contrato de salida (`monto` a veces `int`, a veces `float`, según el valor guardado). Se corrigió forzando `(float)` en `DynamoDbMovimientosRepository::unmarshalMovimiento()` — no es una desviación de arquitectura, sino un detalle real de la librería que hay que tener en cuenta también en el futuro Servicio de Auditoría (Fase 5), que también usa DynamoDB.
  3. `REDIS_CLIENT` se fijó en `predis` (cliente 100% PHP) en vez de `phpredis` (extensión nativa) porque esta última no está instalada en el host y no es necesaria — Predis es igual de válido y es lo que recomienda Laravel cuando no se quiere depender de una extensión de PHP.
- **Bloqueos / pendientes para retomar:** Fase 3 completa. Siguiente paso: Fase 4 (`services/svc-transferencias`, patrón Saga + Circuit Breaker + Idempotency Key — la fase más compleja hasta ahora).

## 2026-07-28 (continuación — Fase 4: `services/svc-transferencias`)

- **Ítem(s) del checklist:** 4.1 a 4.10
- **Qué se hizo:**
  - Scaffold de `services/svc-transferencias` (mismo patrón: Laravel 12 + Octane/RoadRunner en local, Swoole en Dockerfile, `platform.php=8.3.99`). Primera vez que un servicio usa MySQL real: se le creó su propia base de datos (`svc_transferencias`) dentro del mismo contenedor `bp-mysql`, y se agregó `infra/mysql-init/01-databases.sql` montado en `docker-compose.yml` para que un entorno limpio la cree sola la primera vez.
  - Migraciones `cuentas` (`cuenta_id`, `saldo`) y `transferencias` (`transferencia_id` UUID como PK, `idempotency_key` único, `cuenta_origen`, `cuenta_destino`, `monto`, `estado`, `motivo_falla`). Se simplificó el plan original: no se creó una tabla `idempotency_keys` aparte (la idempotencia la resuelve Redis, y `idempotency_key` único en `transferencias` es la red de seguridad a nivel de base de datos) — ver nota en `CHECKLIST.md` 4.2.
  - `App\Http\Middleware\IdempotencyMiddleware`: exige el header `Idempotency-Key`, sirve la respuesta cacheada (status + body) si la clave ya se proceso.
  - `App\Http\Middleware\StepUpAuthMiddleware`: para `monto > STEP_UP_THRESHOLD`, exige `acr=step-up` o `amr` con `mfa` en el JWT; si no, `403 step_up_required`. Se ubicó **antes** del middleware de idempotencia en la cadena (`JwtAuth -> StepUp -> Idempotency -> Controller`) a propósito: un rechazo por falta de step-up no debe quedar cacheado bajo la Idempotency-Key, porque el cliente puede reintentar la misma operación con un JWT distinto tras reautenticarse.
  - `App\Services\TransferOrchestrator`: Saga — debita la cuenta origen dentro de una transacción MySQL (`lockForUpdate` para evitar condiciones de carrera sobre el saldo), crea la `Transferencia` en estado `pendiente`, llama al `InterbankClient`; si tiene éxito pasa a `completada` y publica `TransferCompleted`; si falla, compensa (revierte el débito en otra transacción) y publica `TransferFailed`.
  - `App\Clients\CircuitBreakerInterbankClient`: decorador con retry (backoff exponencial + jitter) y apertura de circuito tras un umbral de fallas configurable; el estado del circuito vive en Redis (no en memoria de proceso) para ser consistente entre workers de Octane e instancias.
  - `App\Clients\FakeInterbankClient`: determinista para poder probar la compensación — cualquier `cuenta_destino` que empiece con `FALLA-` es rechazada; el resto se confirma. `HttpInterbankClient` real también implementado, intercambiable por `INTERBANK_DRIVER=http`.
  - `POST /transfers`, con la cadena de middlewares ya descrita.
  - 11 tests (contra MySQL/Redis/LocalStack reales de `docker-compose`, cada uno envuelto en `DatabaseTransactions` para revertirse solo): camino feliz, rechazo sin `Idempotency-Key`, idempotencia real (segunda llamada no vuelve a debitar), compensación ante rechazo del banco destino, saldo insuficiente, rechazo y aceptación por step-up, y apertura real del circuit breaker (con conteo de llamadas al cliente interno).
- **Cómo se verificó:**
  - `php artisan test` → 11 passed, 26 assertions.
  - Build real de la imagen Docker + `docker run` conectado a la red de `docker-compose`, apuntando a `bp-mysql`/`bp-redis`/`bp-localstack`/`bp-mock-oidc` por nombre de contenedor — `/health` y el rechazo 401 sin token confirmados.
- **Desviaciones respecto a la arquitectura o al checklist:**
  1. Se eliminó la tabla `idempotency_keys` prevista originalmente (ver nota en `CHECKLIST.md` 4.2) — Redis ya resuelve la idempotencia y una columna única en `transferencias` alcanza como red de seguridad; una tabla aparte no agregaba nada.
  2. **Bug real encontrado y corregido durante el desarrollo:** el primer test del circuit breaker abría el circuito escribiendo su estado en el Redis real (a propósito, para ser consistente entre workers) pero no lo limpiaba después — el siguiente test (`TransferControllerTest`, que usa el mismo `InterbankClient` del contenedor) heredaba el circuito abierto y fallaba con `estado=fallida` en vez de `completada`. Se corrigió agregando `tearDown()` en `CircuitBreakerInterbankClientTest` que limpia las claves de Redis del circuito. Es un recordatorio para las próximas fases: cualquier test que toque estado compartido en Redis (no solo MySQL, que ya se revierte solo con `DatabaseTransactions`) tiene que limpiarlo explícitamente en `tearDown`.
- **Bloqueos / pendientes para retomar:** Fase 4 completa. Siguiente paso: Fase 5 (`services/svc-auditoria`, consumidor de eventos vía SQS + DynamoDB + WORM archiver stand-in en S3 de LocalStack).

## 2026-07-28 (continuación — antes de la Fase 5: puerto de MySQL + actor en eventos)

- **Ítem(s) del checklist:** N/A (correcciones puntuales previas a la Fase 5, a pedido del usuario y por un vacío detectado)
- **Qué se hizo:**
  - **Puerto de MySQL:** el usuario tiene otro proyecto Docker usando el `3306` del host, así que se remapeó el MySQL local a `3308:3306` (el puerto interno del contenedor sigue en `3306`, solo cambia el publicado en el host). Se actualizó `docker-compose.yml`, `.env`/`.env.example` de la raíz, y `.env`/`.env.example` de `svc-transferencias` (el único servicio que hoy conecta a MySQL desde el host). Se recreó el contenedor (`docker compose up -d mysql`) y se confirmó que los datos ya existentes sobrevivieron (mismo volumen).
  - **Vacío detectado antes de construir Auditoría:** los eventos que publican `svc-movimientos` (`MovementRegistered`) y `svc-transferencias` (`TransferCompleted`/`TransferFailed`) no incluían quién hizo la acción — sin eso, un registro de auditoría no puede atribuirse a un cliente, que es el propósito completo de la Fase 5. Se agregó `BP\Common\Auth\JwtClaims::actor(Request $request)` a `bp-common` (lee el claim `sub` del JWT ya validado por `JwtAuthMiddleware`, con `'system'` como default) y se usó en ambos servicios para incluir `actor` en el payload del evento publicado.
  - Limpieza menor: `infra/.gitkeep` y `services/.gitkeep` llevaban varias fases borrados en disco pero seguían trackeados en git porque nunca se incluyeron explícitamente en un `git add` (cada commit agregó la carpeta del servicio nuevo puntualmente, no la carpeta padre) — se sacaron del índice con `git rm --cached`.
- **Cómo se verificó:**
  - `docker compose ps` confirma `bp-mysql` publicando en `0.0.0.0:3308->3306`; `php artisan migrate:status` en `svc-transferencias` contra el nuevo puerto muestra las migraciones ya corridas (mismo volumen, no se perdió nada).
  - Se corrió la suite completa de los 3 servicios backend tras el reinicio de infraestructura y el cambio de puerto: `svc-datos-basicos` (10 pass), `svc-movimientos` (9 pass), `svc-transferencias` (11 pass) — los 30 tests siguen en verde.
  - `bp-common`: se agregó `JwtClaimsTest` (2 tests) — pasó de 23 a 25 tests, todos en verde.
- **Desviaciones:** ninguna respecto a la arquitectura; es una corrección de un vacío de implementación (actor faltante) y un ajuste de entorno local (puerto).
- **Nota operativa encontrada:** al reiniciar el stack de `docker-compose`, la tabla DynamoDB `movimientos` sí persistió (volumen `bp_localstack_data` con `PERSISTENCE=1`), pero el bus custom de EventBridge (`bp-domain-events`) **no** sobrevivió el reinicio — hubo que volver a correr `php artisan events:setup-bus`. Vale la pena tenerlo presente para la Fase 5 (el consumidor de Auditoría depende de que ese bus y su regla/cola SQS existan) y quizás para un futuro `make up` que corra estos comandos de provisión automáticamente.
- **Bloqueos / pendientes para retomar:** ninguno. Siguiente paso: Fase 5 (`services/svc-auditoria`).

## 2026-07-28 (continuación — Fase 5: `services/svc-auditoria`)

- **Ítem(s) del checklist:** 5.1 a 5.9 (5.8 y 5.9 agregados durante la fase)
- **Qué se hizo:**
  - Scaffold de `services/svc-auditoria` **sin** Octane ni Horizon (ver ajuste documentado en `CHECKLIST.md` 5.1): es un worker puro sin HTTP, y su fuente de eventos es SQS con el sobre de EventBridge, no la cola interna de Laravel que Horizon supervisa. El "worker" es el comando `audit:consume` (long-polling directo contra el SDK de AWS), que es el proceso principal del `Dockerfile` (sin Swoole).
  - `App\Services\AuditEventProcessor`: núcleo testeable que traduce un evento de EventBridge (`detail-type` + `detail`) en un registro de auditoría — separado del comando de consumo a propósito, para poder probarlo sin una cola real.
  - `App\Repositories\DynamoDbAuditRepository`: persiste `actor` (partition key) + `sort_key` (`timestamp#audit_id`, range) + `accion` + `detalle` + un **hash SHA-256** del contenido (evidencia de integridad — no de inmutabilidad, eso lo da Object Lock en AWS real).
  - `App\Services\WormArchiver`: copia cada registro a un bucket S3 de LocalStack. El código deja explícito en un comentario que esto es solo un *stand-in* local — la inmutabilidad real requiere Object Lock modo Compliance en AWS (LocalStack no lo soporta), que se modela en la Fase 13.
  - `App\Console\Commands\SetupAuditInfrastructure` (ítem 5.8, no estaba en el checklist original pero es indispensable para que el consumidor tenga algo que consumir): provisión idempotente de la cola SQS `audit-events-queue` + su DLQ `audit-events-dlq` (con `RedrivePolicy`, `maxReceiveCount=3`), una regla de EventBridge (`audit-all-domain-events`) que rutea **todo** el bus `bp-domain-events` (patrón `source` con prefijo `bp.`) hacia esa cola, la política de la cola que le da permiso a `events.amazonaws.com` para enviarle mensajes, la tabla DynamoDB `auditoria`, y el bucket S3 `bp-auditoria-worm`.
  - Se agregó `Aws\Sqs\SqsClient` como singleton compartido en `packages/bp-common` (ítem 5.9, retroactivo) — Notificaciones (Fase 6) también va a ser un consumidor de SQS y no tiene sentido que cada servicio configure su propio cliente.
  - 9 tests: 4 unitarios de `AuditEventProcessor` (con mocks), 2 de integración de `DynamoDbAuditRepository`/`WormArchiver` contra LocalStack real, y un test end-to-end completo que publica un evento real en EventBridge, corre `audit:consume --once`, y verifica el registro resultante en DynamoDB por `Query` (no `Scan`, sobre el `actor` único generado para el test).
- **Cómo se verificó:**
  - `php artisan test` → 9 passed, 25 assertions.
  - Verificación manual de la cadena completa vía CLI de LocalStack (`awslocal events put-events` → `awslocal sqs receive-message`) para descartar que el problema fuera la regla/target antes de sospechar del código PHP (ver desviación/hallazgo abajo).
  - Build real de la imagen Docker + `docker run` **en segundo plano** conectado a la red de `docker-compose`: se publicó un evento desde `svc-movimientos` (`tinker`) y el contenedor de auditoría lo procesó solo (log `Auditado: MovementRegistered (...)`), confirmando el worker de punta a punta sin intervención manual.
- **Desviaciones respecto a la arquitectura o al checklist:**
  1. Sin Octane/Horizon — ver justificación arriba y en `CHECKLIST.md` 5.1.
  2. Se agregaron los ítems 5.8 (provisión de infraestructura) y 5.9 (SqsClient en bp-common), necesarios para que el resto de la fase funcionara, no previstos en el plan original.
  3. **Falsa alarma durante el desarrollo, documentada para no repetirla:** el primer intento de `audit:consume --once` no imprimió nada, lo que se interpretó erróneamente como "no llegó ningún mensaje". En realidad el comando solo logueaba en caso de *error*, no de éxito — sí había procesado el mensaje correctamente (confirmado revisando DynamoDB/S3 directamente). Se corrigió agregando un log de éxito (`$this->info(...)`) para que el comando nunca quede en silencio ambiguo.
- **Nota operativa (ya adelantada en la entrada anterior, confirmada aquí):** el bus/regla de EventBridge no sobrevive un reinicio de LocalStack; queda documentado en el README de este servicio.
- **Bloqueos / pendientes para retomar:** Fase 5 completa. Siguiente paso: Fase 6 (`services/svc-notificaciones`, consumidor de SQS igual que Auditoría, con `ChannelRouter` + `TemplateEngine` + adaptadores Pinpoint/SES).
