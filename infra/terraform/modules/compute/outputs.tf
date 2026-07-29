output "cluster_name" {
  value = aws_ecs_cluster.this.name
}

output "cluster_arn" {
  value = aws_ecs_cluster.this.arn
}

output "ecr_repository_urls" {
  value = { for name, repo in aws_ecr_repository.this : name => repo.repository_url }
}

output "service_names" {
  value = { for name, svc in aws_ecs_service.this : name => svc.name }
}

output "api_gateway_endpoint" {
  value = aws_apigatewayv2_stage.default.invoke_url
}

output "service_discovery_namespace_id" {
  value = aws_service_discovery_private_dns_namespace.internal.id
}

output "log_group_names" {
  value = { for name, lg in aws_cloudwatch_log_group.service : name => lg.name }
}
