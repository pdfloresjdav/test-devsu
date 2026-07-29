variable "name_prefix" {
  type = string
}

variable "domain_name" {
  description = "Dominio publico de BP (zona alojada en Route 53). Vacio = no se crea la zona (asume que ya existe y se referencia por data source)."
  type        = string
}

variable "primary_endpoint" {
  description = "Endpoint publico de la region primaria (CloudFront, modulo security)."
  type        = string
}

variable "secondary_endpoint" {
  description = "Endpoint publico de la region secundaria (CloudFront de DR)."
  type        = string
}

variable "tags" {
  type    = map(string)
  default = {}
}
