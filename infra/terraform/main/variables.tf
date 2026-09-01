variable "aws_region" {
  description = "AWS region for the main stack (Proposal 02 §8.2 verifies ap-southeast-1)."
  type        = string
  default     = "ap-southeast-1"
}

variable "availability_zone" {
  description = "Lightsail availability zone. Must be within var.aws_region."
  type        = string
  default     = "ap-southeast-1a"
}

variable "instance_name" {
  description = "Name of the Lightsail instance."
  type        = string
  default     = "pgds-prod"
}

variable "bundle_id" {
  description = <<-EOT
    Lightsail bundle. small_3_0 = 2 vCPU / 2GB RAM / 60GB SSD / 3TB transfer, $12/mo
    (Proposal 02 §2, §8.2). small_ipv6_3_0 was considered and rejected: IPv6-only,
    incompatible with aws_lightsail_static_ip (IPv4).

    BLOCKED on account 334156771769 as of 2026-08-31: CreateInstances returns
    InvalidInputException "your account can not create an instance using this
    Lightsail plan size" for small_3_0, small_ipv6_3_0 and medium_3_0. Verified on a
    clean account with zero existing instances; nano_3_0 and micro_3_0 succeed, so
    the account's plan-size ceiling is micro_3_0 (1GB).

    Note this is NOT what `lightsail get-bundles` reports: every bundle above returns
    isActive=true there, because that API describes the regional CATALOG, not account
    ELIGIBILITY. Proposal 02 §8.2 treated get-bundles as proof that small_3_0 was
    available; that check was necessary but not sufficient.

    The default is deliberately left at small_3_0 rather than lowered to micro_3_0 —
    but NOT for the reason §2 gives. §2 says 1GB "cannot run WordPress + MariaDB +
    Redis" and §4.1 budgets ~1.17GB. Both are estimates, and the RAM one is measurably
    wrong. Measured on the live origin (2026-09-01), 300 requests at 25 concurrent with
    the FastCGI cache bypassed so every request reached PHP:

      idle                    459 MB used
      peak under load         584 MB used, all 6 php-fpm children busy
      swap used                 0 MB
      core stack RSS          172 MB (mariadb 110, php-fpm 35, nginx 15, redis 12)

    584 MB fits 1GB. Caveat that matters: the DB was 4.6MB and Redis held 1.45MB, so the
    256MB InnoDB buffer pool and 160MB Redis ceiling were nearly empty. At full load the
    §4.1 allocation is the right planning figure even though the measurement is lower.

    The actual disqualifier for micro_3_0 is DISK, which no amount of tuning changes:
    micro_3_0 ships 40GB total, and §4.2 expects 25-40GB of media alone plus the OS, the
    database and the FastCGI cache. small_3_0's 60GB is what the media sizing assumed
    (§4.2: "25-40 GB of media fits the 60GB bundle"). 40GB would run out during import.

    So: raise the cap via AWS Support and apply unchanged, or stay on the EC2 fallback,
    where the 60GB gp3 volume is sized independently of the instance.
  EOT
  type        = string
  default     = "small_3_0"

  validation {
    condition     = !can(regex("_win_", var.bundle_id))
    error_message = "Windows bundles cost roughly double and cannot run this LEMP stack."
  }
}

variable "blueprint_id" {
  description = "Lightsail OS blueprint. Ubuntu 24.04 LTS, not the WordPress blueprint, so Nginx config stays under our control (§4)."
  type        = string
  default     = "ubuntu_24_04"
}

variable "key_pair_name" {
  description = "Name of an existing Lightsail key pair to attach to the instance for SSH access. Create it out of band (Lightsail console or `aws lightsail create-key-pair`) before applying."
  type        = string
}

variable "ssh_admin_cidrs" {
  description = "CIDR block(s) allowed to reach SSH (port 22). Required with no default so an apply can never silently open SSH to 0.0.0.0/0 (Proposal 02 §10.2). Use your admin IP with /32, e.g. [\"203.0.113.10/32\"]."
  type        = list(string)

  validation {
    condition     = length(var.ssh_admin_cidrs) > 0 && !contains(var.ssh_admin_cidrs, "0.0.0.0/0")
    error_message = "ssh_admin_cidrs must not be empty and must not include 0.0.0.0/0 — restrict SSH to the administrator's IP (Proposal 02 §10.2)."
  }
}

