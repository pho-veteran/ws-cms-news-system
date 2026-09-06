/**
 * Least-privilege IAM user for SES health alerts (Proposal 02 §8.4, §10.2).
 *
 * The static access key is stored on the instance in a root-owned mode-600 file.
 * This is a known weakness accepted by §10.2 and constrained to SES send actions.
 */

# ---------------------------------------------------------------------------
# SES user — send only.
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
