# Reglas de trabajo para Claude en este repositorio

Este archivo gobierna cómo debo (Claude) trabajar en este proyecto. Se aplica a toda sesión futura, no solo a la actual.

## Fuente de verdad

1. **`docs/arquitectura-banca-digital-bp.md`** es la fuente de verdad de las decisiones de arquitectura (stack, patrones, servicios AWS). Ninguna decisión de esa lista se cambia "sobre la marcha" al programar: si el código exige desviarse de una decisión, se detiene el trabajo, se explica el conflicto al usuario y se actualiza el documento primero.
2. **`CHECKLIST.md`** es la fuente de verdad de qué falta por construir y en qué orden.
3. **`WORKLOG.md`** es la fuente de verdad de qué se hizo realmente y cómo se verificó.

Antes de tocar código en una sesión nueva: leer `CHECKLIST.md` (qué sigue) y las últimas 2-3 entradas de `WORKLOG.md` (qué se hizo y qué quedó pendiente).

## Disciplina de avance (regla central)

- Se trabaja **un ítem del checklist a la vez**, en orden, respetando las dependencias entre fases.
- Un ítem se marca `[x]` **solo** cuando está implementado y verificado (build/lint/test corridos y en verde, no solo "el código parece correcto"). Si algo no se puede verificar automáticamente, se dice explícitamente cómo se verificó manualmente.
- No se avanza al siguiente ítem con el anterior a medias. Si aparece un bloqueo, se marca `[!]` en el checklist, se registra el motivo en `WORKLOG.md`, y se pregunta al usuario en vez de improvisar una solución que se desvíe de la arquitectura.
- Si durante el desarrollo aparece trabajo no previsto (una dependencia faltante, un ajuste de diseño), se agrega como ítem nuevo al checklist en vez de hacerlo "silenciosamente" y seguir de largo.
- Al cerrar un ítem (o un grupo pequeño de ítems relacionados de la misma fase), se agrega la entrada correspondiente en `WORKLOG.md` **antes** de pasar al siguiente ítem.
- No completar fases enteras sin pausar: al terminar una fase completa del checklist, resumir al usuario lo hecho y esperar confirmación antes de iniciar la siguiente fase.

## Convenciones técnicas del proyecto

**Backend (Laravel, PHP 8.3+):**
- Un microservicio = una app Laravel independiente bajo `services/<nombre>`, con su propio `composer.json`, `Dockerfile` y `.env.example`.
- Código compartido entre servicios va en `packages/bp-common` (paquete Composer local vía path-repository) — nunca se copia/pega middleware o utilidades entre servicios.
- Estilo de código: PSR-12, aplicado con Laravel Pint antes de dar por cerrado cualquier ítem de backend.
- Cómputo pensado para Octane (Swoole): evitar estado mutable en propiedades de clases que Octane mantiene vivas entre requests (singletons, estáticos) salvo que sea intencional.
- Cualquier integración externa (DynamoDB, EventBridge/SQS, S3, Auth0, KYC, red interbancaria, Pinpoint/SES) se escribe contra una **interfaz** (Repository/Adapter). Cada interfaz tiene como mínimo un driver `local` (mock/fake/LocalStack) y un driver real, seleccionados por variable de entorno — nunca un `if (app()->environment('local'))` disperso en la lógica de negocio.
- MySQL es real desde el día 1 (no se mockea): es el mismo protocolo que Aurora MySQL en producción, así que promoverlo a AWS es solo cambiar host/credenciales.
- Todo servicio expone `GET /health` (del paquete `bp-common`) y valida JWT con el middleware compartido.
- Cada servicio commitea su `.env.example`; el `.env` real siempre va en `.gitignore`.

**Entorno local (Docker):**
- El entorno de infraestructura se maneja con `make up` / `make down` / `make logs` / `make ps` (nunca `docker compose` directo salvo para depurar), para que el flujo sea el mismo sin importar cuántos servicios se agreguen.
- Si una imagen Docker de terceros crashea en bucle en esta máquina (Apple Silicon) con errores que huelen a incompatibilidad nativa (p. ej. `FileLoadException` de .NET, "exec format error"), probar primero fijando `platform: linux/amd64` en el servicio antes de buscar otra imagen — resolvió el caso de `oidc-server-mock`.

**Frontend (React + TypeScript / React Native + TypeScript):**
- ESLint + Prettier obligatorios, sin warnings al cerrar un ítem.
- Lógica de negocio compartible entre SPA y móvil (validaciones, formateo, llamadas a API) se aísla en módulos reutilizables, no se duplica entre ambos proyectos.
- Nunca se hardcodea la URL de un BFF ni el modo de OAuth: todo por variables de entorno (`.env` / `.env.local` según el bundler), con su `.env.example`.

**Pruebas:**
- Ningún ítem de checklist que agregue una regla de negocio (idempotencia, compensación de Saga, invalidación de caché, apertura de Circuit Breaker, etc.) se marca `[x]` sin al menos un test automatizado que la ejerza.
- Los tests corren contra la infraestructura local (`docker compose`), nunca contra servicios reales de AWS o proveedores externos.

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
