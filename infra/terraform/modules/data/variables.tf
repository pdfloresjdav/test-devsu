terraform {
  required_providers {
    aws = {
      source                = "hashicorp/aws"
      configuration_aliases = [aws.primary, aws.secondary]
    }
  }
}

variable "name_prefix" {
  type = string
}

variable "environment" {
  type = string
}

variable "primary_private_subnet_ids" {
  type = list(string)
}

variable "primary_data_security_group_id" {
  type = string
}

variable "dr_vpc_cidr" {
  description = "CIDR de la VPC de DR -- usado para el Security Group de datos en la region secundaria (esa VPC/subredes se crean en el modulo network cuando se instancia una segunda vez para DR; ver comentario en ha-dr)."
  type        = string
}

variable "dr_vpc_id" {
  type = string
}

variable "dr_private_subnet_ids" {
  type = list(string)
}

variable "dr_region" {
  description = "Nombre de la region de DR (para el bloque replica de DynamoDB Global Tables)."
  type        = string
}

variable "backup_retention_days" {
  description = "Retencion de backups automaticos de Aurora (seccion 9.3: RPO/RTO)."
  type        = number
  default     = 14
}

variable "tags" {
  type    = map(string)
  default = {}
}
