/**
 * Lightsail compute: the single instance, its static IP, and the firewall
 * (public ports). Snapshots are intentionally NOT managed here — Proposal
 * 02 §9.2 flags snapshots-in-Terraform as a perpetual-drift source; they are
 * created by a cron job on the instance instead (§6.1).
 */

resource "aws_lightsail_instance" "app" {
  name              = var.instance_name
  availability_zone = var.availability_zone
  blueprint_id      = var.blueprint_id
  bundle_id         = var.bundle_id
  key_pair_name     = var.key_pair_name

  user_data = file("${path.module}/user_data.sh")

  add_on {
    type          = "AutoSnapshot"
    status        = "Disabled" # snapshots are cron-driven on the instance, not Lightsail's own AutoSnapshot add-on (§6.1, §9.2)
    snapshot_time = "06:00"
  }
}

resource "aws_lightsail_static_ip" "app" {
  name = "${var.instance_name}-static-ip"
}

resource "aws_lightsail_static_ip_attachment" "app" {
  static_ip_name = aws_lightsail_static_ip.app.name
  instance_name  = aws_lightsail_instance.app.name
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
  instance_name = aws_lightsail_instance.app.name

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
