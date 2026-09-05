/**
 * Lightsail compute: the single instance, its static IP, and the firewall
 * (public ports). Snapshots are intentionally NOT managed here — Proposal
 * 02 §9.2 flags snapshots-in-Terraform as a perpetual-drift source; they are
 * created by a cron job on the instance instead (§6.1).
 *
 * This is the PREFERRED backend (§2: nothing is cheaper at this load). Every
 * resource is gated on `var.compute_backend == "lightsail"` because this account
 * currently cannot create bundles above micro_3_0 — see the header of ec2.tf for the
 * evidence and the cost consequence of the fallback.
 */

locals {
  lightsail_instances = var.compute_backend == "lightsail" ? 1 : 0
}

resource "aws_lightsail_instance" "app" {
  count             = local.lightsail_instances
  name              = var.instance_name
  availability_zone = var.availability_zone
  blueprint_id      = var.blueprint_id
  bundle_id         = var.bundle_id
  key_pair_name     = var.key_pair_name

  user_data = templatefile("${path.module}/user_data.sh", {
    pgds_deploy_helper_base64     = filebase64("${path.module}/../../scripts/pgds-deploy-release")
    pgds_deploy_public_key_base64 = base64encode("${trimspace(var.pgds_deploy_public_key)}\n")
  })

  add_on {
    type          = "AutoSnapshot"
    status        = "Disabled" # snapshots are cron-driven on the instance, not Lightsail's own AutoSnapshot add-on (§6.1, §9.2)
    snapshot_time = "06:00"
  }
}

resource "aws_lightsail_static_ip" "app" {
  count = local.lightsail_instances
  name  = "${var.instance_name}-static-ip"
}

resource "aws_lightsail_static_ip_attachment" "app" {
  count          = local.lightsail_instances
  static_ip_name = aws_lightsail_static_ip.app[0].name
  instance_name  = aws_lightsail_instance.app[0].name
}

# ---------------------------------------------------------------------------
# Firewall. §10.2: 80/443 restricted to Cloudflare's edge (the origin sits
# behind Cloudflare's proxy and should never be reachable directly), SSH
# restricted to the administrator's IP only. Nginx additionally validates a
# Cloudflare Transform Rule secret header to reject direct-IP requests that
# happen to originate from a Cloudflare IP range (configured outside
# Terraform, in infra/nginx/pgds.conf).
# ---------------------------------------------------------------------------

resource "aws_lightsail_instance_public_ports" "app" {
  count         = local.lightsail_instances
  instance_name = aws_lightsail_instance.app[0].name

  port_info {
    protocol   = "tcp"
    from_port  = 80
    to_port    = 80
    cidrs      = var.cloudflare_ipv4_cidrs
    ipv6_cidrs = var.cloudflare_ipv6_cidrs
  }

  port_info {
    protocol   = "tcp"
    from_port  = 443
    to_port    = 443
    cidrs      = var.cloudflare_ipv4_cidrs
    ipv6_cidrs = var.cloudflare_ipv6_cidrs
  }

  port_info {
    protocol  = "tcp"
    from_port = 22
    to_port   = 22
    cidrs     = var.ssh_admin_cidrs
  }
}
