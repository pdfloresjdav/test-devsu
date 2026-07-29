environment  = "prod"
project_name = "bp"

primary_region   = "us-east-1"
secondary_region = "us-west-2"

availability_zone_count = 2

alert_email = "platform-team@bp.test"

# auth0_domain / auth0_management_client_id / auth0_management_client_secret
# y las contrasenas/secrets de infraestructura NO van en este archivo (se
# commitea a git) -- se inyectan por variables de entorno TF_VAR_* en el
# pipeline de CI/CD (item 14.9) o via un *.auto.tfvars local no commiteado.
