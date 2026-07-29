# Decision 3.14 (item 14.5): un ECS Task Role de minimo privilegio POR
# SERVICIO -- nunca un rol generico compartido. Los ARNs de los recursos
# de datos/mensajeria se construyen por convencion de nombre (mismo
# `name_prefix` que usan los modulos data/messaging) en vez de recibirlos
# como output de esos modulos, para no crear una dependencia circular de
# modulos (security necesitaria outputs de data/messaging, que a su vez
# podrian necesitar KMS keys de security). Mientras la convencion de
# nombres se mantenga (ver modules/data y modules/messaging), los ARNs
# coinciden exactamente.

data "aws_caller_identity" "current" {}
data "aws_region" "current" {}

locals {
  account_id = data.aws_caller_identity.current.account_id
  region     = data.aws_region.current.name

  dynamodb_arn = {
    movements               = "arn:aws:dynamodb:${local.region}:${local.account_id}:table/${var.name_prefix}-movements"
    audit                   = "arn:aws:dynamodb:${local.region}:${local.account_id}:table/${var.name_prefix}-audit"
    notification_deliveries = "arn:aws:dynamodb:${local.region}:${local.account_id}:table/${var.name_prefix}-notification-deliveries"
  }

  sqs_arn = {
    audit         = "arn:aws:sqs:${local.region}:${local.account_id}:${var.name_prefix}-audit-events-queue"
    notifications = "arn:aws:sqs:${local.region}:${local.account_id}:${var.name_prefix}-notifications-events-queue"
  }

  event_bus_arn  = "arn:aws:events:${local.region}:${local.account_id}:event-bus/${var.name_prefix}-domain-events"
  audit_worm_arn = "arn:aws:s3:::${var.name_prefix}-audit-worm-${local.account_id}"
}