# ---------------------------------------------------------------------------
# Cloudflare IP ranges — 80/443 must only accept traffic from Cloudflare's
# edge, since the origin sits behind Cloudflare's proxy (§10.2). Defaults are
# Cloudflare's officially published ranges as of 2026-08-31:
# https://www.cloudflare.com/ips-v4 and https://www.cloudflare.com/ips-v6.
# Cloudflare occasionally adds ranges — re-check that page if 80/443 traffic
# starts getting blocked at the firewall.
# ---------------------------------------------------------------------------

variable "cloudflare_ipv4_cidrs" {
  description = "Cloudflare's published IPv4 ranges, allowed on 80/443. See https://www.cloudflare.com/ips-v4."
  type        = list(string)
  default = [
    "173.245.48.0/20",
    "103.21.244.0/22",
    "103.22.200.0/22",
    "103.31.4.0/22",
    "141.101.64.0/18",
    "108.162.192.0/18",
    "190.93.240.0/20",
    "188.114.96.0/20",
    "197.234.240.0/22",
    "198.41.128.0/17",
    "162.158.0.0/15",
    "104.16.0.0/13",
    "104.24.0.0/14",
    "172.64.0.0/13",
    "131.0.72.0/22",
  ]
}

variable "cloudflare_ipv6_cidrs" {
  description = "Cloudflare's published IPv6 ranges, allowed on 80/443. See https://www.cloudflare.com/ips-v6."
  type        = list(string)
  default = [
    "2400:cb00::/32",
    "2606:4700::/32",
    "2803:f800::/32",
    "2405:b500::/32",
    "2405:8100::/32",
    "2a06:98c0::/29",
    "2c0f:f248::/32",
  ]
}

variable "backup_bucket_name" {
  description = "Name of the S3 backup bucket created by the bootstrap stack (its `backup_bucket_name` output)."
  type        = string
}

variable "backup_bucket_arn" {
  description = "ARN of the S3 backup bucket created by the bootstrap stack (its `backup_bucket_arn` output)."
  type        = string
}

variable "backup_object_prefix" {
  description = "Key prefix within the backup bucket that the backup IAM user may write to, e.g. \"db-dumps/*\"."
  type        = string
  default     = "db-dumps/*"
}

variable "domain_name" {
  description = "Production domain name, e.g. \"phatgiaovadoisong.example\". Leave \"\" (default) until a real domain is chosen — SES domain identity + DKIM resources are skipped entirely while this is empty, so a placeholder domain never creates broken/unverifiable identities."
  type        = string
  default     = ""
}

variable "budget_notification_emails" {
  description = "Email addresses to notify for the $50/$85 AWS Budgets alerts (Proposal 02 §8.4)."
  type        = list(string)
}

# ---------------------------------------------------------------------------
# Compute backend
# ---------------------------------------------------------------------------

variable "compute_backend" {
  description = <<-EOT
    Which compute service hosts the origin: "lightsail" or "ec2".

    "lightsail" is the proposal's choice and stays the default — §2 concludes nothing
    is cheaper at this load, because the bundle includes 60 GB SSD, IPv4 and 3 TB of
    egress that EC2 bills separately.

    Set "ec2" only when Lightsail cannot deliver a 2 GB instance. On account
    334156771769 that is the case, re-confirmed 2026-09-01: CreateInstances still returns
    InvalidInputException "your account can not create an instance using this Lightsail
    plan size" for small_3_0, while micro_3_0 creates successfully — so the ceiling is
    real and it is micro_3_0. micro_3_0 is rejected on DISK, not RAM: 40 GB against
    §4.2's 25-40 GB of media plus OS, database and cache. See bundle_id above, which
    records the RAM measurement that disproves §2's stated reason.

    Cost, from the Pricing API for ap-southeast-1 rather than estimated:

      t4g.small  730 h/mo x 6 @ $0.0212/h  = $92.86
      gp3 60 GB          x 6 mo @ $0.096/GB = $34.56
      Elastic IP 730 h/mo x 6 @ $0.005/h   = $21.90
      ----------------------------------------------
      six-month gross                       = $149.32  (~$24.89/mo)

    versus $72.00 for Lightsail small_3_0. That is over §8.1's $100 figure and under the
    $200 credits, leaving $50.68 of margin and ~$0 net cash outlay.

    This is NOT a blocked state. §8.1 resolves the $100 itself: "$85 gross against $200
    credits is ample margin — it is no longer a design-blocking constraint. Retain the
    $100 cap as operational discipline to detect unexpected costs." It is a monitoring
    threshold, and budgets.tf implements that discipline at $50 while adding thresholds
    sized to the real projection. The binding constraint is the credit balance, which
    $149.32 respects. See ec2.tf.
  EOT
  type        = string
  default     = "lightsail"

  validation {
    condition     = contains(["lightsail", "ec2"], var.compute_backend)
    error_message = "compute_backend must be either \"lightsail\" or \"ec2\"."
  }
}

