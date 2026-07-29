# Seccion 9.4: WAF + Shield Advanced en el borde (CloudFront/API Gateway)
# contra OWASP Top 10 y DDoS. El WAF de scope CLOUDFRONT SIEMPRE se declara
# en us-east-1 (requisito de AWS, sin importar la region primaria real) --
# el provider aliased "aws.us_east_1" se recibe del root module (ver
# providers.tf y el bloque `providers` de module "edge" en main.tf), un
# modulo hijo no puede declarar su propio bloque `provider`.

resource "aws_wafv2_web_acl" "edge" {
  provider = aws.us_east_1

  name  = "${var.name_prefix}-edge-waf"
  scope = "CLOUDFRONT"

  default_action {
    allow {}
  }

  rule {
    name     = "aws-managed-common-rule-set"
    priority = 1

    override_action {
      none {}
    }

    statement {
      managed_rule_group_statement {
        name        = "AWSManagedRulesCommonRuleSet"
        vendor_name = "AWS"
      }
    }

    visibility_config {
      cloudwatch_metrics_enabled = true
      metric_name                = "${var.name_prefix}-common-rule-set"
      sampled_requests_enabled   = true
    }
  }

  rule {
    name     = "aws-managed-known-bad-inputs"
    priority = 2

    override_action {
      none {}
    }

    statement {
      managed_rule_group_statement {
        name        = "AWSManagedRulesKnownBadInputsRuleSet"
        vendor_name = "AWS"
      }
    }

    visibility_config {
      cloudwatch_metrics_enabled = true
      metric_name                = "${var.name_prefix}-known-bad-inputs"
      sampled_requests_enabled   = true
    }
  }

  # Rate limiting por IP -- capa adicional al throttling propio de API
  # Gateway (decision 3.11), protege contra fuerza bruta antes de llegar
  # siquiera al borde de la API.
  rule {
    name     = "rate-limit-per-ip"
    priority = 3

    action {
      block {}
    }

    statement {
      rate_based_statement {
        limit              = 2000 # requests / 5 min por IP
        aggregate_key_type = "IP"
      }
    }

    visibility_config {
      cloudwatch_metrics_enabled = true
      metric_name                = "${var.name_prefix}-rate-limit"
      sampled_requests_enabled   = true
    }
  }

  visibility_config {
    cloudwatch_metrics_enabled = true
    metric_name                = "${var.name_prefix}-edge-waf"
    sampled_requests_enabled   = true
  }

  tags = var.tags
}

resource "aws_cloudfront_distribution" "edge" {
  enabled     = true
  comment     = "${var.name_prefix} edge -- API Gateway (BFF Web/Movil)"
  web_acl_id  = aws_wafv2_web_acl.edge.arn
  price_class = "PriceClass_100" # NA/EU alcanza para el mercado inicial descrito en el enunciado

  origin {
    origin_id   = "api-gateway"
    domain_name = replace(replace(var.api_gateway_endpoint, "https://", ""), "/", "")

    custom_origin_config {
      http_port              = 80
      https_port             = 443
      origin_protocol_policy = "https-only"
      origin_ssl_protocols   = ["TLSv1.2"]
    }
  }

  default_cache_behavior {
    target_origin_id       = "api-gateway"
    viewer_protocol_policy = "redirect-to-https"
    allowed_methods        = ["GET", "HEAD", "OPTIONS", "PUT", "POST", "PATCH", "DELETE"]
    cached_methods         = ["GET", "HEAD"]

    # Respuestas de API (JSON dinamico, autenticado) -- no se cachean.
    cache_policy_id = "4135ea2d-6df8-44a3-9df3-4b5a84be39ad" # managed-CachingDisabled
  }

  restrictions {
    geo_restriction {
      restriction_type = "none"
    }
  }

  viewer_certificate {
    cloudfront_default_certificate = true # reemplazar por un ACM cert + dominio propio de BP antes de produccion real
  }

  tags = var.tags
}

# Shield Advanced protege recursos especificos, pero la suscripcion en si
# (costo fijo mensual por cuenta) es un opt-in de cuenta que AWS no expone
# como recurso de Terraform "crear suscripcion" -- se activa una vez desde
# la consola o Support API antes del primer apply real. Este recurso falla
# si la cuenta no tiene la suscripcion activa.
resource "aws_shield_protection" "edge" {
  count = var.environment == "prod" ? 1 : 0

  name         = "${var.name_prefix}-edge-shield"
  resource_arn = aws_cloudfront_distribution.edge.arn

  tags = var.tags
}
