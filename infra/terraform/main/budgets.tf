/**
 * AWS Budgets guardrails (Proposal 02 §8.4).
 *
 * Thresholds are set from MEASURED prices, not from the proposal's Lightsail figures.
 *
 * §8.4 asks for alerts "at $50 and $85". Those come from §8.1's ~$85 six-month total,
 * which assumes Lightsail small_3_0 at $12/mo. That bundle is not creatable on this
 * account (see variables.tf: CreateInstances refuses every size above micro_3_0), so the
 * origin runs on EC2 and the real six-month cost is higher. Queried from the Pricing API
 * for ap-southeast-1 on 2026-09-01:
 *
 *   t4g.small   730 h/mo x 6 @ $0.0212/h  = $92.86
 *   gp3 60 GB           x 6 mo @ $0.096/GB = $34.56
 *   Elastic IP  730 h/mo x 6 @ $0.005/h   = $21.90
 *   ---------------------------------------------------
 *   projected six-month gross              = $149.32   (~$24.89/mo)
 *
 * Leaving the second threshold at $85 would therefore alert around month four and then
 * stay on for the rest of the project — precisely the failure §8.4 warns about for the
 * pre-existing $1 zero-spend budget: an alarm that is always on gets muted, and the
 * guardrail is gone. Thresholds that are known-unreachable or known-certain are both
 * useless; these three are set where crossing one actually means something:
 *
 *   $50  — early signal. On the measured run rate this is ~month 2, i.e. still early
 *          enough to change course, and it is the §8.4 figure unchanged.
 *   $160 — the projection has been exceeded. $149.32 is the plan, so crossing $160 means
 *          something is wrong (runaway egress, a forgotten instance) rather than merely
 *          that time has passed.
 *   $190 — the $200 credits are nearly gone. This is the only threshold that corresponds
 *          to real cash leaving the account, which §8.3 identifies as the figure that
 *          matters ("Net cash outlay"). It is the hard line, not the $100.
 *
 * On the $100 "cap": §8.1 line 318 resolves it itself — "$85 gross against $200 credits
 * is ample margin — it is no longer a design-blocking constraint. Retain the $100 cap as
 * operational discipline to detect unexpected costs." It is a monitoring threshold, not an
 * architectural limit, and $149.32 still leaves $50.68 of credit margin. The $50 alert
 * preserves that discipline; nothing about it requires the architecture to fit under $100.
 *
 * ANNUALLY, not MONTHLY, for the cumulative ones: it is the longest window AWS Budgets
 * offers, and the project's life is six months, so it never resets inside the project.
 * QUARTERLY would reset twice.
 *
 * Does NOT touch the pre-existing "My Zero-Spend Budgettt" — Terraform never imports
 * resources it did not create. That budget was deleted manually; see README.md.
 */

# ---------------------------------------------------------------------------
# Lifetime cumulative spend. §8.4's $50 early signal, plus two thresholds sized
# from the measured $149.32 projection rather than from Lightsail's $85.
# ---------------------------------------------------------------------------

resource "aws_budgets_budget" "lifetime_50" {
  name         = "pgds-lifetime-50"
  budget_type  = "COST"
  limit_amount = "50"
  limit_unit   = "USD"
  # ANNUALLY, not MONTHLY: cumulative project spend. Six-month life, so it never resets.
  time_unit = "ANNUALLY"

  notification {
    comparison_operator        = "GREATER_THAN"
    notification_type          = "ACTUAL"
    threshold                  = 100
    threshold_type             = "PERCENTAGE"
    subscriber_email_addresses = var.budget_notification_emails
  }
}

# Was $85 (§8.1's Lightsail total). At the measured EC2 run rate that is crossed around
# month four by normal operation, so it reported nothing except elapsed time. $160 sits
# just above the $149.32 projection: crossing it means the projection is WRONG.
resource "aws_budgets_budget" "lifetime_projection_exceeded" {
  name         = "pgds-lifetime-160-projection-exceeded"
  budget_type  = "COST"
  limit_amount = "160"
  limit_unit   = "USD"
  time_unit    = "ANNUALLY"

  notification {
    comparison_operator        = "GREATER_THAN"
    notification_type          = "ACTUAL"
    threshold                  = 100
    threshold_type             = "PERCENTAGE"
    subscriber_email_addresses = var.budget_notification_emails
  }
}

# The hard line: $200 of credits, alerting at 95%. This is the only threshold tied to real
# cash leaving the account (§8.3, "Net cash outlay"), which is why it exists separately from
# the $100 operational-discipline figure.
resource "aws_budgets_budget" "lifetime_credits" {
  name         = "pgds-lifetime-190-credits-nearly-gone"
  budget_type  = "COST"
  limit_amount = "190"
  limit_unit   = "USD"
  time_unit    = "ANNUALLY"

  notification {
    comparison_operator        = "GREATER_THAN"
    notification_type          = "ACTUAL"
    threshold                  = 100
    threshold_type             = "PERCENTAGE"
    subscriber_email_addresses = var.budget_notification_emails
  }

  # Forecast too: credits running out is the one thing worth knowing BEFORE it happens,
  # because the alternative is an unpayable bill on a Free Tier account.
  notification {
    comparison_operator        = "GREATER_THAN"
    notification_type          = "FORECASTED"
    threshold                  = 100
    threshold_type             = "PERCENTAGE"
    subscriber_email_addresses = var.budget_notification_emails
  }
}

# ---------------------------------------------------------------------------
# Monthly run-rate anomaly detection.
# ---------------------------------------------------------------------------

resource "aws_budgets_budget" "monthly_run_rate" {
  name         = "pgds-monthly-run-rate"
  budget_type  = "COST"
  limit_amount = var.monthly_run_rate_budget
  limit_unit   = "USD"
  time_unit    = "MONTHLY"

  # FORECASTED at 100% warns before the money is spent, which is the only useful
  # moment for a runaway-egress bill.
  notification {
    comparison_operator        = "GREATER_THAN"
    notification_type          = "FORECASTED"
    threshold                  = 100
    threshold_type             = "PERCENTAGE"
    subscriber_email_addresses = var.budget_notification_emails
  }

  # And ACTUAL at 80%, so a real overrun is reported even if the forecast was wrong
  # (forecasts are unreliable in the first days of a month, when there is little data).
  notification {
    comparison_operator        = "GREATER_THAN"
    notification_type          = "ACTUAL"
    threshold                  = 80
    threshold_type             = "PERCENTAGE"
    subscriber_email_addresses = var.budget_notification_emails
  }
}
