# Decision 3.8: Cache-Aside con ElastiCache Redis (ultimos movimientos por
# cuenta, decision 3.4/3.10 tambien para Idempotency-Key y estado del
# Circuit Breaker en svc-transfers). Multi-AZ con failover automatico
# (seccion 9.2) -- no requiere logica adicional de la aplicacion.

resource "aws_elasticache_subnet_group" "this" {
  provider = aws.primary

  name       = "${var.name_prefix}-redis"
  subnet_ids = var.primary_private_subnet_ids

  tags = var.tags
}

resource "aws_elasticache_replication_group" "this" {
  provider = aws.primary

  replication_group_id = "${var.name_prefix}-redis"
  description          = "Cache-Aside de movimientos + Idempotency-Key + estado de Circuit Breaker"

  engine         = "redis"
  engine_version = "7.1"
  node_type      = var.environment == "prod" ? "cache.r7g.large" : "cache.t4g.micro"
  port           = 6379

  num_cache_clusters         = 2 # Multi-AZ: primario + 1 replica en otra AZ
  automatic_failover_enabled = true
  multi_az_enabled           = true

  subnet_group_name  = aws_elasticache_subnet_group.this.name
  security_group_ids = [var.primary_data_security_group_id]

  at_rest_encryption_enabled = true
  transit_encryption_enabled = true # seccion 9.4: ningun trafico interno en texto plano

  tags = var.tags
}
