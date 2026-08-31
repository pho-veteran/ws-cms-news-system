/**
 * EC2 compute — the fallback origin, used when Lightsail is unavailable.
 *
 * WHY THIS EXISTS
 * ---------------
 * Proposal 02 §2 selects Lightsail `small_3_0` and excludes EC2 `t4g.small` on
 * COST, not on technical grounds ("~10% cheaper than x86 but still loses to
 * Lightsail because of the bundle"). That reasoning is sound and unchanged.
 *
 * It is moot on this account. AWS refuses to create ANY Lightsail bundle above
 * `micro_3_0` (1 GB):
 *
 *   InvalidInputException: Sorry, your account can not create an instance using
 *   this Lightsail plan size.
 *
 * Verified 2026-08-31 with zero existing instances, across ap-southeast-1,
 * ap-southeast-2, ap-northeast-1, ap-south-1, us-east-1 and eu-west-1 — the cap is
 * account-wide, not regional. `lightsail get-bundles` reports `isActive: true` for
 * every one of those bundles because it describes the regional CATALOG, not account
 * ELIGIBILITY, which is why §8.2's verification passed and still missed this.
 *
 * §2 rules out 1 GB for a technical reason that has not changed: it "cannot run
 * WordPress + MariaDB + Redis", and §4.1's RAM budget needs ~1.17 GB before serving
 * a single request. So `micro_3_0` is not a fallback — it is a different, broken
 * architecture.
 *
 * EC2 is fully available here (8 vCPU standard on-demand quota; t4g.small offered in
 * all three AZs), so it delivers the 2 vCPU / 2 GB origin the proposal actually
 * requires. `user_data.sh` is reused UNCHANGED: t4g.small is the same 2 vCPU / 2 GB
 * shape, so the §4.1 RAM budget applies exactly as written.
 *
 * COST — this is a real regression against §8.1 and must be reported, not buried:
 *
 *   t4g.small (2 vCPU, 2 GB)   $15.48/mo
 *   gp3 60 GB                  $ 5.76/mo
 *   public IPv4                $ 3.65/mo
 *   egress (~150 GB, first     $ 6.00/mo
 *     100 GB free, $0.12/GB)
 *   -------------------------------------
 *   ~$30.89/mo  vs Lightsail's bundled $12.00/mo
 *
 * Over six months that is ~$185 against ~$72 — roughly $113 more, which BREAKS the
 * $100 operational cap in §8.1 while still fitting inside the $200 of credits. The
 * cap was explicitly "operational discipline to detect unexpected costs" rather than
 * a hard budget, and this is exactly the kind of unexpected cost it exists to
 * surface. The $50/$85 budget alarms in budgets.tf will fire.
 *
 * Egress is the whole delta. Lightsail bundles 3 TB; EC2 bills $0.12/GB beyond
 * 100 GB. The 3 TB was bundle headroom, not forecast demand — §5.2 puts real traffic
 * at ~0.12 req/s average. Keeping Cloudflare in front of static assets (§5.6) is what
 * holds the egress estimate down, so it is now a cost control, not just a cache.
 *
 * PREFER LIGHTSAIL. Request a plan-size increase through AWS Support, then flip
 * `compute_backend` back to "lightsail" and destroy these resources. See
 * infra/terraform/README.md.
 */

locals {
  use_ec2       = var.compute_backend == "ec2"
  ec2_instances = local.use_ec2 ? 1 : 0
}

# Canonical's published SSM parameter, rather than a hardcoded AMI ID: AMIs are
# region-specific and are replaced on every Ubuntu release, so a literal would rot.
data "aws_ssm_parameter" "ubuntu_2404_arm64" {
  count = local.ec2_instances
  name  = "/aws/service/canonical/ubuntu/server/24.04/stable/current/arm64/hvm/ebs-gp3/ami-id"
}

data "aws_vpc" "default" {
  count   = local.ec2_instances
  default = true
}

data "aws_subnets" "default" {
  count = local.ec2_instances

  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.default[0].id]
  }
}

# ---------------------------------------------------------------------------
# Security group — the EC2 equivalent of aws_lightsail_instance_public_ports.
# §10.2: 80/443 reachable only from Cloudflare's edge (the origin must not be
# reachable directly by IP), SSH only from the administrator's address.
# ---------------------------------------------------------------------------

resource "aws_security_group" "app" {
  count       = local.ec2_instances
  name        = "${var.instance_name}-sg"
  description = "pgds origin: HTTP/HTTPS from Cloudflare only, SSH from the admin IP only"
  vpc_id      = data.aws_vpc.default[0].id

  tags = {
    Name = "${var.instance_name}-sg"
  }
}

resource "aws_vpc_security_group_ingress_rule" "http_cloudflare_v4" {
  for_each          = local.use_ec2 ? toset(var.cloudflare_ipv4_cidrs) : toset([])
  security_group_id = aws_security_group.app[0].id
  description       = "HTTP from Cloudflare edge"
  cidr_ipv4         = each.value
  from_port         = 80
  to_port           = 80
  ip_protocol       = "tcp"
}

