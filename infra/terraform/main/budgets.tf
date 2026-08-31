/**
 * AWS Budgets guardrails (Proposal 02 §8.4).
 *
 * §8.4 asks for alerts "at $50 and $85" against the $100 lifetime cap. Those figures
 * assume the Lightsail run rate of ~$12/mo, where $50 of LIFETIME spend is a genuine
 * mid-project signal.
 *
 * On the EC2 fallback the run rate is ~$31/mo (see ec2.tf), so a MONTHLY $50 budget
 * would sit quiet in month one and then alert every single month afterwards. That is
 * the exact failure §8.4 itself warns about for the pre-existing $1 zero-spend budget:
 * an alarm that is always on gets muted, and then the guardrail is gone.
 *
 * So the two lifetime thresholds are modelled as what they actually are — cumulative
 * spend across the project's six-month life — using time_unit = ANNUALLY, which is
 * the longest period AWS Budgets offers (there is no "lifetime" or arbitrary-window
 * option; QUARTERLY would reset twice inside the project).
 *
 * A separate MONTHLY budget then watches the run rate itself, sized above the
 * expected ~$31 so it fires on an anomaly (runaway egress, a forgotten instance)
 * rather than on normal operation. Egress is the volatile component: $0.12/GB beyond
 * the first 100GB, and nothing in the architecture caps it.
 *
 * Does NOT touch the pre-existing "My Zero-Spend Budgettt" — Terraform never imports
 * resources it did not create. That budget was deleted manually; see README.md.
 */

# ---------------------------------------------------------------------------
# Lifetime cumulative spend — the §8.4 $50 / $85 thresholds.
# ---------------------------------------------------------------------------

resource "aws_budgets_budget" "lifetime_50" {
  name         = "pgds-lifetime-50"
  budget_type  = "COST"
  limit_amount = "50"
  limit_unit   = "USD"
  # ANNUALLY, not MONTHLY: this tracks cumulative project spend toward the $100 cap.
  # The project's lifetime is six months, so an annual window never resets inside it.
  time_unit = "ANNUALLY"

  notification {
    comparison_operator        = "GREATER_THAN"
    notification_type          = "ACTUAL"
    threshold                  = 100
    threshold_type             = "PERCENTAGE"
    subscriber_email_addresses = var.budget_notification_emails
  }
}

resource "aws_budgets_budget" "lifetime_85" {
  name         = "pgds-lifetime-85"
  budget_type  = "COST"
  limit_amount = "85"
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
