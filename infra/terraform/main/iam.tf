/**
 * Two separate least-privilege IAM users (Proposal 02 §6.2, §8.4, §10.2):
 *
 * - pgds-backup: s3:PutObject ONLY, scoped to the backup bucket's prefix.
 *   No DeleteObject — a leaked key can write junk but cannot destroy
 *   existing backups (versioning ON on the bucket covers overwrite too).
 * - pgds-ses: SES send permissions only.
 *
 * Both are static access keys stored on the instance (Lightsail has no
 * instance-role equivalent to EC2's). This is a known weakness, accepted in
 * §10.2 and compensated for with least privilege + mode 600 storage +
 * rotation on suspected exposure — none of which Terraform can enforce on
 * the instance side.
 */

# ---------------------------------------------------------------------------
# Backup user — PutObject only, no delete.
# ---------------------------------------------------------------------------

resource "aws_iam_user" "backup" {
  name = "pgds-backup"
  path = "/pgds/"
}

data "aws_iam_policy_document" "backup" {
  statement {
    sid       = "AllowPutOnlyToBackupPrefix"
    effect    = "Allow"
    actions   = ["s3:PutObject"]
    resources = ["${var.backup_bucket_arn}/${var.backup_object_prefix}"]
  }

  statement {
    sid       = "AllowListBucketForBackupPrefix"
    effect    = "Allow"
    actions   = ["s3:ListBucket"]
    resources = [var.backup_bucket_arn]

    condition {
      test     = "StringLike"
      variable = "s3:prefix"
      values   = [var.backup_object_prefix]
    }
  }
}

resource "aws_iam_user_policy" "backup" {
  name   = "pgds-backup-put-only"
  user   = aws_iam_user.backup.name
  policy = data.aws_iam_policy_document.backup.json
}

resource "aws_iam_access_key" "backup" {
  user = aws_iam_user.backup.name
}

# ---------------------------------------------------------------------------
# SES user — send only, separate credentials from the backup user (§8.4).
# ---------------------------------------------------------------------------

resource "aws_iam_user" "ses" {
  name = "pgds-ses"
  path = "/pgds/"
}

data "aws_iam_policy_document" "ses" {
  statement {
    sid    = "AllowSesSendOnly"
    effect = "Allow"
    actions = [
      "ses:SendEmail",
      "ses:SendRawEmail",
    ]
    resources = ["*"]
  }
}

resource "aws_iam_user_policy" "ses" {
  name   = "pgds-ses-send-only"
  user   = aws_iam_user.ses.name
  policy = data.aws_iam_policy_document.ses.json
}

resource "aws_iam_access_key" "ses" {
  user = aws_iam_user.ses.name
}