resource "aws_vpc_security_group_ingress_rule" "https_cloudflare_v4" {
  for_each          = local.use_ec2 ? toset(var.cloudflare_ipv4_cidrs) : toset([])
  security_group_id = aws_security_group.app[0].id
  description       = "HTTPS from Cloudflare edge"
  cidr_ipv4         = each.value
  from_port         = 443
  to_port           = 443
  ip_protocol       = "tcp"
}

resource "aws_vpc_security_group_ingress_rule" "http_cloudflare_v6" {
  for_each          = local.use_ec2 ? toset(var.cloudflare_ipv6_cidrs) : toset([])
  security_group_id = aws_security_group.app[0].id
  description       = "HTTP from Cloudflare edge (IPv6)"
  cidr_ipv6         = each.value
  from_port         = 80
  to_port           = 80
  ip_protocol       = "tcp"
}

resource "aws_vpc_security_group_ingress_rule" "https_cloudflare_v6" {
  for_each          = local.use_ec2 ? toset(var.cloudflare_ipv6_cidrs) : toset([])
  security_group_id = aws_security_group.app[0].id
  description       = "HTTPS from Cloudflare edge (IPv6)"
  cidr_ipv6         = each.value
  from_port         = 443
  to_port           = 443
  ip_protocol       = "tcp"
}

resource "aws_vpc_security_group_ingress_rule" "ssh_admin" {
  # ssh_admin_cidrs has no default and rejects 0.0.0.0/0 (see variables.tf), so this
  # cannot silently expose SSH to the internet.
  for_each          = local.use_ec2 ? toset(var.ssh_admin_cidrs) : toset([])
  security_group_id = aws_security_group.app[0].id
  description       = "SSH from the administrator address only"
  cidr_ipv4         = each.value
  from_port         = 22
  to_port           = 22
  ip_protocol       = "tcp"
}

# Egress is unrestricted: the instance must reach apt, WordPress core updates, the
# YouTube Data API, S3 for backups, and SES.
resource "aws_vpc_security_group_egress_rule" "all_v4" {
  count             = local.ec2_instances
  security_group_id = aws_security_group.app[0].id
  description       = "All outbound IPv4"
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "-1"
}

# ---------------------------------------------------------------------------
# Instance
# ---------------------------------------------------------------------------

resource "aws_instance" "app" {
  count = local.ec2_instances

  ami           = data.aws_ssm_parameter.ubuntu_2404_arm64[0].value
  instance_type = var.ec2_instance_type
  subnet_id     = data.aws_subnets.default[0].ids[0]
  key_name      = var.key_pair_name

  vpc_security_group_ids = [aws_security_group.app[0].id]

  # Reused verbatim from the Lightsail path: same 2 vCPU / 2 GB shape, so the §4.1
  # RAM budget (MariaDB 256M buffer pool, Redis 160M, pm.max_children=6) is unchanged.
  user_data                   = file("${path.module}/user_data.sh")
  user_data_replace_on_change = false # Replacing the instance on a script edit would destroy the database.

  root_block_device {
    volume_size = var.ec2_root_volume_gb
    volume_type = "gp3"
    encrypted   = true

    # The disk holds the WordPress media library (25-40 GB per §4.2). Deleting it on
    # instance termination would take the media with it; the snapshot cron (§6.1) is
    # the intended recovery path, but keeping the volume avoids a one-keystroke loss.
    delete_on_termination = false
  }

  metadata_options {
    http_tokens = "required" # IMDSv2 only: blocks SSRF-based credential theft.
  }

  # IMPORTANT: Lightsail has no instance role, so §10.2 accepts static IAM keys on
  # disk as a known weakness. EC2 does support instance roles, but they are
  # deliberately NOT used here — the backup and SES credentials are consumed by
  # scripts written for the Lightsail path, and swapping them for a role would mean
  # rewriting that layer for a fallback we hope to abandon. The keys stay under /root
  # mode 600 exactly as §10.2 prescribes. Revisit if EC2 becomes permanent.

  tags = {
    Name    = var.instance_name
    Project = "pgds"
    Note    = "Fallback origin: the account cannot create Lightsail bundles above micro_3_0"
  }

  lifecycle {
    # The AMI parameter resolves to a new ID on each Ubuntu release. Without this,
    # every plan after an upstream release would propose destroying the live origin.
    ignore_changes = [ami]
  }
}

# Elastic IP — the EC2 equivalent of aws_lightsail_static_ip. Cloudflare's proxied
# A record points here, so the address must survive stop/start.
resource "aws_eip" "app" {
  count    = local.ec2_instances
  instance = aws_instance.app[0].id
  domain   = "vpc"

  tags = {
    Name = "${var.instance_name}-eip"
  }
}
