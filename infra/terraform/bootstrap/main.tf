/**
 * Bootstrap stack: the two S3 buckets that everything else depends on.
 *
 * - pgds-tfstate-<account_id>   Terraform remote state for the "main" stack.
 * - pgds-backup-<account_id>    DB dumps (mysqldump) and any other off-instance
 *                               backup artifact. Lightsail snapshots are NOT
 *                               stored here — see Proposal 02 §6.1/§9.2.
 *
 * Bucket names are derived from the account ID (data.aws_caller_identity)
 * rather than hardcoded, because §8.5 flags "pgds-tfstate" as an unverified,
 * possibly-taken global name. Apply once; both buckets carry
 * `prevent_destroy` per §6.2/§9.2 so a stray `terraform destroy` on this
 * stack cannot take state or backups with it.
 */

data "aws_caller_identity" "current" {}

locals {
  tfstate_bucket_name = "pgds-tfstate-${data.aws_caller_identity.current.account_id}"
  backup_bucket_name  = "pgds-backup-${data.aws_caller_identity.current.account_id}"
}

# ---------------------------------------------------------------------------
# Terraform state bucket
# ---------------------------------------------------------------------------

resource "aws_s3_bucket" "tfstate" {
  bucket = local.tfstate_bucket_name

  lifecycle {
    prevent_destroy = true
  }
}

resource "aws_s3_bucket_versioning" "tfstate" {
  bucket = aws_s3_bucket.tfstate.id

  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "tfstate" {
  bucket = aws_s3_bucket.tfstate.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256" # SSE-S3: no KMS key to manage or pay per-request for; adequate for TF state
    }
  }
}

resource "aws_s3_bucket_public_access_block" "tfstate" {
  bucket = aws_s3_bucket.tfstate.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# ---------------------------------------------------------------------------
# Backup bucket (DB dumps, TF plan artifacts, etc.)
# ---------------------------------------------------------------------------

resource "aws_s3_bucket" "backup" {
  bucket = local.backup_bucket_name

  lifecycle {
    prevent_destroy = true
  }
}

resource "aws_s3_bucket_versioning" "backup" {
  bucket = aws_s3_bucket.backup.id

  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "backup" {
  bucket = aws_s3_bucket.backup.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256" # SSE-S3: no KMS key to manage or pay per-request for; adequate for DB dumps
    }
  }
}

resource "aws_s3_bucket_public_access_block" "backup" {
  bucket = aws_s3_bucket.backup.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}
