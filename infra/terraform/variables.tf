variable "environment" {
  description = "Nombre del ambiente (dev, staging, prod). Determina sizing y flags de HA en varios modulos."
  type        = string

  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "environment debe ser dev, staging o prod."
  }
}

variable "project_name" {
  description = "Prefijo corto usado en nombres de recursos y tags."
  type        = string
  default     = "bp"
}

variable "primary_region" {
  description = "Region activa de AWS (decision 9.3: activo-pasivo multi-region)."
  type        = string
  default     = "us-east-1"
}

variable "secondary_region" {
  description = "Region pasiva de DR (decision 9.3)."
  type        = string
  default     = "us-west-2"
}

variable "vpc_cidr" {
  description = "Bloque CIDR de la VPC primaria."
  type        = string
  default     = "10.0.0.0/16"
}

variable "dr_vpc_cidr" {
  description = "Bloque CIDR de la VPC en la region DR -- distinto del primario para poder correr VPC peering/Transit Gateway sin colisiones si hiciera falta."
  type        = string
  default     = "10.1.0.0/16"
}

variable "availability_zone_count" {
  description = "Cantidad de AZs a usar por region (decision 9.2: Multi-AZ como minimo)."
  type        = number
  default     = 2

  validation {
    condition     = var.availability_zone_count >= 2
    error_message = "Se requieren al menos 2 AZs para Multi-AZ real (seccion 9.2)."
  }
}

variable "services" {
  description = <<-EOT
    Los 7 microservicios backend (services/*), uno por carpeta del monorepo.
    Cada entrada define su puerto interno, si expone HTTP publico (via BFF/API
    Gateway) o es un worker puro (Auditoria/Notificaciones, decision 3.4), y
    su ruta de composer/Dockerfile para el pipeline de build (item 14.9).
  EOT
  type = map(object({
    exposes_http = bool
    port         = number
    is_worker    = bool
    cpu          = number # unidades de CPU de Fargate (1024 = 1 vCPU)
    memory       = number # MB
  }))
  default = {
    svc-customer-data = { exposes_http = true, port = 8000, is_worker = false, cpu = 512, memory = 1024 }
    svc-movements     = { exposes_http = true, port = 8000, is_worker = false, cpu = 512, memory = 1024 }
    svc-transfers     = { exposes_http = true, port = 8000, is_worker = false, cpu = 512, memory = 1024 }
    svc-audit         = { exposes_http = false, port = 8000, is_worker = true, cpu = 256, memory = 512 }
    svc-notifications = { exposes_http = false, port = 8000, is_worker = true, cpu = 256, memory = 512 }
    bff-web           = { exposes_http = true, port = 8000, is_worker = false, cpu = 512, memory = 1024 }
    bff-mobile        = { exposes_http = true, port = 8000, is_worker = false, cpu = 512, memory = 1024 }
  }
}

variable "auth0_domain" {
  description = "Dominio del tenant de Auth0 (Management API) -- item 14.8."
  type        = string
  default     = ""
}

variable "auth0_management_client_id" {
  description = "Client ID de la aplicacion Machine-to-Machine de Auth0 usada por Terraform para gestionar el tenant."
  type        = string
  default     = ""
  sensitive   = true
}

variable "auth0_management_client_secret" {
  description = "Client secret de la aplicacion M2M de Auth0."
  type        = string
  default     = ""
  sensitive   = true
}

variable "alert_email" {
  description = "Direccion que recibe las alarmas de CloudWatch (seccion 9.5)."
  type        = string
  default     = ""
}

variable "domain_name" {
  description = "Dominio publico de BP para Route 53 (item 14.6). Vacio = no se crea zona ni registros DNS todavia."
  type        = string
  default     = ""
}

variable "ses_from_domain" {
  type    = string
  default = "bp.test"
}

variable "web_redirect_uris" {
  type    = list(string)
  default = ["http://localhost:5173/callback"]
}

variable "mobile_redirect_uris" {
  type    = list(string)
  default = ["http://localhost:19006/callback"]
}

variable "third_party_api_keys" {
  description = "API keys de proveedores externos (KYC, etc.) a guardar en Secrets Manager. Nunca en un .tfvars commiteado -- via TF_VAR_third_party_api_keys en el pipeline."
  type        = map(string)
  default     = {}
  sensitive   = true
}

variable "github_repository" {
  type    = string
  default = "pdfloresjdav/test-devsu"
}
