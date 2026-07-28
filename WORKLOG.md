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
