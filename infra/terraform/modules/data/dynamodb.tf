# Decision 3.10 (movimientos) + 3.9 (auditoria): DynamoDB para los patrones
# de acceso de alto volumen/particionado simple. Streams habilitado en las
# 3 tablas (item 14.3) porque alimentan el pipeline de auditoria
# (invalidacion de cache para movimientos, WORM Archiver para auditoria) y
# porque el bloque `replica` de mas abajo (Global Tables v2) depende de
# streams para replicar entre regiones.

resource "aws_dynamodb_table" "movements" {
  provider = aws.primary

  name         = "${var.name_prefix}-movements"
  billing_mode = "PAY_PER_REQUEST" # carga variable/impredecible, igual criterio que el resto del proyecto en LocalStack
  hash_key     = "account_id"
  range_key    = "movement_id"

  attribute {
    name = "account_id"
    type = "S"
  }

  attribute {
    name = "movement_id"
    type = "S"
  }

  stream_enabled   = true
  stream_view_type = "NEW_AND_OLD_IMAGES"

  point_in_time_recovery {
    enabled = true
  }

  replica {
    region_name = var.dr_region
  }

  tags = var.tags
}

resource "aws_dynamodb_table" "audit" {
  provider = aws.primary

  name         = "${var.name_prefix}-audit"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "actor"
  range_key    = "sort_key"

  attribute {
    name = "actor"
    type = "S"
  }

  attribute {
    name = "sort_key"
    type = "S"
  }

  stream_enabled   = true
  stream_view_type = "NEW_AND_OLD_IMAGES"

  point_in_time_recovery {
    enabled = true # dato de auditoria: no repudio (seccion 9.1), no puede perderse
  }

  replica {
    region_name = var.dr_region
  }

  tags = var.tags
}

resource "aws_dynamodb_table" "notification_deliveries" {
  provider = aws.primary

  name         = "${var.name_prefix}-notification-deliveries"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "actor"
  range_key    = "sort_key"

  attribute {
    name = "actor"
    type = "S"
  }

  attribute {
    name = "sort_key"
    type = "S"
  }

  stream_enabled   = true
  stream_view_type = "NEW_AND_OLD_IMAGES"

  point_in_time_recovery {
    enabled = true
  }

  replica {
    region_name = var.dr_region
  }

  tags = var.tags
}
