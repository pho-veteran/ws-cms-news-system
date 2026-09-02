/*
 * Scheduled AMI snapshots via Data Lifecycle Manager (Proposal 02 §6.1).
 *
 * §6.1 requires a daily instance snapshot with 4-rolling retention, and §6.3 measures RTO
 * against "the latest snapshot". Neither is true of a snapshot nobody takes: until this
 * file existed, `infra/scripts/pgds-snapshot.sh` had to be run BY HAND from the admin
 * workstation, `user_data.sh` never installed it in cron, and the account held exactly one
 * AMI — from the 2026-08-31 restore drill. The documented 21.7 min RTO assumed a recent
 * snapshot that nothing was creating.
 *
 * WHY DLM RATHER THAN CRON ON THE ORIGIN. §6.2 and the script's own header refuse to put
 * the snapshot job in the origin's crontab, and the reasoning is sound: pruning needs
 * ec2:DeregisterImage + ec2:DeleteSnapshot, and a credential that can delete backups must
 * not sit on an internet-facing box — that is precisely the credential an attacker wants
 * after a WordPress compromise. DLM resolves the dilemma instead of trading it away: AWS
 * runs the schedule and the pruning server-side under the service role below, so the
 * delete capability exists in IAM and never on the instance. The origin gets no new
 * credential at all.
 *
 * This does not delete pgds-snapshot.sh. That script stays the manual, on-demand path —
 * pre-resize, pre-migration, or an ad-hoc restore point — and it is still the only thing
 * that produces a snapshot on a schedule DLM cannot express. The two coexist because they
 * tag differently (see below), so neither prunes the other's images.
 *
 * COST. DLM itself is free; you pay only for the AMIs and their backing EBS snapshots at
 * $0.05/GB-month, which §6.1 already budgets at ~$1.80/mo for 4 rolling images of a ~40GB
 * base. EBS snapshots are incremental, so 4 rolling images bill roughly one full copy plus
 * daily deltas, NOT 4 × 60GB. This is inside the ~$24.89/mo run rate the $40 monthly budget
 * watches.
 *
 * VERIFY IT IS ACTUALLY RUNNING — a schedule that silently stopped looks identical to one
 * that never fired:
 *   aws dlm get-lifecycle-policy --policy-id <id> --region ap-southeast-1 \
 *     --query 'Policy.{state:State,status:StatusMessage}'
 *   aws ec2 describe-images --owners self --region ap-southeast-1 \
 *     --filters Name=tag:CreatedBy,Values=dlm \
 *     --query 'sort_by(Images,&CreationDate)[].{n:Name,d:CreationDate}' --output table
 * Expect up to 4 rows, the newest less than ~25h old.
 */

locals {
  # Tied to the EC2 backend: the policy targets the instance by tag, and Lightsail
  # snapshots are a different service entirely (aws_lightsail_instance_snapshot), which
  # would be added alongside lightsail.tf if the plan-size cap is ever lifted.
  dlm_policies = local.ec2_instances
}

# ---------------------------------------------------------------------------
# Service role. AWS documents a default role (AWSDataLifecycleManagerDefaultRoleForAMI-
# Management) created implicitly by the console, but it does NOT exist in this account —
# `iam get-role` returns NoSuchEntity — and relying on a console side effect would make
# `terraform apply` fail on a fresh account. Declared explicitly so the stack is
# self-contained.
# ---------------------------------------------------------------------------

