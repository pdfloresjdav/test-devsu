# Decision 3.9: S3 Object Lock en modo Compliance para el WORM Archiver de
# Auditoria -- ni siquiera el usuario root puede borrar un objeto antes de
# vencer la retencion (seccion 9.1: no repudio). Cross-Region Replication
# (seccion 9.3) mantiene una copia continua en la region DR.

resource "aws_s3_bucket" "audit_worm_primary" {
  provider = aws.primary

  bucket = "${var.name_prefix}-audit-worm-${data.aws_caller_identity.current.account_id}"

  object_lock_enabled = true # tiene que declararse en la creacion del bucket, no se puede activar despues

  tags = var.tags
}

data "aws_caller_identity" "current" {
  provider = aws.primary
}

resource "aws_s3_bucket_versioning" "audit_worm_primary" {
  provider = aws.primary

  bucket = aws_s3_bucket.audit_worm_primary.id

  versioning_configuration {
    status = "Enabled" # requisito de AWS para poder usar Object Lock
  }
}

resource "aws_s3_bucket_object_lock_configuration" "audit_worm_primary" {
  provider = aws.primary

  bucket = aws_s3_bucket.audit_worm_primary.id

  rule {
    default_retention {
      mode = "COMPLIANCE" # ver seccion 9.1 del documento: ni el root puede eliminar antes de vencer
      days = 1825         # 5 anios -- el minimo del rango 5-10 anios que documenta la seccion 9.1, ajustable por variable si la regulacion final de BP exige mas
    }
  }
}

resource "aws_s3_bucket_public_access_block" "audit_worm_primary" {
  provider = aws.primary

  bucket = aws_s3_bucket.audit_worm_primary.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket" "audit_worm_secondary" {
  provider = aws.secondary

  bucket              = "${var.name_prefix}-audit-worm-dr-${data.aws_caller_identity.current.account_id}"
  object_lock_enabled = true

  tags = var.tags
}

resource "aws_s3_bucket_versioning" "audit_worm_secondary" {
  provider = aws.secondary

  bucket = aws_s3_bucket.audit_worm_secondary.id

  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_object_lock_configuration" "audit_worm_secondary" {
  provider = aws.secondary

  bucket = aws_s3_bucket.audit_worm_secondary.id

  rule {
    default_retention {
      mode = "COMPLIANCE"
      days = 1825
    }
  }
}

resource "aws_iam_role" "s3_replication" {
  provider = aws.primary

  name = "${var.name_prefix}-s3-audit-replication"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Service = "s3.amazonaws.com" }
      Action    = "sts:AssumeRole"
    }]
  })

  tags = var.tags
}

resource "aws_iam_role_policy" "s3_replication" {
  provider = aws.primary

  name = "${var.name_prefix}-s3-audit-replication"
  role = aws_iam_role.s3_replication.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect   = "Allow"
        Action   = ["s3:GetReplicationConfiguration", "s3:ListBucket"]
        Resource = [aws_s3_bucket.audit_worm_primary.arn]
      },
      {
        Effect   = "Allow"
        Action   = ["s3:GetObjectVersionForReplication", "s3:GetObjectVersionAcl", "s3:GetObjectVersionTagging"]
        Resource = ["${aws_s3_bucket.audit_worm_primary.arn}/*"]
      },
      {
        Effect   = "Allow"
        Action   = ["s3:ReplicateObject", "s3:ReplicateDelete", "s3:ReplicateTags", "s3:ObjectOwnerOverrideToBucketOwner"]
        Resource = ["${aws_s3_bucket.audit_worm_secondary.arn}/*"]
      }
    ]
  })
}

resource "aws_s3_bucket_replication_configuration" "audit_worm" {
  provider = aws.primary

  # Object Lock exige que la replicacion este configurada DESPUES de que
  # el versionado quede activo en ambos buckets, o falla en el apply.
  depends_on = [aws_s3_bucket_versioning.audit_worm_primary, aws_s3_bucket_versioning.audit_worm_secondary]

  bucket = aws_s3_bucket.audit_worm_primary.id
  role   = aws_iam_role.s3_replication.arn

  rule {
    id     = "audit-worm-cross-region"
    status = "Enabled"

    destination {
      bucket        = aws_s3_bucket.audit_worm_secondary.arn
      storage_class = "STANDARD"
    }
  }
}
