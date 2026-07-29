# Decision 3.4: ECS Fargate para los servicios sincronos, sin gestion de
# servidores. Un cluster unico para todo el sistema (aislamiento entre
# servicios lo dan las task definitions/SGs, no clusters separados).

resource "aws_ecs_cluster" "this" {
  name = "${var.name_prefix}-cluster"

  setting {
    name  = "containerInsights"
    value = "enabled" # metricas de cluster/servicio en CloudWatch (seccion 9.5)
  }

  tags = var.tags
}

resource "aws_cloudwatch_log_group" "service" {
  for_each = var.services

  name              = "/ecs/${var.name_prefix}/${each.key}"
  retention_in_days = var.log_retention_days

  tags = var.tags
}

# Cloud Map: descubrimiento de servicios interno (BFF -> microservicios de
# negocio) sin necesitar un load balancer por servicio interno -- las
# tareas se resuelven por DNS privado dentro de la VPC.
resource "aws_service_discovery_private_dns_namespace" "internal" {
  name        = "${var.name_prefix}.internal"
  description = "Namespace de descubrimiento de servicios para comunicacion interna entre microservicios"
  vpc         = var.vpc_id

  tags = var.tags
}

resource "aws_service_discovery_service" "this" {
  for_each = var.services

  name = each.key

  dns_config {
    namespace_id = aws_service_discovery_private_dns_namespace.internal.id

    dns_records {
      ttl  = 10
      type = "A"
    }

    routing_policy = "MULTIVALUE"
  }

  health_check_custom_config {
    failure_threshold = 1
  }

  tags = var.tags
}

# Execution role: usado por el AGENTE de ECS para arrancar el contenedor
# (pull de ECR, escritura de logs, lectura de secrets) -- NO es el rol con
# el que corre el codigo de la aplicacion (eso es var.task_role_arns, de
# minimo privilegio por servicio, definido en el modulo security).
resource "aws_iam_role" "execution" {
  name = "${var.name_prefix}-ecs-execution"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Service = "ecs-tasks.amazonaws.com" }
      Action    = "sts:AssumeRole"
    }]
  })

  tags = var.tags
}

resource "aws_iam_role_policy_attachment" "execution_managed" {
  role       = aws_iam_role.execution.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

resource "aws_iam_role_policy" "execution_secrets" {
  count = length(var.container_secrets) > 0 ? 1 : 0

  name = "${var.name_prefix}-ecs-execution-secrets"
  role = aws_iam_role.execution.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = ["secretsmanager:GetSecretValue"]
      Resource = values(var.container_secrets)
    }]
  })
}

resource "aws_ecs_task_definition" "this" {
  for_each = var.services

  family                   = "${var.name_prefix}-${each.key}"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = each.value.cpu
  memory                   = each.value.memory
  execution_role_arn       = aws_iam_role.execution.arn
  task_role_arn            = var.task_role_arns[each.key]

  container_definitions = jsonencode([{
    name      = each.key
    image     = "${aws_ecr_repository.this[each.key].repository_url}:latest"
    essential = true

    portMappings = each.value.exposes_http || !each.value.is_worker ? [{
      containerPort = each.value.port
      protocol      = "tcp"
    }] : []

    secrets = [
      for env_name, arn in var.container_secrets : {
        name      = env_name
        valueFrom = arn
      }
    ]

    environment = [
      { name = "APP_ENV", value = var.environment },
    ]

    logConfiguration = {
      logDriver = "awslogs"
      options = {
        "awslogs-group"         = aws_cloudwatch_log_group.service[each.key].name
        "awslogs-region"        = data.aws_region.current.name
        "awslogs-stream-prefix" = each.key
      }
    }

    healthCheck = each.value.exposes_http ? {
      command     = ["CMD-SHELL", "curl -f http://localhost:${each.value.port}/health || exit 1"]
      interval    = 30
      timeout     = 5
      retries     = 3
      startPeriod = 30
    } : null
    },
    # X-Ray daemon como sidecar (seccion 9.5: tracing distribuido) -- cada
    # tarea envia sus trazas por UDP local a este contenedor, que las
    # reenvia al servicio X-Ray real. Patron estandar en Fargate (no hay
    # host compartido donde correr un daemon unico como en EC2).
    {
      name              = "xray-daemon"
      image             = "public.ecr.aws/xray/aws-xray-daemon:latest"
      essential         = false
      cpu               = 32
      memoryReservation = 256

      portMappings = [{
        containerPort = 2000
        protocol      = "udp"
      }]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.service[each.key].name
          "awslogs-region"        = data.aws_region.current.name
          "awslogs-stream-prefix" = "xray"
        }
      }
  }])

  tags = var.tags
}

data "aws_region" "current" {}

resource "aws_ecs_service" "this" {
  for_each = var.services

  name            = each.key
  cluster         = aws_ecs_cluster.this.id
  task_definition = aws_ecs_task_definition.this[each.key].arn
  launch_type     = "FARGATE"

  # Minimo 2 tareas -- seccion 9.2 (Alta disponibilidad): "un minimo de 2
  # tareas por Availability Zone" se traduce aca en 2 tareas repartidas por
  # el scheduler de ECS entre las AZs de las subredes privadas.
  desired_count = 2

  network_configuration {
    subnets         = var.private_subnet_ids
    security_groups = [var.ecs_tasks_security_group_id]
  }

  service_registries {
    registry_arn = aws_service_discovery_service.this[each.key].arn
  }

  deployment_circuit_breaker {
    enable   = true
    rollback = true # auto-healing de despliegues fallidos (seccion 9.6), no solo de tareas caidas
  }

  tags = var.tags
}
