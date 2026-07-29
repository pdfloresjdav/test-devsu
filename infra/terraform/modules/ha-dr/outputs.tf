output "hosted_zone_id" {
  value = local.zone_id
}

output "primary_health_check_id" {
  value = aws_route53_health_check.primary.id
}
