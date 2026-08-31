output "tfstate_bucket_name" {
  description = "Name of the S3 bucket holding Terraform remote state for the main stack. Use this as `bucket` in main/backend.tf."
  value       = aws_s3_bucket.tfstate.id
}

output "backup_bucket_name" {
  description = "Name of the S3 bucket holding DB dumps and other backup artifacts. Use this as `var.backup_bucket_name` in the main stack."
  value       = aws_s3_bucket.backup.id
}

output "backup_bucket_arn" {
  description = "ARN of the backup bucket, needed to scope the backup IAM user's policy in the main stack."
  value       = aws_s3_bucket.backup.arn
}
