output "cloudfront_domain_name" {
  value = module.edge.cloudfront_domain_name
}

output "api_gateway_endpoint" {
  value = module.compute.api_gateway_endpoint
}

output "ecr_repository_urls" {
  value = module.compute.ecr_repository_urls
}

output "aurora_cluster_endpoint" {
  value = module.data.aurora_cluster_endpoint
}

output "dynamodb_table_names" {
  value = module.data.dynamodb_table_names
}

output "event_bus_name" {
  value = module.messaging.event_bus_name
}

output "github_actions_deploy_role_arn" {
  value = module.security.github_actions_deploy_role_arn
}

output "vpc_id" {
  value = module.network.vpc_id
}
