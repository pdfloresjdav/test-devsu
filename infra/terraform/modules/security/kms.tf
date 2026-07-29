# Seccion 9.4: "llaves separadas por dominio de dato" -- si una llave se
# ve comprometida, el radio de exposicion queda acotado a un solo dominio
# en vez de a todo el sistema (relevante para el alcance de una
# certificacion PCI-DSS, seccion 9.1).

resource "aws_kms_key" "transactional" {
  description             = "Cifrado del dominio transaccional: Aurora (cuentas/transferencias) y DynamoDB de movimientos"
  deletion_window_in_days = 30
  enable_key_rotation     = true

  tags = merge(var.tags, { Domain = "transactional" })
}

resource "aws_kms_alias" "transactional" {
  name          = "alias/${var.name_prefix}-transactional"
  target_key_id = aws_kms_key.transactional.key_id
}

resource "aws_kms_key" "audit" {
  description             = "Cifrado del dominio de auditoria: DynamoDB de auditoria + bucket S3 WORM (decision 3.9)"
  deletion_window_in_days = 30
  enable_key_rotation     = true

  tags = merge(var.tags, { Domain = "audit" })
}

resource "aws_kms_alias" "audit" {
  name          = "alias/${var.name_prefix}-audit"
  target_key_id = aws_kms_key.audit.key_id
}

resource "aws_kms_key" "secrets" {
  description             = "Cifrado de Secrets Manager: credenciales de base de datos, API keys de proveedores externos (KYC, Auth0)"
  deletion_window_in_days = 30
  enable_key_rotation     = true

  tags = merge(var.tags, { Domain = "secrets" })
}

resource "aws_kms_alias" "secrets" {
  name          = "alias/${var.name_prefix}-secrets"
  target_key_id = aws_kms_key.secrets.key_id
}
