# Dos alias de provider AWS -- decision 9.3 (DR activo-pasivo multi-region):
# "primary" es la region activa (recibe trafico), "secondary" es la region
# pasiva de DR. Todos los recursos replicados (Aurora Global Database,
# DynamoDB Global Tables, S3 CRR) necesitan un provider por region porque
# el proveedor de AWS de Terraform es regional.
provider "aws" {
  alias  = "primary"
  region = var.primary_region

  default_tags {
    tags = local.common_tags
  }
}

provider "aws" {
  alias  = "secondary"
  region = var.secondary_region

  default_tags {
    tags = local.common_tags
  }
}

# El WAF de scope CLOUDFRONT (modulo edge) exige declararse en us-east-1 sin
# importar la region primaria real -- requisito de AWS, no de esta
# arquitectura. Un provider aliased NO puede declararse dentro de un modulo
# hijo (solo el root module puede configurar providers), por eso vive aca y
# se pasa explicito a "edge" via el bloque `providers` de ese module block.
provider "aws" {
  alias  = "us_east_1"
  region = "us-east-1"

  default_tags {
    tags = local.common_tags
  }
}

# Auth0 real como Authorization Server (decision 3.5) -- reemplaza al
# mock-oidc usado en local/CI (Fases 0-13). Las credenciales de gestion
# (dominio + client_id/secret de una Machine-to-Machine application con
# scope de Management API) se leen de variables, nunca hardcodeadas.
provider "auth0" {
  domain        = var.auth0_domain
  client_id     = var.auth0_management_client_id
  client_secret = var.auth0_management_client_secret
}

locals {
  common_tags = {
    Project     = "banca-digital-bp"
    Environment = var.environment
    ManagedBy   = "terraform"
  }
}
