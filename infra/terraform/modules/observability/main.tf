# Seccion 9.5: CloudWatch (metricas/logs/alarmas), X-Ray (dado de alta en
# el modulo compute como sidecar), CloudWatch Synthetics (canarios) y
# GuardDuty + Security Hub para postura de seguridad continua.

resource "aws_sns_topic" "alerts" {
  name = "${var.name_prefix}-alerts"

  tags = var.tags
}

resource "aws_sns_topic_subscription" "alerts_email" {
  count = var.alert_email != "" ? 1 : 0

  topic_arn = aws_sns_topic.alerts.arn
  protocol  = "email"
  endpoint  = var.alert_email
}

# Una alarma de DLQ por worker -- un mensaje ahi significa que Auditoria o
# Notificaciones no pudieron procesar un evento 3 veces (redrive policy del
# modulo messaging), lo cual es una falla real que exige intervencion, no
# un simple reintento automatico mas.
resource "aws_cloudwatch_metric_alarm" "dlq_has_messages" {
  for_each = var.worker_dlq_arns

  alarm_name          = "${var.name_prefix}-${each.key}-dlq-not-empty"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 1
  metric_name         = "ApproximateNumberOfMessagesVisible"
  namespace           = "AWS/SQS"
  period              = 300
  statistic           = "Maximum"
  threshold           = 0
  alarm_description   = "Hay mensajes en la DLQ de ${each.key} -- un evento de dominio no se pudo procesar tras 3 reintentos"
  treat_missing_data  = "notBreaching"

  dimensions = {
    QueueName = split(":", each.value)[5]
  }

  alarm_actions = [aws_sns_topic.alerts.arn]
  ok_actions    = [aws_sns_topic.alerts.arn]

  tags = var.tags
}

resource "aws_cloudwatch_metric_alarm" "ecs_cpu_high" {
  for_each = var.service_names

  alarm_name          = "${var.name_prefix}-${each.key}-cpu-high"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 3
  metric_name         = "CPUUtilization"
  namespace           = "AWS/ECS"
  period              = 60
  statistic           = "Average"
  threshold           = 85
  alarm_description   = "CPU sostenida sobre 85% en ${each.key} -- Auto Scaling deberia estar sumando tareas (modulo compute); si esto se dispara igual, revisar limites de max_capacity"
  treat_missing_data  = "notBreaching"

  dimensions = {
    ClusterName = var.ecs_cluster_name
    ServiceName = each.value
  }

  alarm_actions = [aws_sns_topic.alerts.arn]

  tags = var.tags
}

resource "aws_cloudwatch_metric_alarm" "api_5xx" {
  alarm_name          = "${var.name_prefix}-api-5xx"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 1
  metric_name         = "5xx"
  namespace           = "AWS/ApiGateway"
  period              = 60
  statistic           = "Sum"
  threshold           = 10
  alarm_description   = "Mas de 10 errores 5xx en 1 minuto en la API -- indica un problema en uno de los BFFs o su cadena de dependencias"
  treat_missing_data  = "notBreaching"

  alarm_actions = [aws_sns_topic.alerts.arn]

  tags = var.tags
}

# Canario sintetico: ejercita login + movimientos + transferencia de forma
# continua, igual que el criterio de aceptacion manual de la Fase 11 de
# este proyecto pero corriendo solo, 24/7, contra el ambiente real.
resource "aws_s3_bucket" "synthetics_artifacts" {
  bucket_prefix = "${var.name_prefix}-synthetics-"

  tags = var.tags
}

resource "aws_iam_role" "synthetics" {
  name = "${var.name_prefix}-synthetics-canary"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Service = "lambda.amazonaws.com" }
      Action    = "sts:AssumeRole"
    }]
  })

  tags = var.tags
}

resource "aws_iam_role_policy_attachment" "synthetics_execution" {
  role       = aws_iam_role.synthetics.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/CloudWatchSyntheticsFullAccess"
}

resource "aws_synthetics_canary" "critical_flow" {
  name                 = "${var.name_prefix}-critical-flow"
  artifact_s3_location = "s3://${aws_s3_bucket.synthetics_artifacts.id}/canary"
  execution_role_arn   = aws_iam_role.synthetics.arn
  handler              = "criticalFlow.handler"
  runtime_version      = "syn-nodejs-puppeteer-9.0"
  zip_file             = "${path.module}/canary/critical-flow.zip"

  schedule {
    expression = "rate(5 minutes)"
  }

  run_config {
    timeout_in_seconds = 60
    environment_variables = {
      API_BASE_URL = var.api_gateway_endpoint
    }
  }

  tags = var.tags
}

resource "aws_guardduty_detector" "this" {
  enable = true

  datasources {
    s3_logs {
      enable = true
    }
  }

  tags = var.tags
}

resource "aws_securityhub_account" "this" {}

resource "aws_securityhub_standards_subscription" "aws_foundational" {
  depends_on    = [aws_securityhub_account.this]
  standards_arn = "arn:aws:securityhub:${data.aws_region.current.name}::standards/aws-foundational-security-best-practices/v/1.0.0"
}

data "aws_region" "current" {}
