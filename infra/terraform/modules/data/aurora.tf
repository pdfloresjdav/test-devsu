# Decision 3.10: Aurora MySQL Multi-AZ para el core transaccional
# (svc-transfers: cuentas y transferencias necesitan ACID real). Global
# Database (seccion 9.3) replica de forma continua hacia la region DR con
# RPO cercano a 1 segundo, sin el overhead de una replica logica manual.

resource "random_password" "aurora_master" {
  length  = 32
  special = false # Aurora rechaza algunos caracteres especiales en la contrasena maestra
}

resource "aws_secretsmanager_secret" "aurora_master" {
  provider = aws.primary

  name = "${var.name_prefix}/aurora/master-credentials"

  tags = var.tags
}

resource "aws_secretsmanager_secret_version" "aurora_master" {
  provider = aws.primary

  secret_id = aws_secretsmanager_secret.aurora_master.id
  secret_string = jsonencode({
    username = "bp_admin"
    password = random_password.aurora_master.result
  })
}

resource "aws_db_subnet_group" "primary" {
  provider = aws.primary

  name       = "${var.name_prefix}-aurora-primary"
  subnet_ids = var.primary_private_subnet_ids

  tags = var.tags
}

resource "aws_db_subnet_group" "secondary" {
  provider = aws.secondary

  name       = "${var.name_prefix}-aurora-secondary"
  subnet_ids = var.dr_private_subnet_ids

  tags = var.tags
}

resource "aws_rds_global_cluster" "this" {
  provider = aws.primary

  global_cluster_identifier = "${var.name_prefix}-aurora-global"
  engine                    = "aurora-mysql"
  engine_version            = "8.0.mysql_aurora.3.05.2"
  database_name             = "svc_transfers"
  storage_encrypted         = true
}

resource "aws_rds_cluster" "primary" {
  provider = aws.primary

  cluster_identifier        = "${var.name_prefix}-aurora-primary"
  engine                    = aws_rds_global_cluster.this.engine
  engine_version            = aws_rds_global_cluster.this.engine_version
  global_cluster_identifier = aws_rds_global_cluster.this.id
  database_name             = "svc_transfers"
  master_username           = "bp_admin"
  master_password           = random_password.aurora_master.result
  db_subnet_group_name      = aws_db_subnet_group.primary.name
  vpc_security_group_ids    = [var.primary_data_security_group_id]
  storage_encrypted         = true
  backup_retention_period   = var.backup_retention_days
  preferred_backup_window   = "03:00-04:00" # baja carga esperada, horario UTC
  skip_final_snapshot       = var.environment != "prod"
  deletion_protection       = var.environment == "prod"

  tags = var.tags
}

resource "aws_rds_cluster_instance" "primary" {
  provider = aws.primary
  count    = 2 # Multi-AZ real (seccion 9.2): al menos 2 instancias en AZs distintas

  identifier           = "${var.name_prefix}-aurora-primary-${count.index}"
  cluster_identifier   = aws_rds_cluster.primary.id
  instance_class       = var.environment == "prod" ? "db.r6g.large" : "db.t4g.medium"
  engine               = aws_rds_cluster.primary.engine
  engine_version       = aws_rds_cluster.primary.engine_version
  db_subnet_group_name = aws_db_subnet_group.primary.name

  tags = var.tags
}

# Cluster secundario de la Global Database en la region DR -- pasivo
# (solo lectura) hasta un failover real (seccion 9.3). No tiene
# master_username/master_password propios: hereda las credenciales del
# cluster primario via la Global Database.
resource "aws_rds_cluster" "secondary" {
  provider = aws.secondary

  cluster_identifier        = "${var.name_prefix}-aurora-secondary"
  engine                    = aws_rds_global_cluster.this.engine
  engine_version            = aws_rds_global_cluster.this.engine_version
  global_cluster_identifier = aws_rds_global_cluster.this.id
  db_subnet_group_name      = aws_db_subnet_group.secondary.name
  vpc_security_group_ids    = [var.primary_data_security_group_id] # placeholder: SG real de DR se resuelve en el modulo ha-dr (ver comentario ahi)
  storage_encrypted         = true
  skip_final_snapshot       = true

  depends_on = [aws_rds_cluster_instance.primary]

  tags = var.tags
}

resource "aws_rds_cluster_instance" "secondary" {
  provider = aws.secondary

  identifier           = "${var.name_prefix}-aurora-secondary-0"
  cluster_identifier   = aws_rds_cluster.secondary.id
  instance_class       = var.environment == "prod" ? "db.r6g.large" : "db.t4g.medium"
  engine               = aws_rds_cluster.secondary.engine
  engine_version       = aws_rds_cluster.secondary.engine_version
  db_subnet_group_name = aws_db_subnet_group.secondary.name

  tags = var.tags
}
