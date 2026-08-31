output "static_ip_address" {
  description = "Static public IPv4 address of the instance. Point Cloudflare's proxied A record at this."
  value       = aws_lightsail_static_ip.app.ip_address
}

output "instance_name" {
  value = aws_lightsail_instance.app.name
}

output "backup_access_key_id" {
  description = "Access key ID for the pgds-backup IAM user (PutObject only, no delete)."
  value       = aws_iam_access_key.backup.id
}

output "backup_secret_access_key" {
  description = "Secret access key for the pgds-backup IAM user. Store under /root on the instance, mode 600 (§10.2)."
  value       = aws_iam_access_key.backup.secret
  sensitive   = true
}

output "ses_access_key_id" {
  description = "Access key ID for the pgds-ses IAM user (send only)."
  value       = aws_iam_access_key.ses.id
}

output "ses_secret_access_key" {
  description = "Secret access key for the pgds-ses IAM user. Store under /root on the instance, mode 600 (§10.2)."
  value       = aws_iam_access_key.ses.secret
  sensitive   = true
}

output "ses_dkim_tokens" {
  description = "DKIM CNAME tokens to add to Cloudflare DNS. Empty until var.domain_name is set to a real domain."
  value       = var.domain_name == "" ? [] : aws_ses_domain_dkim.app[0].dkim_tokens
}
