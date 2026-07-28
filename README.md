# Arquitectura — Sistema de Banca Digital BP

Diseño de arquitectura de solución (modelo C4) para el sistema de banca por internet de la entidad **BP**: consulta de histórico de movimientos, transferencias y pagos entre cuentas propias e interbancarias, con onboarding móvil biométrico y autenticación OAuth 2.0.

## Contenido del repositorio

```
.
├── README.md
├── .gitignore
├── docs/
│   └── arquitectura-banca-digital-bp.md   # Documento completo: decisiones, diagramas C4 y consideraciones transversales
└── diagrams/
    ├── 01-contexto.mmd                              # C4 Nivel 1 (contexto)
    ├── 02-contenedores.mmd                          # C4 Nivel 2 (contenedores)
    ├── 03-componentes-transferencias.mmd            # C4 Nivel 3 (componentes: Transferencias)
    ├── 04-componentes-auditoria-notificaciones.mmd  # C4 Nivel 3 (componentes: Auditoría/Notificaciones)
    ├── 05-despliegue.mmd                            # C4 Despliegue (infraestructura AWS, Multi-AZ + DR)
    ├── 06-secuencia-transferencia.mmd               # C4 Dinámico (secuencia: transferencia interbancaria)
    └── 07-secuencia-onboarding.mmd                  # C4 Dinámico (secuencia: onboarding + login recurrente)
```

El documento principal está en [`docs/arquitectura-banca-digital-bp.md`](docs/arquitectura-banca-digital-bp.md) e incluye los diagramas embebidos como bloques Mermaid (se renderizan automáticamente en GitHub, GitLab, VS Code y Obsidian). Los archivos `.mmd` en `diagrams/` son la misma fuente de cada diagrama, aislada para poder editarla o renderizarla por separado.

## Stack propuesto

- **Frontend:** React + TypeScript (SPA) y React Native + TypeScript (app móvil).
- **Backend:** Laravel (PHP) sobre Amazon ECS Fargate, con Laravel Octane (Swoole) para baja latencia y Laravel Horizon para procesamiento asíncrono.
- **Identidad:** Auth0 / Okta CIC (OAuth 2.0 + OIDC, flujo Authorization Code + PKCE).
- **Biometría/KYC:** Onfido / iProov para onboarding, AWS Rekognition para revalidaciones ligeras, WebAuthn/FIDO2 para login recurrente.
- **Datos:** Amazon Aurora MySQL (transaccional), Amazon DynamoDB (movimientos y auditoría), Amazon ElastiCache Redis (caché).
- **Mensajería:** Amazon EventBridge + SQS.
- **Notificaciones:** Amazon Pinpoint (push/SMS) + Amazon SES (email).

## Cómo exportar el documento a PDF

El documento es Markdown puro con diagramas Mermaid embebidos. Opciones recomendadas para exportar a PDF conservando los diagramas renderizados:

- **VS Code:** extensión "Markdown Preview Enhanced" → *Export → PDF* (renderiza Mermaid de forma nativa).
- **Pandoc:** `pandoc docs/arquitectura-banca-digital-bp.md -o arquitectura-BP.pdf --pdf-engine=xelatex --filter mermaid-filter` (requiere `mermaid-filter` instalado vía npm).
- **GitHub:** al subir el repositorio, el documento se renderiza directamente en la interfaz web con los diagramas incluidos.

## Estado

Documento de arquitectura v1.0 — pendiente de revisión.
