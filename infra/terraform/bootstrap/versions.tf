/**
 * Bootstrap stack — provider and Terraform version constraints.
 *
 * This stack uses a LOCAL backend on purpose: it creates the S3 bucket that
 * the "main" stack later uses as its remote backend, so it cannot depend on
 * that bucket existing yet. See README.md for the apply order.
 */

terraform {
  required_version = ">= 1.10.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
  }
}

provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project   = "pgds"
      ManagedBy = "terraform"
      Stack     = "bootstrap"
    }
  }
}
