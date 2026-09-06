output "tfstate_bucket_name" {
  description = "Name of the S3 bucket holding Terraform remote state for the main stack. Use this as `bucket` in main/backend.tf."
  value       = aws_s3_bucket.tfstate.id
}
