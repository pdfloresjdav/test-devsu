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
