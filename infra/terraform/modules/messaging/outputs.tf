output "event_bus_name" {
  value = aws_cloudwatch_event_bus.domain_events.name
}

output "event_bus_arn" {
  value = aws_cloudwatch_event_bus.domain_events.arn
}

output "worker_queue_names" {
  description = "Nombre de cola por worker -- consumido por el modulo compute para autoscaling por profundidad de cola."
  value = {
    svc-audit         = aws_sqs_queue.queue["audit"].name
    svc-notifications = aws_sqs_queue.queue["notifications"].name
  }
}

output "worker_queue_arns" {
  value = { for k, q in aws_sqs_queue.queue : k => q.arn }
}

output "worker_dlq_arns" {
  value = { for k, q in aws_sqs_queue.dlq : k => q.arn }
}

output "pinpoint_app_id" {
  value = aws_pinpoint_app.this.application_id
}

output "ses_domain_identity_arn" {
  value = aws_ses_domain_identity.this.arn
}
