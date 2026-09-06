# Architecture — pgds / vihn.id.vn

Current-state architecture of the running system, plus a request-level walkthrough from
browser to database and back.

**This document describes what is deployed, not what was proposed.** Where the running
system differs from `docs/initial_entries/PROPOSAL_02_AWS_INFRA_COST.md`, the deployed
state wins. The proposal remains a historical design record, not a description of production.

Verified 2026-09-06 against the live origin (SSH), the public edge (HTTP), and the
restricted staged-release implementation. Facts sourced from the repository carry a
`file:line` reference; facts sourced from the running host say so.

---

## 1. What is actually running

| | |
|---|---|
| Public site | `https://vihn.id.vn` — HTTP 200, `server: cloudflare` |
| Compute | EC2 `t4g.small`, arm64, 2 vCPU / 1835 MB usable RAM |
| Instance | `i-047f1e4d31db00df6`, AZ `ap-southeast-1b` |
| Public IP | Elastic IP `3.1.122.66` |
| OS | Ubuntu 24.04.4 LTS (aarch64) |
| Disk | 58 GB root, 5.1 GB used (9%), + 2 GB swapfile (0 B in use) |
| Stack | nginx 1.24.0, PHP 8.3.6-FPM, MariaDB 10.11.14, Redis 7.0.15 |
| WordPress | 7.1, theme `pgds` active, 25 posts + 1 page published |
| Docroot | `/var/www/pgds` (**not** `/var/www/html`) |
| Terraform state | `s3://pgds-tfstate-334156771769`, key `main/terraform.tfstate` |
| Application durability | **No application backup or recovery point as of 2026-09-06.** The running EC2 origin and attached root volume are the only application copy; loss or corruption is unrecoverable and requires rebuilding and reseeding. |

The compute backend is EC2, not Lightsail, because `compute_backend = "ec2"` in
`infra/terraform/main/terraform.tfvars:8`. The account is capped at the `micro_3_0`
Lightsail bundle, so the `small_3_0` bundle the proposal costed cannot be created. Both
backends exist in Terraform and are mutually exclusive via `count`; flipping the variable
back would destroy the working origin.

---

## 2. AWS architecture

```text
                            ┌──────────────────────────┐
        Visitors ──────────►│        Cloudflare        │   DNS + proxy + TLS (free plan)
                            │  ─────────────────────── │
                            │  Cache Rule 1: bypass    │   /wp-admin/ /wp-login.php
                            │    admin + API paths     │   /wp-json/ /wp-cron.php /xmlrpc.php
                            │  Cache Rule 2: cache     │   css js woff2 webp jpg png svg …
                            │    static, edge TTL 31d  │   → cf-cache-status: HIT
                            │  HTML: NOT cached        │   → cf-cache-status: DYNAMIC
                            └────────────┬─────────────┘
                                         │ HTTPS 443
                                         │ (origin cert: Let's Encrypt "YR2")
                       ═══════════════════════════════════════  AWS ap-southeast-1
                                         │
                            ┌────────────▼─────────────┐
                            │  Security group pgds-prod-sg
                            │  ───────────────────────  │
                            │  :80,:443 ← 15 CF IPv4 ranges
                            │             7 CF IPv6 ranges
                            │  :22     ← admin /32 + transient CI /32
                            │  egress  → 0.0.0.0/0
                            └────────────┬─────────────┘
                                         │
                            ┌────────────▼─────────────┐
                            │  EC2 t4g.small            │
                            │  Elastic IP 3.1.122.66     │
                            │  ───────────────────────  │
                            │  nginx 1.24                │
                            │    ├─ static → immutable   │
                            │    └─ FastCGI cache        │
                            │  PHP 8.3-FPM               │
                            │    ├─ MariaDB 10.11        │
                            │    └─ Redis 7              │
                            │  media on local disk       │
                            │  fail2ban                  │
                            │  cron: health alert + WP cron
                            └──────────┬──────────┬─────┘
                                       │          │
                             SES SendEmail        │ CloudWatch metrics
                                       ▼          ▼
                            ┌──────────────┐   ┌──────────────┐
                            │ Amazon SES   │   │ 3 alarms → SNS│
                            │ (SANDBOX)    │   │ → email       │
                            │ IAM pgds-ses │   └──────────────┘
                            └──────────────┘

                    ┌──────────────────────────────────────────┐
                    │ S3 pgds-tfstate-…                         │
                    │ Terraform state + native lockfile          │
                    │ versioning ON · SSE-AES256 · no public ACL │
                    └──────────────────────────────────────────┘
```

Everything below the AWS boundary is Terraform-managed except Cloudflare, which is configured
by `infra/scripts/pgds-cloudflare-setup.sh` and the dashboard. There is no Cloudflare provider
in the Terraform stack.

### 2.1 Why the topology looks like this

