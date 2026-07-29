variable "name_prefix" {
  type = string
}

variable "alert_email" {
  type = string
}

variable "ecs_cluster_name" {
  type = string
}

variable "service_names" {
  type = map(string)
}

variable "api_gateway_endpoint" {
  type = string
}

variable "worker_dlq_arns" {
  description = "ARN de cada DLQ de worker (Auditoria/Notificaciones) -- una alarma se dispara si algo cae ahi, porque significa que un evento no se pudo procesar 3 veces."
  type        = map(string)
}

variable "tags" {
  type    = map(string)
  default = {}
}
