# Seccion 9.6 (Auto-healing) + seccion 9.7 (costos): escalar cada servicio
# de forma independiente segun su propia senal de carga, en vez de un
# numero fijo de tareas -- evita sobreaprovisionar computo para cargas
# intermitentes.

resource "aws_appautoscaling_target" "this" {
  for_each = var.services

  max_capacity       = each.value.is_worker ? 6 : 10
  min_capacity       = 2 # nunca menos de 2 tareas -- decision 9.2
  resource_id        = "service/${aws_ecs_cluster.this.name}/${aws_ecs_service.this[each.key].name}"
  scalable_dimension = "ecs:service:DesiredCount"
  service_namespace  = "ecs"
}

# Servicios sincronos (BFFs y microservicios de negocio): target tracking
# por CPU -- senal directa de carga de trabajo en un servicio request/response.
resource "aws_appautoscaling_policy" "cpu" {
  for_each = { for name, svc in var.services : name => svc if !svc.is_worker }

  name               = "${var.name_prefix}-${each.key}-cpu"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.this[each.key].resource_id
  scalable_dimension = aws_appautoscaling_target.this[each.key].scalable_dimension
  service_namespace  = aws_appautoscaling_target.this[each.key].service_namespace

  target_tracking_scaling_policy_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageCPUUtilization"
    }
    target_value       = 60
    scale_in_cooldown  = 120
    scale_out_cooldown = 60
  }
}

# Workers (Auditoria/Notificaciones): target tracking por profundidad de
# cola SQS -- un worker puede estar con CPU baja mientras la cola crece si
# el cuello de botella es I/O (llamadas a DynamoDB/SES/Pinpoint), no CPU.
resource "aws_appautoscaling_policy" "queue_depth" {
  for_each = { for name, svc in var.services : name => svc if svc.is_worker && contains(keys(var.worker_queue_names), name) }

  name               = "${var.name_prefix}-${each.key}-queue-depth"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.this[each.key].resource_id
  scalable_dimension = aws_appautoscaling_target.this[each.key].scalable_dimension
  service_namespace  = aws_appautoscaling_target.this[each.key].service_namespace

  target_tracking_scaling_policy_configuration {
    customized_metric_specification {
      metric_name = "ApproximateNumberOfMessagesVisible"
      namespace   = "AWS/SQS"
      statistic   = "Average"
      unit        = "Count"

      dimensions {
        name  = "QueueName"
        value = var.worker_queue_names[each.key]
      }
    }
    target_value       = 50 # mensajes visibles por tarea antes de sumar otra
    scale_in_cooldown  = 180
    scale_out_cooldown = 60
  }
}
