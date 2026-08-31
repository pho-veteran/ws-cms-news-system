# Backend-agnostic so the Cloudflare DNS step is identical either way: a Lightsail
# static IP and an EC2 Elastic IP serve the same role, and only one of the two sets of
# resources exists for a given var.compute_backend.
output "static_ip_address" {
  description = "Static public IPv4 address of the origin. Point Cloudflare's proxied A record at this."
  value = var.compute_backend == "lightsail" ? (
    length(aws_lightsail_static_ip.app) > 0 ? aws_lightsail_static_ip.app[0].ip_address : null
    ) : (
    length(aws_eip.app) > 0 ? aws_eip.app[0].public_ip : null
  )
}

output "instance_name" {
  description = "Name/tag of the origin instance."
  value = var.compute_backend == "lightsail" ? (
    length(aws_lightsail_instance.app) > 0 ? aws_lightsail_instance.app[0].name : null
  ) : var.instance_name
}

output "compute_backend" {
  description = "Which compute service is actually hosting the origin. \"ec2\" means the Lightsail plan-size cap forced the fallback (see ec2.tf) and the run rate is ~$31/mo rather than $12/mo."
  value       = var.compute_backend
}

output "ec2_instance_id" {
  description = "EC2 instance ID, or null when running on Lightsail. Needed for snapshot/AMI operations and the §6.3 restore test."
  value       = length(aws_instance.app) > 0 ? aws_instance.app[0].id : null
}

output "ssh_command" {
  description = "Ready-to-use SSH command for the origin. The default EC2 user on Canonical's Ubuntu AMI is \"ubuntu\"."
  value = var.compute_backend == "lightsail" ? (
    length(aws_lightsail_static_ip.app) > 0 ? "ssh -i ~/.ssh/${var.key_pair_name}.pem ubuntu@${aws_lightsail_static_ip.app[0].ip_address}" : null
    ) : (
    length(aws_eip.app) > 0 ? "ssh -i ~/.ssh/${var.key_pair_name}.pem ubuntu@${aws_eip.app[0].public_ip}" : null
  )
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
