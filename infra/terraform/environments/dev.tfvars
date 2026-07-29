environment  = "dev"
project_name = "bp"

primary_region   = "us-east-1"
secondary_region = "us-west-2"

# Un ambiente de desarrollo no necesita el mismo nivel de redundancia que
# produccion, pero se mantiene en 2 AZs para poder probar el comportamiento
# Multi-AZ antes de promover a produccion.
availability_zone_count = 2

alert_email = "platform-team@bp.test"
