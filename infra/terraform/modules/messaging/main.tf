# Decision 3.13: EventBridge + SQS con Pub/Sub y Competing Consumers -- un
# bus compartido, una cola+DLQ POR CONSUMIDOR (Auditoria, Notificaciones),
# mismo patron ya implementado en LocalStack (services/svc-audit y
# services/svc-notifications, comando `*:setup-infrastructure`). Cada
# consumidor recibe su propia copia de todo evento, aislado por Bulkhead.

resource "aws_cloudwatch_event_bus" "domain_events" {
  name = "${var.name_prefix}-domain-events"

  tags = var.tags
}

locals {
  # Un consumidor por servicio worker -- agregar uno nuevo (ej. un futuro
  # motor de deteccion de fraude, decision 3.13) es agregar una entrada
  # aca, sin tocar a los productores del evento.
  consumers = toset(["audit", "notifications"])
}

resource "aws_sqs_queue" "dlq" {
  for_each = local.consumers

  name                      = "${var.name_prefix}-${each.key}-events-dlq"
  message_retention_seconds = 1209600 # 14 dias -- tiempo para investigar mensajes que fallaron 3 veces

  tags = var.tags
}

resource "aws_sqs_queue" "queue" {
  for_each = local.consumers

  name                       = "${var.name_prefix}-${each.key}-events-queue"
  visibility_timeout_seconds = 30
  message_retention_seconds  = 345600 # 4 dias

  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.dlq[each.key].arn
    maxReceiveCount     = 3
  })

  tags = var.tags
}

resource "aws_sqs_queue_redrive_allow_policy" "queue" {
  for_each = local.consumers

  queue_url = aws_sqs_queue.dlq[each.key].id

  redrive_allow_policy = jsonencode({
    redrivePermission = "byQueue"
    sourceQueueArns   = [aws_sqs_queue.queue[each.key].arn]
  })
}

resource "aws_cloudwatch_event_rule" "consumer" {
  for_each = local.consumers

  name           = "${var.name_prefix}-${each.key}-all-domain-events"
  event_bus_name = aws_cloudwatch_event_bus.domain_events.name

  event_pattern = jsonencode({
    source = [{ prefix = "bp." }]
  })

  tags = var.tags
}

resource "aws_cloudwatch_event_target" "consumer" {
  for_each = local.consumers

  rule           = aws_cloudwatch_event_rule.consumer[each.key].name
  event_bus_name = aws_cloudwatch_event_bus.domain_events.name
  arn            = aws_sqs_queue.queue[each.key].arn
}

resource "aws_sqs_queue_policy" "allow_eventbridge" {
  for_each = local.consumers

  queue_url = aws_sqs_queue.queue[each.key].id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Service = "events.amazonaws.com" }
      Action    = "sqs:SendMessage"
      Resource  = aws_sqs_queue.queue[each.key].arn
      Condition = {
        ArnEquals = { "aws:SourceArn" = aws_cloudwatch_event_rule.consumer[each.key].arn }
      }
    }]
  })
}

# Decision 3.12: canal inmediato (push, Pinpoint) + canal de respaldo
# (email, SES) -- dos proveedores independientes para redundancia real.
resource "aws_pinpoint_app" "this" {
  name = "${var.name_prefix}-notifications"

  tags = var.tags
}

resource "aws_ses_domain_identity" "this" {
  domain = var.ses_from_domain
}

resource "aws_ses_domain_dkim" "this" {
  domain = aws_ses_domain_identity.this.domain
}
