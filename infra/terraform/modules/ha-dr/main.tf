# Seccion 9.3: Route 53 con failover routing + health checks hacia la
# region secundaria pasiva. Los datos (Aurora Global Database, DynamoDB
# Global Tables, S3 CRR -- modulo data) ya replican de forma continua
# hacia la region DR sin importar si hay trafico ahi o no.
#
# Simplificacion de alcance documentada (decision 9.7, costo-consciente):
# el COMPUTO de DR (ECS Fargate + API Gateway + CloudFront en la region
# secundaria) no se instancia en caliente 24/7 en este ejercicio -- eso
# duplicaria el costo de computo activo para un standby que, salvo un
# desastre real, nunca recibe trafico. El patron real para activarlo es
# volver a invocar los modulos `compute` y `security` con
# `providers = { aws = aws.secondary }` (mismo patron ya usado para
# replicar `network` en la region DR) como parte del runbook de failover,
# no como infraestructura permanente. Mientras tanto, `secondary_endpoint`
# puede apuntar a una pagina estatica de "servicio en mantenimiento" en S3
# + CloudFront (mucho mas barato de mantener siempre activa) para que el
# failover de DNS de abajo tenga adonde apuntar sin dejar el dominio muerto.

resource "aws_route53_zone" "this" {
  count = var.domain_name != "" ? 1 : 0

  name = var.domain_name
  tags = var.tags
}

locals {
  zone_id = var.domain_name != "" ? aws_route53_zone.this[0].zone_id : ""
}

resource "aws_route53_health_check" "primary" {
  fqdn              = var.primary_endpoint
  port              = 443
  type              = "HTTPS"
  resource_path     = "/web/health"
  failure_threshold = 3
  request_interval  = 30

  tags = var.tags
}

resource "aws_route53_record" "primary" {
  count = var.domain_name != "" ? 1 : 0

  zone_id = local.zone_id
  name    = "api.${var.domain_name}"
  type    = "CNAME"
  ttl     = 60
  records = [var.primary_endpoint]

  set_identifier = "primary"

  failover_routing_policy {
    type = "PRIMARY"
  }

  health_check_id = aws_route53_health_check.primary.id
}

resource "aws_route53_record" "secondary" {
  count = var.domain_name != "" ? 1 : 0

  zone_id = local.zone_id
  name    = "api.${var.domain_name}"
  type    = "CNAME"
  ttl     = 60
  records = [var.secondary_endpoint]

  set_identifier = "secondary"

  failover_routing_policy {
    type = "SECONDARY"
  }
}

# Objetivos de referencia (seccion 9.3), documentados aca para que quien
# opere el sistema sepa contra que medir un simulacro de DR real:
#   RTO objetivo: < 4 horas para Transferencias/Movimientos.
#   RPO objetivo: < 15 minutos (Aurora Global Database da ~1s, DynamoDB
#   Global Tables da segundos -- el cuello de botella real de RPO/RTO es
#   el tiempo de promover el compute de DR de "frio" a "recibiendo
#   trafico", no la replicacion de datos en si).
