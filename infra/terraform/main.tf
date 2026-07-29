# --- Red: region primaria + region DR (mismo modulo, dos instancias con
# un provider distinto cada una -- ver comentario en modules/ha-dr sobre
# por que el computo de DR no se replica de la misma forma) ---

module "network" {
  source = "./modules/network"

  name_prefix             = "${var.project_name}-${var.environment}"
  vpc_cidr                = var.vpc_cidr
  availability_zone_count = var.availability_zone_count
  tags                    = local.common_tags

  providers = {
    aws = aws.primary
  }
}

module "network_dr" {
  source = "./modules/network"

  name_prefix             = "${var.project_name}-${var.environment}-dr"
  vpc_cidr                = var.dr_vpc_cidr
  availability_zone_count = var.availability_zone_count
  tags                    = local.common_tags

  providers = {
    aws = aws.secondary
  }
}

# --- Mensajeria (14.4) -- independiente de datos/computo ---

module "messaging" {
  source = "./modules/messaging"

  name_prefix     = "${var.project_name}-${var.environment}"
  ses_from_domain = var.ses_from_domain
  tags            = local.common_tags

  providers = {
    aws = aws.primary
  }
}

# --- Datos (14.3) -- Aurora Global DB, DynamoDB Global Tables, S3 CRR,
# necesita ambos providers por los recursos replicados entre regiones ---

module "data" {
  source = "./modules/data"

  name_prefix                    = "${var.project_name}-${var.environment}"
  environment                    = var.environment
  primary_private_subnet_ids     = module.network.private_subnet_ids
  primary_data_security_group_id = module.network.data_security_group_id
  dr_vpc_id                      = module.network_dr.vpc_id
  dr_vpc_cidr                    = var.dr_vpc_cidr
  dr_private_subnet_ids          = module.network_dr.private_subnet_ids
  dr_region                      = var.secondary_region
  tags                           = local.common_tags

  providers = {
    aws.primary   = aws.primary
    aws.secondary = aws.secondary
  }
}

# --- Seguridad (14.5) -- KMS/WAF/CloudFront/IAM Task Roles/mTLS/OIDC.
# Los ARNs de los recursos de datos/mensajeria se resuelven por
# convencion de nombre dentro del propio modulo (ver comentario en
# modules/security/iam_task_roles.tf) para evitar una dependencia
# circular con los modulos data/messaging. Por eso security NO recibe
# outputs de esos dos modulos como input aca. ---

module "security" {
  source = "./modules/security"

  name_prefix          = "${var.project_name}-${var.environment}"
  environment          = var.environment
  third_party_api_keys = var.third_party_api_keys
  github_repository    = var.github_repository
  tags                 = local.common_tags

  providers = {
    aws = aws.primary
  }
}

# --- Computo (14.2) -- depende de security (task roles) y messaging
# (nombres de cola para autoscaling) ---

module "compute" {
  source = "./modules/compute"

  name_prefix                 = "${var.project_name}-${var.environment}"
  vpc_id                      = module.network.vpc_id
  public_subnet_ids           = module.network.public_subnet_ids
  private_subnet_ids          = module.network.private_subnet_ids
  ecs_tasks_security_group_id = module.network.ecs_tasks_security_group_id
  edge_security_group_id      = module.network.edge_security_group_id
  services                    = var.services
  task_role_arns              = module.security.task_role_arns
  container_secrets           = module.security.container_secret_arns
  worker_queue_names          = module.messaging.worker_queue_names
  auth0_issuer_url            = var.auth0_domain != "" ? "https://${var.auth0_domain}/" : "https://placeholder.auth0.com/"
  auth0_audience              = "bp-web"
  environment                 = var.environment
  tags                        = local.common_tags

  providers = {
    aws = aws.primary
  }
}

# --- Borde: WAF + CloudFront (14.5) -- separado de security para evitar
# la dependencia circular compute<->security (ver comentario en
# modules/edge/variables.tf) ---

module "edge" {
  source = "./modules/edge"

  name_prefix          = "${var.project_name}-${var.environment}"
  environment          = var.environment
  api_gateway_endpoint = module.compute.api_gateway_endpoint
  tags                 = local.common_tags

  providers = {
    aws           = aws.primary
    aws.us_east_1 = aws.us_east_1
  }
}

# --- Observabilidad (14.7) -- depende de compute (nombres de servicio) y
# messaging (DLQs) ---

module "observability" {
  source = "./modules/observability"

  name_prefix          = "${var.project_name}-${var.environment}"
  alert_email          = var.alert_email
  ecs_cluster_name     = module.compute.cluster_name
  service_names        = module.compute.service_names
  api_gateway_endpoint = module.compute.api_gateway_endpoint
  worker_dlq_arns      = module.messaging.worker_dlq_arns
  tags                 = local.common_tags

  providers = {
    aws = aws.primary
  }
}

# --- HA/DR (14.6) -- Route 53 failover entre el edge primario (CloudFront,
# modulo security) y el secundario (ver simplificacion documentada en
# modules/ha-dr sobre por que el computo de DR no esta activo 24/7) ---

module "ha_dr" {
  source = "./modules/ha-dr"

  name_prefix        = "${var.project_name}-${var.environment}"
  domain_name        = var.domain_name
  primary_endpoint   = module.edge.cloudfront_domain_name
  secondary_endpoint = module.edge.cloudfront_domain_name # placeholder hasta activar el runbook de DR -- ver modules/ha-dr/main.tf
  tags               = local.common_tags

  providers = {
    aws = aws.primary
  }
}

# --- Identidad (14.8) -- Auth0 real, solo se aplica si se proveyeron
# credenciales de gestion (el provider auth0 falla el `init`/`plan` si
# el dominio esta vacio, por eso este modulo -- y solo este -- es opcional
# en ambientes donde todavia no existe un tenant de Auth0 real) ---

module "identity" {
  source = "./modules/identity"
  count  = var.auth0_domain != "" ? 1 : 0

  name_prefix          = "${var.project_name}-${var.environment}"
  environment          = var.environment
  web_redirect_uris    = var.web_redirect_uris
  mobile_redirect_uris = var.mobile_redirect_uris
}
