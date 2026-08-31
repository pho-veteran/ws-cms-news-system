/**
 * SES domain identity + DKIM. Gated on var.domain_name != "" — the real
 * domain is not chosen yet (§8.2: list-email-identities is empty, DKIM
 * needs DNS records in Cloudflare which needs nameservers delegated first,
 * §11). Applying with the placeholder default therefore creates zero SES
 * identity resources rather than a broken, unverifiable one.
 *
 * Once a domain exists: set var.domain_name, re-apply, then add the DKIM
 * CNAME records it outputs to Cloudflare DNS.
 */

resource "aws_ses_domain_identity" "app" {
  count  = var.domain_name == "" ? 0 : 1
  domain = var.domain_name
}

resource "aws_ses_domain_dkim" "app" {
  count  = var.domain_name == "" ? 0 : 1
  domain = aws_ses_domain_identity.app[0].domain
}
