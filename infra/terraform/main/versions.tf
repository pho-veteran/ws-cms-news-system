/**
 * Main stack — provider and backend configuration.
 *
 * S3 backend with native locking (`use_lockfile = true`, Terraform >= 1.10):
 * state + lock both live in S3, no DynamoDB table required. Proposal 02
 * §9.1 explicitly rejects DynamoDB-based locking as deprecated.
 *
 * The bucket name below is a placeholder — it depends on the AWS account ID,
 * which Terraform cannot interpolate into a `backend` block. Replace
 * "pgds-tfstate-334156771769" with the bootstrap stack's
 * `tfstate_bucket_name` output before running `terraform init` here. See
 * README.md for the exact command.
 */

terraform {
  required_version = ">= 1.10.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
  }

  backend "s3" {
    bucket       = "pgds-tfstate-334156771769" # pgds-tfstate-<account_id>, from bootstrap output
    key          = "main/terraform.tfstate"
    region       = "ap-southeast-1"
    encrypt      = true
    use_lockfile = true
  }
}

provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project   = "pgds"
      ManagedBy = "terraform"
      Stack     = "main"
    }
  }
}
