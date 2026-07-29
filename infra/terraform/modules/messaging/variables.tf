variable "name_prefix" {
  type = string
}

variable "ses_from_domain" {
  description = "Dominio verificado para el remitente de emails transaccionales (decision 3.12)."
  type        = string
  default     = "bp.test"
}

variable "tags" {
  type    = map(string)
  default = {}
}
