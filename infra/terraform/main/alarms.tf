/**
 * CloudWatch alarms — the three §7 "Required alarms".
 *
 * §7 lists them as free Lightsail console alarms:
 *   - CPU utilization > 80% for 10 minutes
 *   - Burst capacity < 20%
 *   - Status check failed
 *
 * NONE existed. `describe-alarms` returned an empty list, so the origin had no automated
 * liveness signal at all: pgds-health-alert.sh covers RAM/disk/swap from inside the box
 * (§7's "replacement for RAM/disk"), but by definition it cannot report that the instance
 * itself is unreachable or wedged — the one failure mode that matters most on a
 * single-instance architecture with no load balancer in front of it.
 *
 * Translated from Lightsail's metric names to the EC2 equivalents, since the origin runs on
 * EC2 (see variables.tf: the account cannot create Lightsail bundles above micro_3_0):
 *
 *   Lightsail "burst capacity"  ->  AWS/EC2 CPUCreditBalance
 *   Lightsail "status check"    ->  AWS/EC2 StatusCheckFailed
 *   Lightsail "CPU utilization" ->  AWS/EC2 CPUUtilization
 *
 * Cost is what §7 requires it to be. All three are BUILT-IN metrics — verified with
 * `list-metrics --namespace AWS/EC2`, which returns 22 metrics for this instance including
 * all three, with no CloudWatch agent installed. So this does not incur the custom-metric
 * charge §7 explicitly rejects ("Do not assume that 10 custom metrics + 10 alarms + 5GB
 * logs are free. Over six months ... ~$24, 28% of the budget"). Standard-resolution alarms
 * are $0.10/alarm-month (queried from the Pricing API for ap-southeast-1), and the account
 * had 0 alarms against a 10-alarm free allowance, so three alarms cost $0 — and $1.80 over
 * six months in the worst case if the allowance does not apply.
 *
 * Alarm actions publish to an SNS topic rather than emailing directly, because SES cannot
 * send until an identity is verified (which needs DNS on a domain this account does not
 * control). SNS email subscriptions are independent of SES and confirm by their own link,
 * so the topic is created and wired now and a subscriber can be attached whenever an
 * address is available. An alarm with no action still records state and is visible in the
 * console, so the alarms are useful before anyone subscribes.
 */

locals {
  # Alarms only make sense for the backend that actually exists. Lightsail alarms are a
  # different resource type (aws_lightsail_alarm) and would be added alongside
  # lightsail.tf if the plan-size cap is ever lifted.
  ec2_alarms = local.ec2_instances
}

# ---------------------------------------------------------------------------
# Notification target.
# ---------------------------------------------------------------------------

resource "aws_sns_topic" "alarms" {
  count = local.ec2_alarms
  name  = "pgds-alarms"

  # SNS validates tag VALUES more strictly than EC2 does and rejected a prose Note tag
  # ("Tags Reason: The given tag(s) contain invalid characters"), so the explanation lives
  # in the file header instead of in a tag.
  tags = {
    Project = "pgds"
  }
}

/*
 * Email subscription, only when an address is supplied. AWS sends a confirmation link that
 * a human must click; until then the subscription sits in PendingConfirmation and the
 * alarm still records state. Left unset by default rather than pointed at a placeholder,
 * because a subscription to an address nobody reads is worse than none — it looks like
 * monitoring exists when it does not.
 */
resource "aws_sns_topic_subscription" "alarms_email" {
  count     = local.ec2_alarms > 0 && var.alarm_notification_email != "" ? 1 : 0
  topic_arn = aws_sns_topic.alarms[0].arn
  protocol  = "email"
  endpoint  = var.alarm_notification_email
}

# ---------------------------------------------------------------------------
# §7 alarm 1 — CPU utilization > 80% for 10 minutes.
# ---------------------------------------------------------------------------

resource "aws_cloudwatch_metric_alarm" "cpu_high" {
  count               = local.ec2_alarms
  alarm_name          = "pgds-cpu-high"
  alarm_description   = "CPU > 80% for 10 minutes (Proposal 02 §7). Sustained CPU on a 2 vCPU burstable instance means either a traffic event the FastCGI cache is not absorbing, or a runaway process."
  namespace           = "AWS/EC2"
  metric_name         = "CPUUtilization"
  statistic           = "Average"
  comparison_operator = "GreaterThanThreshold"
  threshold           = 80
  # 2 x 5-minute periods = the 10 minutes §7 asks for. Five-minute periods are the free
  # basic-monitoring resolution; 1-minute periods would require detailed monitoring, which
  # is billed.
  period             = 300
  evaluation_periods = 2

  dimensions = {
    InstanceId = aws_instance.app[0].id
  }

  alarm_actions = [aws_sns_topic.alarms[0].arn]
  ok_actions    = [aws_sns_topic.alarms[0].arn]
  # Missing data on a stopped instance is not "OK" and not a breach either.
  treat_missing_data = "missing"

  tags = { Project = "pgds" }
}

