# Decision 3.11: Amazon API Gateway como capa de integracion unica, con
# patron BFF (un backend por canal). HTTP API (v2, no REST API) porque no
# se necesita transformacion de payload ni autorizador Lambda a medida --
# el JWT Authorizer nativo alcanza (decision 3.5/3.6: JWT firmado por
# Auth0, validado contra su JWKS).

resource "aws_apigatewayv2_api" "this" {
  name          = "${var.name_prefix}-api"
  protocol_type = "HTTP"

  cors_configuration {
    allow_origins = ["*"] # el origen real de la SPA se restringe en CloudFront/WAF (modulo security), no aca
    allow_methods = ["GET", "POST", "PUT", "DELETE", "OPTIONS"]
    allow_headers = ["Authorization", "Content-Type", "Idempotency-Key"]
  }

  tags = var.tags
}

# VPC Link: permite que API Gateway (que vive fuera de la VPC) llegue a los
# BFFs en subredes privadas sin exponerlos con una IP publica ni un NLB
# aparte -- se conecta directo al servicio de Cloud Map de cada BFF.
resource "aws_apigatewayv2_vpc_link" "this" {
  name               = "${var.name_prefix}-vpc-link"
  security_group_ids = [var.ecs_tasks_security_group_id]
  subnet_ids         = var.private_subnet_ids

  tags = var.tags
}

resource "aws_apigatewayv2_authorizer" "jwt" {
  api_id           = aws_apigatewayv2_api.this.id
  authorizer_type  = "JWT"
  identity_sources = ["$request.header.Authorization"]
  name             = "${var.name_prefix}-auth0-jwt"

  jwt_configuration {
    audience = [var.auth0_audience]
    issuer   = var.auth0_issuer_url
  }
}

resource "aws_apigatewayv2_integration" "bff" {
  for_each = toset(["bff-web", "bff-mobile"])

  api_id             = aws_apigatewayv2_api.this.id
  integration_type   = "HTTP_PROXY"
  integration_method = "ANY"
  connection_type    = "VPC_LINK"
  connection_id      = aws_apigatewayv2_vpc_link.this.id
  integration_uri    = aws_service_discovery_service.this[each.key].arn

  # El servicio en si valida el JWT una segunda vez (bp-common/JwtAuthMiddleware)
  # -- defensa en profundidad, no se confia solo en el authorizer del borde.
  payload_format_version = "1.0"
}

resource "aws_apigatewayv2_route" "web" {
  api_id             = aws_apigatewayv2_api.this.id
  route_key          = "ANY /web/{proxy+}"
  target             = "integrations/${aws_apigatewayv2_integration.bff["bff-web"].id}"
  authorization_type = "JWT"
  authorizer_id      = aws_apigatewayv2_authorizer.jwt.id
}

# /onboarding queda deliberadamente SIN el JWT authorizer del borde -- un
# cliente nuevo todavia no tiene token (mismo criterio que
# services/bff-mobile/routes/api.php: la verificacion KYC es el control de
# acceso real de este endpoint, no un JWT que no puede existir todavia).
resource "aws_apigatewayv2_route" "mobile_onboarding" {
  api_id    = aws_apigatewayv2_api.this.id
  route_key = "POST /mobile/onboarding"
  target    = "integrations/${aws_apigatewayv2_integration.bff["bff-mobile"].id}"
}

resource "aws_apigatewayv2_route" "mobile" {
  api_id             = aws_apigatewayv2_api.this.id
  route_key          = "ANY /mobile/{proxy+}"
  target             = "integrations/${aws_apigatewayv2_integration.bff["bff-mobile"].id}"
  authorization_type = "JWT"
  authorizer_id      = aws_apigatewayv2_authorizer.jwt.id
}

resource "aws_cloudwatch_log_group" "api_access_logs" {
  name              = "/apigateway/${var.name_prefix}"
  retention_in_days = var.log_retention_days

  tags = var.tags
}

resource "aws_apigatewayv2_stage" "default" {
  api_id      = aws_apigatewayv2_api.this.id
  name        = "$default"
  auto_deploy = true

  # Throttling (decision 3.11) -- limite razonable para transferencias
  # bancarias, bien por debajo de lo que tolerarian los microservicios
  # internos, para proteger contra abuso antes de llegar a ellos.
  default_route_settings {
    throttling_burst_limit = 200
    throttling_rate_limit  = 100
  }

  access_log_settings {
    destination_arn = aws_cloudwatch_log_group.api_access_logs.arn
    format = jsonencode({
      requestId               = "$context.requestId"
      ip                      = "$context.identity.sourceIp"
      requestTime             = "$context.requestTime"
      httpMethod              = "$context.httpMethod"
      routeKey                = "$context.routeKey"
      status                  = "$context.status"
      responseLength          = "$context.responseLength"
      integrationErrorMessage = "$context.integrationErrorMessage"
    })
  }

  tags = var.tags
}
