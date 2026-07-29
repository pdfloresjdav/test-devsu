output "task_role_arns" {
  description = "Un ARN de rol por servicio, consumido por el modulo compute como task_role_arns."
  value = {
    svc-customer-data = aws_iam_role.svc_customer_data.arn
    svc-movements     = aws_iam_role.svc_movements.arn
    svc-transfers     = aws_iam_role.svc_transfers.arn
    svc-audit         = aws_iam_role.svc_audit.arn
    svc-notifications = aws_iam_role.svc_notifications.arn
    bff-web           = aws_iam_role.bff_web.arn
    bff-mobile        = aws_iam_role.bff_mobile.arn
  }
}

output "container_secret_arns" {
  description = "ARNs de los secretos de terceros, para inyectar como variables de entorno seguras en las task definitions."
  value       = { for k, s in aws_secretsmanager_secret.third_party : k => s.arn }
}

output "kms_transactional_key_arn" {
  value = aws_kms_key.transactional.arn
}

output "kms_audit_key_arn" {
  value = aws_kms_key.audit.arn
}

output "kms_secrets_key_arn" {
  value = aws_kms_key.secrets.arn
}

output "internal_ca_arn" {
  value = aws_acmpca_certificate_authority.internal.arn
}

output "mesh_name" {
  value = aws_appmesh_mesh.this.id
}

output "github_actions_deploy_role_arn" {
  value = aws_iam_role.github_actions_deploy.arn
}
