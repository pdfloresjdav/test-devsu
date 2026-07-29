variable "name_prefix" {
  type = string
}

variable "environment" {
  type = string
}

variable "api_gateway_endpoint" {
  description = "Endpoint HTTP del modulo compute -- separado del modulo security para evitar una dependencia circular (compute necesita los IAM Task Roles de security; CloudFront necesita el endpoint de compute, que solo existe despues de crear la API Gateway)."
  type        = string
}

variable "tags" {
  type    = map(string)
  default = {}
}