data "aws_iam_policy_document" "dlm_assume" {
  count = local.dlm_policies

  statement {
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["dlm.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "dlm" {
  count = local.dlm_policies

  name               = "pgds-dlm-ami-management"
  path               = "/pgds/"
  assume_role_policy = data.aws_iam_policy_document.dlm_assume[0].json
}

# The AWS-managed policy for AMI lifecycle policies. Using the managed policy rather than a
# hand-written one is deliberate: DLM's required action set has changed as the service has
# gained features, and a hand-rolled policy silently stops working when it does — the
# schedule keeps reporting ENABLED while creating nothing.
resource "aws_iam_role_policy_attachment" "dlm" {
  count = local.dlm_policies

  role       = aws_iam_role.dlm[0].name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AWSDataLifecycleManagerServiceRoleForAMIManagement"
}

# ---------------------------------------------------------------------------
# Daily AMI, 4 rolling — §6.1's snapshot design, executed by AWS.
# ---------------------------------------------------------------------------

resource "aws_dlm_lifecycle_policy" "ami" {
  count = local.dlm_policies

  # Letters, digits, spaces, underscores and hyphens only. DLM rejects commas, periods and
  # parentheses with a bare "invalid value for description" at validate time — so this
  # cannot carry the usual "(Proposal 02 §6.1)" citation. Verified against the provider's
  # own validator, not just the API.
  description        = "pgds daily AMI 4 rolling - Proposal 02 section 6-1"
  execution_role_arn = aws_iam_role.dlm[0].arn
  state              = "ENABLED"

  policy_details {
    policy_type    = "IMAGE_MANAGEMENT"
    resource_types = ["INSTANCE"]

    # No reboot. §4 verified a --no-reboot AMI restores cleanly, and MariaDB's dump is the
    # point-in-time backup — a block-level image of a running InnoDB volume is
    # crash-consistent, which is why the database is ALSO dumped separately (§6.1).
    # Never set this to false on a single-instance SPOF: it reboots production nightly.
    #
    # This sits in `parameters` at the policy_details level, NOT inside `schedule` — the
    # provider rejects `no_reboot` there with "An argument named no_reboot is not expected
    # here", and a policy that silently defaulted to rebooting would restart the origin
    # every night. Verified against hashicorp/aws v6.62.0.
    parameters {
      no_reboot = true
    }

    # Matched by tag, not instance ID, so a rebuilt origin keeps its schedule without a
    # Terraform change — the Name tag is set by aws_instance.app.
    #
    # This also means the restore procedure stays safe: §4's clean-room instance is
    # launched by `aws ec2 run-instances` with no tags, so DLM does not pick it up and
    # cannot start snapshotting a throwaway test box. If you ever tag a restore instance
    # Name=pgds-prod, it WILL be snapshotted and its images will count against the same
    # retention pool as production's.
    target_tags = {
      Name = var.instance_name
    }

    schedule {
      name = "daily-ami-4-rolling"

      create_rule {
        interval      = 24
        interval_unit = "HOURS"
        # UTC. 19:40 UTC = 02:40 ICT, the site's overnight trough, and clear of both
        # db-backup runs (03:17 / 15:17 UTC) so a dump and an AMI never contend for the
        # same 2GB of RAM and 2 vCPUs. DLM starts within an hour of this time.
        times = ["19:40"]
      }

      # §6.1: 4 rolling, not 7 — sufficient for an ephemeral site at RPO 24h.
      # DLM deletes the AMI *and* its backing EBS snapshot together, which is the trap
      # pgds-snapshot.sh exists to avoid: deregistering an AMI alone orphans the snapshot
      # and keeps billing at $0.05/GB-month invisibly.
      retain_rule {
        count = 4
      }

      copy_tags = true

      # Distinguishes DLM's images from pgds-snapshot.sh's (which tags its own with the
      # pgds-auto prefix). Without a discriminator the manual script's retention sweep
      # could prune DLM's images, and vice versa, leaving neither pool at its intended
      # depth.
      tags_to_add = {
        CreatedBy = "dlm"
        Schedule  = "daily-ami-4-rolling"
      }
    }
  }
}

output "dlm_ami_policy_id" {
  description = "DLM policy ID for the daily AMI schedule. Empty when the backend is not EC2."
  value       = local.dlm_policies > 0 ? aws_dlm_lifecycle_policy.ami[0].id : null
}
