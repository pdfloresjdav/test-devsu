# Item 14.9: el pipeline de despliegue (.github/workflows/deploy.yml)
# asume este rol via OIDC -- GitHub Actions nunca recibe credenciales
# estaticas de AWS (access key/secret key) guardadas en secrets, que es el
# riesgo real que esta pieza evita (una credencial de larga vida filtrada
# en un log o un fork malicioso).

variable "github_repository" {
  description = "owner/repo de GitHub habilitado a asumir el rol de despliegue."
  type        = string
  default     = "pdfloresjdav/test-devsu"
}

data "tls_certificate" "github_actions" {
  url = "https://token.actions.githubusercontent.com/.well-known/openid-configuration"
}

resource "aws_iam_openid_connect_provider" "github_actions" {
  url             = "https://token.actions.githubusercontent.com"
  client_id_list  = ["sts.amazonaws.com"]
  thumbprint_list = [data.tls_certificate.github_actions.certificates[0].sha1_fingerprint]

  tags = var.tags
}

data "aws_iam_policy_document" "github_actions_trust" {
  statement {
    effect  = "Allow"
    actions = ["sts:AssumeRoleWithWebIdentity"]

    principals {
      type        = "Federated"
      identifiers = [aws_iam_openid_connect_provider.github_actions.arn]
    }

    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:aud"
      values   = ["sts.amazonaws.com"]
    }

    # Restringido a la rama main -- ni un PR de un fork ni una rama de
    # feature pueden desplegar, solo lo que ya paso por code review y
    # quedo mergeado (mismo espiritu que backend-ci.yml/frontend-ci.yml
    # solo corriendo en push/pull_request a main).
    condition {
      test     = "StringLike"
      variable = "token.actions.githubusercontent.com:sub"
      values   = ["repo:${var.github_repository}:ref:refs/heads/main"]
    }
  }
}

resource "aws_iam_role" "github_actions_deploy" {
  name               = "${var.name_prefix}-github-actions-deploy"
  assume_role_policy = data.aws_iam_policy_document.github_actions_trust.json

  tags = var.tags
}

data "aws_iam_policy_document" "github_actions_deploy" {
  statement {
    effect = "Allow"
    actions = [
      "ecr:GetAuthorizationToken",
      "ecr:BatchCheckLayerAvailability",
      "ecr:PutImage",
      "ecr:InitiateLayerUpload",
      "ecr:UploadLayerPart",
      "ecr:CompleteLayerUpload",
    ]
    resources = ["*"] # GetAuthorizationToken no soporta scoping por recurso
  }

  statement {
    effect = "Allow"
    actions = [
      "ecs:UpdateService",
      "ecs:DescribeServices",
    ]
    # Acotado al propio cluster de este ambiente -- el pipeline no puede
    # tocar otros ambientes ni otros clusters de la cuenta.
    resources = ["arn:aws:ecs:*:${data.aws_caller_identity.current.account_id}:service/${var.name_prefix}-cluster/*"]
  }
}

resource "aws_iam_role_policy" "github_actions_deploy" {
  name   = "deploy"
  role   = aws_iam_role.github_actions_deploy.id
  policy = data.aws_iam_policy_document.github_actions_deploy.json
}
