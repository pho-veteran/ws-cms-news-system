variable "aws_region" {
  description = "AWS region for the bootstrap resources (Proposal 02 §8.2 verifies ap-southeast-1)."
  type        = string
  default     = "ap-southeast-1"
}
