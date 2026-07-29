variable "name_prefix" {
  type = string
}

variable "vpc_id" {
  type = string
}

variable "public_subnet_ids" {
  type = list(string)
}

variable "private_subnet_ids" {
  type = list(string)
}

variable "ecs_tasks_security_group_id" {
  type = string
}

variable "edge_security_group_id" {
  type = string
}

variable "services" {
  description = "Mismo mapa de services que la variable raiz (ver variables.tf raiz)."
  type = map(object({
    exposes_http = bool
    port         = number
    is_worker    = bool
    cpu          = number
    memory       = number
  }))
}

variable "task_role_arns" {
  description = "IAM Task Role de minimo privilegio por servicio (item 14.5, definido en el modulo security y pasado como input aca -- compute no decide permisos de negocio, solo los conecta)."
  type        = map(string)
}

variable "container_secrets" {
  description = "ARNs de Secrets Manager por servicio, inyectados como variables de entorno seguras en el contenedor (item 14.5)."
  type        = map(string)
  default     = {}
}

variable "auth0_issuer_url" {
  description = "Issuer OIDC real de Auth0 (https://<domain>/) para el JWT Authorizer de API Gateway."
  type        = string
}

variable "auth0_audience" {
  type    = string
  default = "bp-web"
}

variable "environment" {
  type = string
}

variable "log_retention_days" {
  type    = number
  default = 30
}

variable "worker_queue_names" {
  description = "Nombre de la cola SQS principal de cada worker (Auditoria/Notificaciones), para escalar por profundidad de cola (seccion 9.6) en vez de CPU -- un worker I/O-bound puede tener CPU baja y una cola creciendo igual."
  type        = map(string)
  default     = {}
}

variable "tags" {
  type    = map(string)
  default = {}
}
