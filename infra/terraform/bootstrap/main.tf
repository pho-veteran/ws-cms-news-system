/**
 * Bootstrap stack for the Terraform remote-state bucket.
 *
 * The bucket name is derived from the account ID (data.aws_caller_identity)
 * rather than hardcoded, because S3 bucket names are globally unique. The
 * `prevent_destroy` guard keeps an unqualified destroy from deleting state.
 */

data "aws_caller_identity" "current" {}

locals {
  tfstate_bucket_name = "pgds-tfstate-${data.aws_caller_identity.current.account_id}"
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

/**
 * Bound the state history.
 *
 * Versioning on this bucket is deliberate and must stay — it is the rollback path for a
 * corrupted or truncated state file, which on a single-state stack is the difference
 * between a bad apply and a rebuild from scratch. But it was UNBOUNDED: no lifecycle
 * configuration existed, and 37 object versions had already accumulated on day one, since
 * every apply writes a new version of the same key.
 *
 * 90 days of noncurrent versions rather than 7: the current version is never expired (no
 * * `expiration` block here, and state history is the thing you
 * reach for weeks after a mistake, not hours. The project's own life is six months, so 90
 * days is most of it while still terminating.
 *
 * newer_noncurrent_versions keeps the most recent 10 regardless of age, so a burst of
 * applies inside one day cannot be aged out together and leave nothing to roll back to.
 */
resource "aws_s3_bucket_lifecycle_configuration" "tfstate" {
  bucket = aws_s3_bucket.tfstate.id

  depends_on = [aws_s3_bucket_versioning.tfstate]

  rule {
    id     = "expire-old-state-versions"
    status = "Enabled"

    filter {}

    noncurrent_version_expiration {
      noncurrent_days           = 90
      newer_noncurrent_versions = 10
    }
  }

  rule {
    id     = "abort-incomplete-multipart-uploads"
    status = "Enabled"

    filter {}

    abort_incomplete_multipart_upload {
      days_after_initiation = 7
    }
  }
}