variable "ec2_instance_type" {
  description = <<-EOT
    EC2 instance type when compute_backend = "ec2". Default t4g.small = 2 vCPU / 2 GB
    (Graviton/arm64), matching the Lightsail small_3_0 shape so the §4.1 RAM budget
    and user_data.sh apply unchanged. Graviton is ~10% cheaper than the x86 t3a.small
    equivalent (§2.2).

    Changing the architecture family (e.g. to t3a.small, which is x86) also requires
    changing the AMI SSM parameter in ec2.tf from arm64 to amd64.
  EOT
  type        = string
  default     = "t4g.small"

  validation {
    condition     = can(regex("^t4g\\.(small|medium)$", var.ec2_instance_type))
    error_message = "Use t4g.small (2GB, matches the proposal) or t4g.medium (4GB, the import-day upgrade in §9.3). Other types need the AMI architecture in ec2.tf changed to match."
  }
}

variable "ec2_root_volume_gb" {
  description = "Root gp3 volume size in GB when compute_backend = \"ec2\". 60 matches the Lightsail small_3_0 bundle and holds the 25-40 GB media library (§4.2)."
  type        = number
  default     = 60

  validation {
    condition     = var.ec2_root_volume_gb >= 40
    error_message = "The media library alone is 25-40 GB (§4.2); anything under 40 GB will not fit WordPress plus media."
  }
}

variable "monthly_run_rate_budget" {
  description = <<-EOT
    Monthly run-rate budget in USD, for anomaly detection rather than for tracking
    cumulative spend (the lifetime thresholds in budgets.tf do that).

    Sized ABOVE the expected run rate so it signals something unexpected instead of
    firing every month on normal operation — the alert-fatigue trap §8.4 describes.

    Expected run rate, from the Pricing API for ap-southeast-1 on 2026-09-01 rather than
    from an estimate:

      t4g.small  730 h @ $0.0212/h  = $15.48/mo
      gp3 60 GB        @ $0.096/GB  =  $5.76/mo
      Elastic IP 730 h @ $0.005/h   =  $3.65/mo
      ---------------------------------------------
      measured                       = $24.89/mo

    (An earlier revision of this file said "~$31/mo" for EC2. That was an estimate and it
    was wrong; the figure above is queried.)

    The default of 40 leaves ~$15 of headroom over the measured $24.89 — enough that a
    normal month never alerts, tight enough that the volatile component shows up. That
    component is egress at $0.12/GB beyond the first 100 GB free, which nothing in the
    architecture caps: at $40 the alarm trips at roughly 125 GB/month of billable egress,
    against the ~150 GB total the traffic in §5.2 implies (most of which Cloudflare serves,
    so it never reaches the origin).

    Lower this to ~20 if the origin ever moves back to Lightsail, or the budget stops
    being a meaningful signal.
  EOT
  type        = string
  default     = "40"

  validation {
    condition     = can(tonumber(var.monthly_run_rate_budget)) && tonumber(var.monthly_run_rate_budget) > 0
    error_message = "monthly_run_rate_budget must be a positive number expressed as a string, e.g. \"40\"."
  }
}
