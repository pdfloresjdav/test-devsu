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
