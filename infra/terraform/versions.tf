terraform {
  required_version = ">= 1.7.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    auth0 = {
      source  = "auth0/auth0"
      version = "~> 1.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.6"
    }
    tls = {
      source  = "hashicorp/tls"
      version = "~> 4.0"
    }
  }

  # Estado remoto (decision 9.2/9.3: el propio estado de Terraform es un
  # recurso critico que tiene que sobrevivir a la caida de una sola AZ).
  # Comentado a proposito: requiere un bucket S3 + tabla DynamoDB de locking
  # creados por fuera de este mismo Terraform (bootstrap manual una sola
  # vez, patron estandar para evitar la dependencia circular de "Terraform
  # necesita el backend que el propio Terraform crearia"). Descomentar y
  # completar los nombres reales antes del primer `terraform init` contra
  # una cuenta de AWS real.
  # backend "s3" {
  #   bucket         = "bp-terraform-state-<account-id>"
  #   key            = "banca-digital-bp/terraform.tfstate"
  #   region         = "us-east-1"
  #   dynamodb_table = "bp-terraform-locks"
  #   encrypt        = true
  # }
}