**Cloudflare replaces Route 53 + CloudFront + ACM.** The free plan supplies authoritative
DNS, edge TLS, and a CDN for static assets, so the AWS surface shrinks to one instance plus
the Terraform remote-state bucket. AWS DNS and CDN line items are $0.

**The origin is not reachable from the internet except through Cloudflare.** Ports 80 and
443 admit only Cloudflare's published ranges (`infra/terraform/main/variables.tf:98-131`). A
direct request to `http://3.1.122.66/` times out — verified. This is network-level
enforcement, not application-level: the intended `X-Origin-Verify` secret header check is
commented out in `infra/nginx/pgds.conf` and is absent from the live nginx config, so the
security group is the only thing standing between the public internet and the origin.

**Single instance, single AZ, accepted SPOF.** No load balancer, no auto-scaling group, no
RDS, no multi-AZ, and no application backup or recovery point. As of 2026-09-06, the running
EC2 origin and attached root volume are the only application copy; loss or corruption is
unrecoverable and requires rebuilding and reseeding. For a six-month site on a $100
operational cap, HA is not cost-justified — this is a deliberate trade, not an oversight.

**Media lives on the instance disk.** No S3 media offload: S3 is not in the Bandwidth
Alliance, so S3→Cloudflare egress would bill independently. The current disk has ample
headroom, but it is the only copy of application media.

**No EC2 instance role.** The scripts were written for Lightsail first, so the SES sender
uses static IAM access keys in root-owned `600` environment files
(`infra/terraform/main/ec2.tf:197-202`). This is a known weakness. An EC2 instance profile
would be strictly better; it was not adopted because the backend flip happened after the
scripts existed.

### 2.2 Compute

`aws_instance.app` (`infra/terraform/main/ec2.tf:167-195`):

- Type `t4g.small` (Graviton, ~10% cheaper than the x86 equivalent).
- AMI resolved at plan time from Canonical's SSM parameter
  `/aws/service/canonical/ubuntu/server/24.04/stable/current/arm64/hvm/ebs-gp3/ami-id` —
  never a hardcoded ID.
- Root volume: 60 GB `gp3`, `encrypted = true`, `delete_on_termination = false`.
- `http_tokens = "required"` — IMDSv2 only.
- `lifecycle.ignore_changes = [ami, user_data]`.

That `ignore_changes` block means an edit to `user_data.sh` has no effect on the running
instance: cloud-init ran it once at first boot. Any change must be applied over SSH or exists
only for a newly built instance.

The EIP is attached to the instance and can remain associated when an origin is intentionally
replaced. It does not provide application-data durability.

### 2.3 RAM budget

2 GB is the binding constraint on this box, and the tuning is derived from it rather than
guessed (`infra/terraform/main/user_data.sh:56-96`):

| Component | Setting | Budget |
|---|---|---:|
| OS + nginx | — | ~250 MB |
| MariaDB | `innodb_buffer_pool_size = 256M`, `max_connections = 40` | ~400 MB |
| Redis | `maxmemory 160mb`, `allkeys-lru` | ~160 MB |
| PHP-FPM | `pm = ondemand`, `pm.max_children = 6`, idle timeout 10s | ~360 MB |
| **Total** | | **~1.17 GB** |

Measured on the live host: 578 MB used, 1003 MB in buff/cache, 1256 MB available, swap
0 B used. The configuration is behaving as designed — swap is insurance against OOM, not
capacity, and any sustained swap usage means the tuning is wrong.

### 2.4 Terraform remote state

The Terraform remote-state bucket is created by a separate bootstrap root
(`infra/terraform/bootstrap/`) with its own local state. It carries `prevent_destroy`,
versioning, SSE-AES256, and all four public-access blocks. It stores
`main/terraform.tfstate` and the S3-native lockfile; noncurrent versions expire after 90
days while the ten newest are retained. State locking uses `use_lockfile = true`; DynamoDB
locking is deprecated and is not used.

This bucket protects Terraform state only. It is not an application-data backup or recovery
point.

### 2.5 IAM

The `pgds-ses` static user has the least-privilege `ses:SendEmail` and
`ses:SendRawEmail` permissions. Its secret-key output is marked `sensitive`; its access-key
ID is not.

### 2.6 Email

`aws_ses_domain_identity.app` and `aws_ses_domain_dkim.app` are gated on
`var.domain_name != ""`. SES is still in the **sandbox**
(`ProductionAccessEnabled: false`), so sending works only to verified recipients. The
instance runs `wp-mail-smtp`; there is no local MTA, so WordPress transactional mail depends
on that plugin's configuration.

### 2.7 Observability

Three CloudWatch alarms, all `AWS/EC2`, fire to SNS topic `pgds-alarms` on alarm and OK
transitions:

