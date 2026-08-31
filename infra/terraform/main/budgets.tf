/**
 * AWS Budgets guardrails (Proposal 02 §8.4): $50 and $85 actual-spend
 * notifications against the $100 lifetime cap.
 *
 * Does NOT touch the pre-existing "My Zero-Spend Budgettt" ($1.00 limit,
 * currently in ALARM) — Terraform never imports or deletes resources it
 * did not create, and that budget was created out of band. See
 * README.md for the CLI command to clean it up manually; leaving it in
 * place once Lightsail bills $12/mo causes alert fatigue on the real
 * guardrails below.
 */

resource "aws_budgets_budget" "monthly_50" {
  name         = "pgds-monthly-50"
  budget_type  = "COST"
  limit_amount = "50"
  limit_unit   = "USD"
  time_unit    = "MONTHLY"

  notification {
    comparison_operator        = "GREATER_THAN"
    notification_type          = "ACTUAL"
    threshold                  = 100
    threshold_type             = "PERCENTAGE"
    subscriber_email_addresses = var.budget_notification_emails
  }
}

resource "aws_budgets_budget" "monthly_85" {
  name         = "pgds-monthly-85"
  budget_type  = "COST"
  limit_amount = "85"
  limit_unit   = "USD"
  time_unit    = "MONTHLY"

  notification {
    comparison_operator        = "GREATER_THAN"
    notification_type          = "ACTUAL"
    threshold                  = 100
    threshold_type             = "PERCENTAGE"
    subscriber_email_addresses = var.budget_notification_emails
  }
}
