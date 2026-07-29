variable "name_prefix" {
  type = string
}

variable "environment" {
  type = string
}

variable "third_party_api_keys" {
  description = "Secretos que no son credenciales de una base de datos (API keys de Onfido/iProov, tokens de gestion de Auth0, etc.) -- decision 3.7/3.5. Mapa nombre logico -> valor; el valor real se pasa por TF_VAR_* en CI/CD, nunca hardcodeado en un .tfvars commiteado (ver environments/prod.tfvars)."
  type        = map(string)
  default     = {}
  sensitive   = true
}

variable "tags" {
  type    = map(string)
  default = {}
}
