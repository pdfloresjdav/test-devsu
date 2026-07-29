output "aurora_cluster_endpoint" {
  value = aws_rds_cluster.primary.endpoint
}

output "aurora_secret_arn" {
  value = aws_secretsmanager_secret.aurora_master.arn
}

output "dynamodb_table_names" {
  value = {
    movements               = aws_dynamodb_table.movements.name
    audit                   = aws_dynamodb_table.audit.name
    notification_deliveries = aws_dynamodb_table.notification_deliveries.name
  }
}

output "dynamodb_table_arns" {
  value = {
    movements               = aws_dynamodb_table.movements.arn
    audit                   = aws_dynamodb_table.audit.arn
    notification_deliveries = aws_dynamodb_table.notification_deliveries.arn
  }
}

output "redis_primary_endpoint" {
  value = aws_elasticache_replication_group.this.primary_endpoint_address
}

output "audit_worm_bucket_name" {
  value = aws_s3_bucket.audit_worm_primary.id
}

output "audit_worm_bucket_arn" {
  value = aws_s3_bucket.audit_worm_primary.arn
}