# ---------------------------------------------------------------------------
# §7 alarm 2 — burst capacity < 20%.
# ---------------------------------------------------------------------------

/*
 * t4g.small earns 24 credits/hour and holds a maximum of 576 (24 x 24 h).
 *
 * This is the alarm that matters most on a burstable instance: exhausting credits does not
 * stop the site, it silently throttles it to the 20% baseline, so the symptom is a slow
 * site with perfectly healthy CPU and status checks. Without this alarm that state is
 * invisible.
 *
 * The threshold is NOT §7's literal 20% (115.2). A fresh instance starts near zero and
 * accrues, so a 20% alarm fires on a healthy new box and stays in ALARM until the bucket
 * fills. Measured on this instance over six hours:
 *
 *   16.5 -> 38.4 -> 62.0 -> 84.0 -> 107.6 -> 130.1   (~22.7 credits/hr, spec is 24)
 *
 * i.e. it was BELOW 115.2 for its first ~5 hours and needed ~20 more to saturate. Shipping
 * the literal threshold would have meant an alarm that was already firing the moment it was
 * created — the same alert-fatigue trap §8.4 describes and that budgets.tf was just fixed
 * for. An alarm that is on at birth teaches the operator to ignore it.
 *
 * 60 credits (~10%) with a 30-minute window instead:
 *   - 60 is unreachable by accrual alone: a healthy instance passes it in under 3 hours and
 *     then climbs, so it can only be re-entered by genuinely BURNING credits.
 *   - At 60 credits there are still ~60 CPU-minutes of burst left, which is enough runway
 *     to react before throttling starts.
 *   - 6 x 5-minute periods stops a brief import spike from paging anyone.
 */
resource "aws_cloudwatch_metric_alarm" "cpu_credits_low" {
  count               = local.ec2_alarms
  alarm_name          = "pgds-cpu-credits-low"
  alarm_description   = "CPUCreditBalance < 60 (~10% of the t4g.small maximum of 576) for 30 minutes. Proposal 02 §7 asks for 'burst capacity < 20%'; 20% (115.2) is deliberately NOT used because a new instance accrues through that band and would alarm while healthy — measured at ~22.7 credits/hr from near zero. Below 60 the instance is genuinely burning credits and will throttle to baseline, which shows up as a slow site with healthy CPU and status checks."
  namespace           = "AWS/EC2"
  metric_name         = "CPUCreditBalance"
  statistic           = "Average"
  comparison_operator = "LessThanThreshold"
  threshold           = 60
  period              = 300
  evaluation_periods  = 6

  dimensions = {
    InstanceId = aws_instance.app[0].id
  }

  alarm_actions      = [aws_sns_topic.alarms[0].arn]
  ok_actions         = [aws_sns_topic.alarms[0].arn]
  treat_missing_data = "missing"

  tags = { Project = "pgds" }
}

# ---------------------------------------------------------------------------
# §7 alarm 3 — status check failed.
# ---------------------------------------------------------------------------

/*
 * StatusCheckFailed covers both the system check (AWS-side: host, network, power) and the
 * instance check (guest-side: kernel panic, exhausted memory, corrupt filesystem). This is
 * the only signal that reports "the origin is gone", which pgds-health-alert.sh cannot do
 * by construction — it runs ON the box it is monitoring.
 *
 * evaluation_periods = 2 over 60-second periods: one failed check can be transient, two
 * consecutive is real. StatusCheckFailed is published at 1-minute resolution as a basic
 * metric, so this costs nothing extra.
 */
resource "aws_cloudwatch_metric_alarm" "status_check_failed" {
  count               = local.ec2_alarms
  alarm_name          = "pgds-status-check-failed"
  alarm_description   = "EC2 status check failed for 2 consecutive minutes (Proposal 02 §7). Covers both the system check (host/network) and the instance check (kernel, OOM, filesystem) — the one failure mode the on-box health script cannot report."
  namespace           = "AWS/EC2"
  metric_name         = "StatusCheckFailed"
  statistic           = "Maximum"
  comparison_operator = "GreaterThanThreshold"
  threshold           = 0
  period              = 60
  evaluation_periods  = 2

  dimensions = {
    InstanceId = aws_instance.app[0].id
  }

  alarm_actions = [aws_sns_topic.alarms[0].arn]
  ok_actions    = [aws_sns_topic.alarms[0].arn]
  # A missing status check means CloudWatch is not hearing from the instance, which is
  # itself the condition being watched for.
  treat_missing_data = "breaching"

  tags = { Project = "pgds" }
}
