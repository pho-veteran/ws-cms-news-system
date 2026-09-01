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
 * `expiration` block here, unlike the backup bucket), and state history is the thing you
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

/**
 * Retention. §6.1's table gives DB dumps "2x/day, 7 days".
 *
 * This did not exist: `get-bucket-lifecycle-configuration` returned
 * NoSuchLifecycleConfiguration, so nothing was expiring anything. That is not a
 * self-correcting gap, because the design deliberately removes the other possible
 * mechanism — pgds-db-backup.sh says so in its own header:
 *
 *   "Retention is NOT enforced here. The backup IAM user is deliberately PutObject-only
 *    with no DeleteObject (§6.2), so this script cannot prune what it writes even if it
 *    wanted to — that is the point: a compromised instance cannot erase its own backups.
 *    Expiry is the bucket's job."
 *
 * So with no lifecycle rule, twice-daily dumps accumulate forever and NOBODY can delete
 * them: not the script, not the instance. At ~20 KB/dump the cost is trivial today, but
 * the same bucket takes the media-bearing dumps after the §9 import, and an
 * ever-growing bucket with no expiry is a slow leak that only surfaces on the bill.
 *
 * Versioning is enabled on this bucket, which means an expired object leaves a
 * noncurrent version behind. Both are expired, or the "deletion" only hides the data and
 * keeps paying for it.
 */
resource "aws_s3_bucket_lifecycle_configuration" "backup" {
  bucket = aws_s3_bucket.backup.id

  # Explicit dependency: S3 rejects a lifecycle configuration that references versioning
  # behaviour before versioning is actually enabled.
  depends_on = [aws_s3_bucket_versioning.backup]

  rule {
    id     = "expire-db-dumps-after-7-days"
    status = "Enabled"

    filter {
      prefix = "db-dumps/"
    }

    # §6.1: 7 days. Twice-daily dumps means 14 recovery points, which comfortably covers
    # the RTO/RPO drill in §6.3.
    expiration {
      days = 7
    }

    # Versioning is on, so expiring the current version only creates a noncurrent one.
    # Without this the data — and its storage cost — never actually goes away.
    noncurrent_version_expiration {
      noncurrent_days = 7
    }
  }

  # Multipart uploads that fail partway leave parts that are billed but invisible in
  # `s3 ls`. A dump is small enough that this should never trigger, which is exactly why
  # it would go unnoticed if it did.
  rule {
    id     = "abort-incomplete-multipart-uploads"
    status = "Enabled"

    filter {}

    abort_incomplete_multipart_upload {
      days_after_initiation = 7
    }
  }
}
