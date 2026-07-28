# Arquitectura — Sistema de Banca Digital BP

Diseño de arquitectura de solución (modelo C4) para el sistema de banca por internet de la entidad **BP**: consulta de histórico de movimientos, transferencias y pagos entre cuentas propias e interbancarias, con onboarding móvil biométrico y autenticación OAuth 2.0.

## Contenido del repositorio

```
.
├── README.md
├── .gitignore
├── docs/
│   ├── arquitectura-banca-digital-bp.md   # Documento completo: decisiones, diagramas C4 e imágenes embebidas
│   └── arquitectura-banca-digital-bp.pdf  # Entregable en PDF (mismo contenido, listo para imprimir/subir)
└── diagrams/
    ├── 01-contexto.mmd                                  # C4 Nivel 1 (contexto)
    ├── 02a-contenedores-frontend-edge.mmd                # C4 Nivel 2 (1/3): frontend, edge y autenticación
    ├── 02b-contenedores-microservicios.mmd               # C4 Nivel 2 (2/3): microservicios de negocio
    ├── 02c-contenedores-datos-mensajeria.mmd             # C4 Nivel 2 (3/3): persistencia y mensajería
    ├── 03a-componentes-transferencias-recepcion.mmd      # C4 Nivel 3 (1/2): Transferencias - recepción
    ├── 03b-componentes-transferencias-orquestacion.mmd   # C4 Nivel 3 (2/2): Transferencias - orquestación
    ├── 04-componentes-auditoria-notificaciones.mmd       # C4 Nivel 3: Auditoría/Notificaciones
    ├── 05-despliegue.mmd                                 # C4 Despliegue (infraestructura AWS, Multi-AZ + DR)
    ├── 06-secuencia-transferencia.mmd                    # C4 Dinámico (secuencia: transferencia interbancaria)
    ├── 07-secuencia-onboarding.mmd                       # C4 Dinámico (secuencia: onboarding + login recurrente)
    └── png/                                              # Todos los diagramas anteriores ya renderizados a imagen
```

El documento principal está en [`docs/arquitectura-banca-digital-bp.md`](docs/arquitectura-banca-digital-bp.md), con los diagramas embebidos como **imágenes PNG** (no solo código), y su versión exportada en [`docs/arquitectura-banca-digital-bp.pdf`](docs/arquitectura-banca-digital-bp.pdf). Los archivos `.mmd` en `diagrams/` son la fuente editable de cada diagrama (sintaxis [Mermaid](https://mermaid.js.org/), compatible con el estándar C4); `diagrams/png/` contiene el resultado ya renderizado que se usa en el documento y en el PDF.

El diagrama de Contenedores y el de Componentes de Transferencias se dividieron en varias vistas complementarias (en vez de un único diagrama saturado) para que cada una se pueda leer con claridad — práctica habitual en C4 cuando un diagrama crece demasiado.

## Stack propuesto

- **Frontend:** React + TypeScript (SPA) y React Native + TypeScript (app móvil).
- **Backend:** Laravel (PHP) sobre Amazon ECS Fargate, con Laravel Octane (Swoole) para baja latencia y Laravel Horizon para procesamiento asíncrono.
- **Identidad:** Auth0 / Okta CIC (OAuth 2.0 + OIDC, flujo Authorization Code + PKCE).
- **Biometría/KYC:** Onfido / iProov para onboarding, AWS Rekognition para revalidaciones ligeras, WebAuthn/FIDO2 para login recurrente.
- **Datos:** Amazon Aurora MySQL (transaccional), Amazon DynamoDB (movimientos y auditoría), Amazon ElastiCache Redis (caché).
- **Mensajería:** Amazon EventBridge + SQS.
- **Notificaciones:** Amazon Pinpoint (push/SMS) + Amazon SES (email).

## Cómo se regenera el PDF

Los diagramas se renderizan con `@mermaid-js/mermaid-cli` y el documento se convierte a PDF con Chrome headless a partir de una versión HTML intermedia. Ambos pasos son reproducibles localmente sin instalar nada de forma permanente (se usa `npx` con un directorio de caché temporal). Si se edita algún `.mmd`, hay que volver a renderizar su PNG en `diagrams/png/` y regenerar el PDF.

## Estado

Documento de arquitectura v1.1 — diagramas C4 completos (contexto, contenedores, componentes), diagrama de despliegue y diagramas dinámicos de los flujos de transferencia y onboarding.
