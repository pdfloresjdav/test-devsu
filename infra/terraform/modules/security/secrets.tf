# Secrets Manager con rotacion automatica (seccion 9.4) para credenciales
# de proveedores externos que no son una base de datos administrada por AWS
# (esas -- Aurora -- generan su propio secreto en el modulo data; ver
# comentario ahi sobre por que no comparte la KMS key "secrets" de aca para
# evitar una dependencia circular entre modulos).

locals {
  # for_each no admite un mapa marcado como sensible (var.third_party_api_keys
  # lo esta, correctamente, porque son API keys de terceros) -- las claves
  # en si (nombres de proveedor, ej. "onfido") no son secretas, solo los
  # valores. nonsensitive() sobre las claves no expone ningun secreto real.
  third_party_api_key_names = nonsensitive(keys(var.third_party_api_keys))
}

resource "aws_secretsmanager_secret" "third_party" {
  for_each = toset(local.third_party_api_key_names)

  name       = "${var.name_prefix}/third-party/${each.key}"
  kms_key_id = aws_kms_key.secrets.arn

  tags = var.tags
}

resource "aws_secretsmanager_secret_version" "third_party" {
  for_each = toset(local.third_party_api_key_names)

  secret_id     = aws_secretsmanager_secret.third_party[each.key].id
  secret_string = var.third_party_api_keys[each.key]
}

# Rotacion automatica -- requiere una funcion Lambda de rotacion desplegada
# (AWS publica una via el Serverless Application Repository para RDS; para
# API keys de terceros como Onfido/Auth0 la rotacion es especifica del
# proveedor y normalmente se implementa como una Lambda propia que llama a
# la API de "rotate credential" de cada proveedor). Se deja el recurso
# preparado y documentado en vez de asumir un ARN de Lambda que no existe
# en este ejercicio -- completar `rotation_lambda_arn` antes de aplicar
# contra una cuenta real.
variable "rotation_lambda_arn" {
  description = "ARN de la Lambda de rotacion (una por tipo de secreto en un caso real; se simplifica a una sola aca). Vacio = rotacion deshabilitada hasta tener la Lambda desplegada."
  type        = string
  default     = ""
}

resource "aws_secretsmanager_secret_rotation" "third_party" {
  for_each = var.rotation_lambda_arn != "" ? toset(local.third_party_api_key_names) : toset([])

  secret_id           = aws_secretsmanager_secret.third_party[each.key].id
  rotation_lambda_arn = var.rotation_lambda_arn

  rotation_rules {
    automatically_after_days = 90
  }
}