data "aws_iam_policy_document" "ecs_task_trust" {
  statement {
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}

# Baseline compartido: X-Ray (tracing distribuido, seccion 9.5) -- lo
# unico verdaderamente comun a los 7 servicios, adjuntado a cada rol
# individual (no via un rol padre) para que cada uno siga siendo
# autocontenido y auditable de forma independiente.
data "aws_iam_policy_document" "xray_baseline" {
  statement {
    effect = "Allow"
    actions = [
      "xray:PutTraceSegments",
      "xray:PutTelemetryRecords",
    ]
    resources = ["*"] # X-Ray no soporta scoping por recurso para estas acciones
  }
}

resource "aws_iam_role" "svc_customer_data" {
  name               = "${var.name_prefix}-svc-customer-data-task"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_trust.json
  tags               = var.tags
}

resource "aws_iam_role_policy" "svc_customer_data" {
  name   = "xray"
  role   = aws_iam_role.svc_customer_data.id
  policy = data.aws_iam_policy_document.xray_baseline.json
}

resource "aws_iam_role" "svc_movements" {
  name               = "${var.name_prefix}-svc-movements-task"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_trust.json
  tags               = var.tags
}

data "aws_iam_policy_document" "svc_movements" {
  statement {
    effect  = "Allow"
    actions = ["dynamodb:GetItem", "dynamodb:PutItem", "dynamodb:Query"]
    # unicamente su propia tabla -- decision 3.14
    resources = [local.dynamodb_arn.movements]
  }

  statement {
    effect    = "Allow"
    actions   = ["events:PutEvents"]
    resources = [local.event_bus_arn]
  }

  statement {
    effect    = "Allow"
    actions   = ["xray:PutTraceSegments", "xray:PutTelemetryRecords"]
    resources = ["*"]
  }
}

resource "aws_iam_role_policy" "svc_movements" {
  name   = "least-privilege"
  role   = aws_iam_role.svc_movements.id
  policy = data.aws_iam_policy_document.svc_movements.json
}

resource "aws_iam_role" "svc_transfers" {
  name               = "${var.name_prefix}-svc-transfers-task"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_trust.json
  tags               = var.tags
}

data "aws_iam_policy_document" "svc_transfers" {
  # Sin permisos de DynamoDB -- svc-transfers persiste en Aurora (acceso
  # via credenciales de Secrets Manager + red, no IAM de AWS SDK).
  statement {
    effect    = "Allow"
    actions   = ["events:PutEvents"]
    resources = [local.event_bus_arn]
  }

  statement {
    effect    = "Allow"
    actions   = ["xray:PutTraceSegments", "xray:PutTelemetryRecords"]
    resources = ["*"]
  }
}

resource "aws_iam_role_policy" "svc_transfers" {
  name   = "least-privilege"
  role   = aws_iam_role.svc_transfers.id
  policy = data.aws_iam_policy_document.svc_transfers.json
}

resource "aws_iam_role" "svc_audit" {
  name               = "${var.name_prefix}-svc-audit-task"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_trust.json
  tags               = var.tags
}

data "aws_iam_policy_document" "svc_audit" {
  statement {
    effect    = "Allow"
    actions   = ["dynamodb:PutItem", "dynamodb:Query"]
    resources = [local.dynamodb_arn.audit]
  }

  statement {
    effect    = "Allow"
    actions   = ["s3:PutObject", "s3:PutObjectRetention"]
    resources = ["${local.audit_worm_arn}/*"]
  }

  statement {
    effect    = "Allow"
    actions   = ["sqs:ReceiveMessage", "sqs:DeleteMessage", "sqs:GetQueueAttributes"]
    resources = [local.sqs_arn.audit]
  }

  statement {
    effect    = "Allow"
    actions   = ["xray:PutTraceSegments", "xray:PutTelemetryRecords"]
    resources = ["*"]
  }
}

resource "aws_iam_role_policy" "svc_audit" {
  name   = "least-privilege"
  role   = aws_iam_role.svc_audit.id
  policy = data.aws_iam_policy_document.svc_audit.json
}

resource "aws_iam_role" "svc_notifications" {
  name               = "${var.name_prefix}-svc-notifications-task"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_trust.json
  tags               = var.tags
}

data "aws_iam_policy_document" "svc_notifications" {
  statement {
    effect    = "Allow"
    actions   = ["dynamodb:PutItem", "dynamodb:Query"]
    resources = [local.dynamodb_arn.notification_deliveries]
  }

  statement {
    effect    = "Allow"
    actions   = ["sqs:ReceiveMessage", "sqs:DeleteMessage", "sqs:GetQueueAttributes"]
    resources = [local.sqs_arn.notifications]
  }

  statement {
    effect    = "Allow"
    actions   = ["mobiletargeting:SendMessages"]
    resources = ["*"] # Pinpoint no soporta scoping por app de forma granular en todas las acciones
  }

  statement {
    effect    = "Allow"
    actions   = ["ses:SendEmail", "ses:SendRawEmail"]
    resources = ["*"]
  }

  statement {
    effect    = "Allow"
    actions   = ["xray:PutTraceSegments", "xray:PutTelemetryRecords"]
    resources = ["*"]
  }
}

resource "aws_iam_role_policy" "svc_notifications" {
  name   = "least-privilege"
  role   = aws_iam_role.svc_notifications.id
  policy = data.aws_iam_policy_document.svc_notifications.json
}

resource "aws_iam_role" "bff_web" {
  name               = "${var.name_prefix}-bff-web-task"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_trust.json
  tags               = var.tags
}

resource "aws_iam_role_policy" "bff_web" {
  name   = "xray"
  role   = aws_iam_role.bff_web.id
  policy = data.aws_iam_policy_document.xray_baseline.json
}

resource "aws_iam_role" "bff_mobile" {
  name               = "${var.name_prefix}-bff-mobile-task"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_trust.json
  tags               = var.tags
}

data "aws_iam_policy_document" "bff_mobile" {
  statement {
    effect    = "Allow"
    actions   = ["events:PutEvents"] # OnboardingCompleted/OnboardingRejected
    resources = [local.event_bus_arn]
  }

  statement {
    effect    = "Allow"
    actions   = ["xray:PutTraceSegments", "xray:PutTelemetryRecords"]
    resources = ["*"]
  }
}

resource "aws_iam_role_policy" "bff_mobile" {
  name   = "least-privilege"
  role   = aws_iam_role.bff_mobile.id
  policy = data.aws_iam_policy_document.bff_mobile.json
}
