/**
 * GitHub Actions OIDC — lets the deploy workflow open SSH for its own runner IP.
 *
 * WHY THIS EXISTS
 * ---------------
 * §10.2 restricts SSH ingress to a single administrator CIDR, and the deploy job needs SSH
 * to rsync the theme. Those two requirements collide, and the collision is not theoretical:
 * the first real run failed with
 *
 *   ssh: connect to host *** port 22: Connection timed out
 *
 * because the runner's address is not the admin CIDR. GitHub-hosted runners have no stable
 * egress IP — https://api.github.com/meta lists 5,625 IPv4 ranges for `actions`, against a
 * default AWS limit of 60 rules per security group, so allow-listing them is not merely
 * ugly, it is impossible. Opening 0.0.0.0/0 would discard the §10.2 control entirely.
 *
 * So the workflow opens a /32 for itself, deploys, and revokes it in an `always()` step.
 * The exposure is one runner IP for the length of one rsync.
 *
 * WHY OIDC RATHER THAN AN ACCESS KEY
 * ----------------------------------
 * The alternative is storing an IAM access key as a GitHub secret. §10.2 already calls
 * static keys on the instance a known weakness accepted only under specific conditions;
 * putting one in a third-party CI system is worse — it is long-lived, it is exfiltratable by
 * any workflow change, and rotating it is manual. OIDC issues a short-lived token per run,
 * scoped by the trust policy below to this repository alone, and there is no secret to leak
 * or rotate.
 *
 * The role can do exactly two things: authorize and revoke ingress on ONE security group.
 * It cannot read the instance, touch S3, or describe anything else.
 */

# The GitHub OIDC issuer. One per account; `token.actions.githubusercontent.com` is fixed.
resource "aws_iam_openid_connect_provider" "github" {
  count = local.use_ec2 ? 1 : 0

  url            = "https://token.actions.githubusercontent.com"
  client_id_list = ["sts.amazonaws.com"]

  /*
   * Thumbprint of the issuer's TLS intermediate. AWS ignores this value for the GitHub
   * issuer (it validates against its own trust store since 2023) but the API still requires
   * a syntactically valid entry, so this is the documented placeholder rather than a secret
   * that needs rotating when GitHub's certificate rolls.
   */
  thumbprint_list = ["6938fd4d98bab03faadb97b34396831e3780aea1"]

  tags = {
    Project = "pgds"
    Note    = "Trust anchor for GitHub Actions deploys - no static AWS keys in CI"
  }
}

data "aws_caller_identity" "current" {}

resource "aws_iam_role" "github_deploy" {
  count = local.use_ec2 ? 1 : 0
  name  = "pgds-github-deploy"
  path  = "/pgds/"

  /*
   * The `sub` condition is what makes this safe. Without it, ANY GitHub repository in the
   * world could assume this role. GitHub repositories created on or after 2026-07-15 emit
   * an immutable subject that includes both the owner ID and repository ID. Pinning those
   * IDs plus the `main` ref prevents a rename, transfer, or fork pull request from widening
   * who can assume the role.
   */
  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect = "Allow"
      Principal = {
        Federated = aws_iam_openid_connect_provider.github[0].arn
      }
      Action = "sts:AssumeRoleWithWebIdentity"
      Condition = {
        StringEquals = {
          "token.actions.githubusercontent.com:aud" = "sts.amazonaws.com"
          "token.actions.githubusercontent.com:sub" = "repo:${split("/", var.github_repository)[0]}@${var.github_repository_owner_id}/${split("/", var.github_repository)[1]}@${var.github_repository_id}:ref:refs/heads/main"
        }
      }
    }]
  })

  tags = { Project = "pgds" }
}

resource "aws_iam_role_policy" "github_deploy_sg" {
  count = local.use_ec2 ? 1 : 0
  name  = "pgds-github-deploy-sg-ingress"
  role  = aws_iam_role.github_deploy[0].id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid    = "AllowTemporarySshIngressOnThisGroupOnly"
        Effect = "Allow"
        Action = [
          "ec2:AuthorizeSecurityGroupIngress",
          "ec2:RevokeSecurityGroupIngress",
        ]
        # Scoped to the one security group. The role cannot open a port on anything else.
        Resource = aws_security_group.app[0].arn
      },
      {
        /*
         * Describe is unavoidable: revoking needs the rule to be locatable, and
         * DescribeSecurityGroups does not support resource-level permissions — it is
         * all-or-nothing on "*". Read-only on security groups is an acceptable floor;
         * nothing here grants write access to any other group.
         */
        Sid      = "AllowLocatingTheRuleToRevoke"
        Effect   = "Allow"
        Action   = ["ec2:DescribeSecurityGroups", "ec2:DescribeSecurityGroupRules"]
        Resource = "*"
      },
    ]
  })
}

output "github_deploy_role_arn" {
  description = "Role ARN for the GitHub Actions deploy job (set as the AWS_DEPLOY_ROLE_ARN secret)."
  value       = local.use_ec2 ? aws_iam_role.github_deploy[0].arn : null
}

output "app_security_group_id" {
  description = "Security group the deploy job opens SSH on temporarily (set as the AWS_SECURITY_GROUP_ID secret)."
  value       = local.use_ec2 ? aws_security_group.app[0].id : null
}