| Alarm | Metric | Condition |
|---|---|---|
| `pgds-cpu-high` | `CPUUtilization` | Average > 80 for 2 × 300 s |
| `pgds-cpu-credits-low` | `CPUCreditBalance` | Average < 60 for 6 × 300 s |
| `pgds-status-check-failed` | `StatusCheckFailed` | Maximum > 0 for 2 × 60 s |

RAM and disk are deliberately not CloudWatch metrics. The origin health script runs every 10
minutes and emails through SES when memory, disk, swap, or a core service crosses its
threshold, with a 3-hour cooldown per alert. Four AWS Budgets guard spend: three lifetime
budgets and one monthly run-rate budget.

### 2.8 Deploy identity

No AWS access key is stored in GitHub. `aws_iam_openid_connect_provider.github` plus
`aws_iam_role.github_deploy` let Actions federate in. The role can open and revoke a
transient SSH hole in one security group; it has no S3, SES, or instance-control permissions.
File transfer authority is a separate SSH key.

---

## 3. Cost

Measured monthly run rate is ~$24.89: `t4g.small`, 60 GB gp3, and an Elastic IP. Six months
gross is approximately $149. This is roughly double the Lightsail figure in the historical
proposal because EC2 bills instance, storage, IPv4, and egress separately.

---

## 4. Runtime and delivery

### 4.1 Content and cache

nginx serves static files with immutable cache headers and FastCGI-caches HTML at the origin.
Cloudflare caches static assets but does not cache HTML. The cache-flush mu-plugin purges the
origin on publish transitions and relevant content changes. Only deployment purges Cloudflare.

### 4.2 Runtime release boundary

A release payload contains the theme, the lunar-calendar plugin, and the two listed mu-plugins.
The unprivileged deploy account can only place a package in its incoming directory and invoke
the fixed root-owned promotion helper. The helper verifies checksums, PHP, and assets;
serializes promotion; reloads PHP-FPM; and flushes FastCGI. Promotion failure restores the
prior runtime; retained releases are deployment history, not application-data recovery.

### 4.3 Scheduled work

`/etc/cron.d/pgds`, verified live:

| Schedule | User | Job |
|---|---|---|
| `*/10 * * * *` | root | `pgds-health-alert.sh` — RAM/disk/swap/services → SES |
| `*/5 * * * *` | www-data | `wp cron event run --due-now` |

WP-Cron runs from the system scheduler rather than visitor requests, which is important on a
cached site where most requests never reach PHP.

---

## 5. Security posture

What is actually in place:

- Origin reachable only from Cloudflare ranges; SSH is key-only and IP-restricted.
- fail2ban has `sshd` and `wordpress-auth` jails.
- `DISALLOW_FILE_EDIT = true`; PHP execution is denied in uploads.
- IMDSv2 is required; Redis and MariaDB bind only to localhost.
- Deployment uses immutable-subject-pinned OIDC federation with a narrowly scoped role.

Open items:

- **The `X-Origin-Verify` secret-header check is not active.** The security group is the only
  deployed defence against direct-IP traffic.
- **Static SES IAM credentials are on the instance** under `/root`, mode 600.
- **SES sandbox** restricts alert recipients until production access is granted.
- **SCP cannot be used** on a standalone account; an IAM deny policy cannot constrain root or
  other administrators.

---

## 6. Application data durability

**As of 2026-09-06, production intentionally has no application backup or recovery point.**
The running EC2 origin and its attached root volume are the only application copy. Loss or
corruption is unrecoverable and requires rebuilding the system and reseeding its content.
Terraform remote state is retained separately for infrastructure management only and cannot
recover the application.

---

## 7. Verifying this document

```bash
# Edge + origin cache behaviour
curl -sSI https://vihn.id.vn/ | grep -iE 'x-cache|cf-cache-status'      # twice: MISS then HIT
curl -sSI https://vihn.id.vn/wp-content/themes/pgds/assets/dist/main.a04ae8eb.css \
  | grep -iE 'cache-control|cf-cache-status'                            # immutable + HIT
curl -sSI https://vihn.id.vn/wp-login.php | grep -i x-cache             # BYPASS

# Origin (admin workstation only — port 22 is IP-restricted)
ssh -i ~/.ssh/pgds-deploy-ec2.pem ubuntu@3.1.122.66
sudo -u www-data wp option get siteurl --path=/var/www/pgds
sudo cat /etc/cron.d/pgds
free -m; df -h /
sudo redis-cli CONFIG GET maxmemory

# Infrastructure
cd infra/terraform/main && terraform plan     # expect no changes
aws sesv2 get-account --region ap-southeast-1 \
  --query '{prod:ProductionAccessEnabled,review:Details.ReviewDetails.Status}'
```

Note `~/.ssh/pgds-deploy.pem` does not authenticate despite `key_pair_name = "pgds-deploy"`;
the working keys are `pgds-deploy-ec2.pem` and `pgds-ec2.pem`.
