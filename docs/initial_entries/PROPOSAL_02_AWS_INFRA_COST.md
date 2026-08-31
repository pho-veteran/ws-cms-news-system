# PROPOSAL 02 — Infrastructure & Costs

> **System lifetime:** ephemeral, maximum 6 months.
> **Priority criterion:** cost effective.
> **Staffing:** 1–2 engineers. **Timeline:** 3 days.
> **Budget cap:** USD 100 for the full lifetime.

---

## 1. TL;DR

**Lightsail 2GB + Cloudflare (DNS + CDN) + S3 (Terraform state, DB dumps) + SES.**

Cost is ~**USD 13.9/month**, ~**USD 85** for 6 months, covered by the **USD 200 remaining credits** → net cash **~$0**. 86% of the cost is Lightsail itself, and nothing is cheaper at this load.

No Route 53, CloudFront, ACM, S3 media offload, or Reserved Instance. Media resides on the instance disk. See section 13 for the complete exclusion list.

Terraform is included (state in S3 with `use_lockfile`). CI/CD is included (GitHub Actions, one environment).

**The target account has been verified as eligible** (section 8.2): standalone, no Organization, no SCP, with Lightsail available in `ap-southeast-1` using the `small_3_0` $12.00 bundle. The only remaining D0 blocker is that **SES is in the sandbox**—a production-access request takes 24h+.

---

## 2. Compute

On-demand pricing for region `ap-southeast-1`, August 2026.

| Option | Instance/month | + Storage 60GB | + IPv4 | + Egress 3TB | **Total** |
|---|---|---|---|---|---|
| **Lightsail Small 2GB** | $12.00 | includes 60GB SSD | included | **includes 3TB** | **~$12** |
| EC2 t4g.small 2GB | $15.48 | $5.76 (gp3) | ~$3.65¹ | $0.12/GB after 100GB free | ~$25+ |
| EC2 t4g.micro 1GB | $7.74 | $5.76 | ~$3.65 | idem | ~$17² |
| EC2 t3a.small 2GB (x86) | $17.23 | $5.76 | ~$3.65 | idem | ~$27 |
| Lightsail Medium 4GB | $24.00 | includes 80GB | included | includes 4TB | ~$24 |

¹ Public IPv4 $0.005/h. Must be reconfirmed if EC2 is selected.
² 1GB RAM cannot run WordPress + MariaDB + Redis. Excluded for technical reasons, not price.

**Unit prices:**

| | Price |
|---|---|
| Lightsail Small 2GB — bundle `small_3_0` (2 vCPU, 60GB SSD, 3TB transfer) | **$12.00/month** |
| Lightsail Medium 4GB — bundle `medium_3_0` (2 vCPU, 80GB, 4TB) | **$24.00/month** |
| Lightsail snapshot | $0.05/GB-month |
| EC2 t4g.small | $0.0212/h |
| EC2 t4g.micro | $0.0106/h |
| EC2 t4g.medium | $0.0424/h |
| EC2 t3a.small | $0.0236/h |
| EBS gp3 | $0.096/GB-month |
| EBS gp2 | $0.12/GB-month |
| EC2 egress (first 10TB, beyond free tier) | $0.12/GB |
| S3 Standard (first 50TB) | $0.025/GB-month |
| SES outbound | $0.0001/recipient |

### 2.1 Why Lightsail wins despite comparable instance-hour pricing

The Lightsail bundle includes three items that EC2 bills separately: **60GB SSD, IPv4, and 3TB egress**.

Egress is decisive. EC2 charges $0.12/GB (first 10TB, after 100GB/month free). 3TB of EC2 egress = **$360**. The Lightsail bundle = **$0**.

### 2.2 Excluded options

| Option | Reason for exclusion |
|---|---|
| Fargate / ECS / App Runner | WordPress requires a persistent filesystem → must add EFS + RDS. Costs 3–4× more; setup does not fit 3 days. |
| Lambda + Bref | Unsuitable for WP admin/Gutenberg, cold starts, requires EFS. |
| Graviton EC2 (t4g) | ~10% cheaper than x86 but still loses to Lightsail because of the bundle. |
| Reserved Instance / Savings Plan | One-year commitment for a six-month asset. |

**Final decision: Lightsail Small 2GB.** No option is cheaper at this load.

---

## 3. Architecture

```
                    ┌─────────────────┐
   Visitors ───────► │   Cloudflare    │  DNS + CDN + SSL (free)
                    │  cache: static  │  does NOT cache HTML
                    └────────┬────────┘
                             │ HTTPS, origin certificate
                             ▼
                 ┌───────────────────────┐
                 │  Lightsail 2GB (SG)   │
                 │  ─────────────────────│
                 │  nginx + FastCGI cache│
                 │  PHP 8.3-FPM          │
                 │  MariaDB 10.11        │
                 │  Redis (object cache)  │
                 │  media on disk         │
                 └───────┬───────────────┘
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
    Lightsail        S3 bucket        SES
    snapshot      ┌─────────────┐   (transactional
    (4 rolling)   │ TF state    │        email)
                  │ DB dump     │
                  └─────────────┘
                   S3 Standard
                   no lifecycle
```

**Explicitly accepted:** a single instance, single AZ, **SPOF**. RTO 30–60 minutes from a snapshot. HA is not cost-justified for a six-month ephemeral site with this budget.

---

## 4. Lightsail configuration

**Bundle:** Small 2GB — 2 vCPU, 2GB RAM, 60GB SSD, 3TB transfer. Region `ap-southeast-1` (Singapore).
**Blueprint:** Ubuntu 24.04 LTS (do not use the prebuilt WordPress blueprint—Nginx configuration must be controlled).

### 4.1 RAM budget

On a 2GB machine, calculate the RAM budget before selecting worker count—not the reverse. AWS documentation (the Lightsail WordPress tuning table) recommends ~10 workers for machines with 1.5–3GB; that number does not directly define PHP-FPM children, and a site that must also run imports + image processing needs a more conservative setting.

| Component | RAM |
|---|---|
| OS + nginx | ~250 MB |
| MariaDB (`innodb_buffer_pool_size=256M`) | ~400 MB |
| Redis (`maxmemory 160mb`) | ~160 MB |
| PHP-FPM: `pm=ondemand`, `pm.max_children=6` × ~60MB | ~360 MB |
| **Total** | **~1.17 GB** |
| **Remaining for OS page cache** | **~850 MB** |

- **`pm.max_children=6`** is a starting point; tune from real observations. Increase only when logs show `max_children reached`.
- **2GB swap** is insurance against OOM, **not capacity**. If swap is used regularly → the configuration is wrong.
- **Keep FastCGI cache on disk, NOT tmpfs.** tmpfs directly consumes 2GB RAM. Let the OS page cache manage it.
- During image imports: reduce `pm.max_children` to 2 + `nice -n 19`.

### 4.2 Media on disk — no S3 offload

25–40 GB of media fits the 60GB bundle. Serve it through Cloudflare; egress is included in the 3TB allocation.

Why not S3 + Cloudflare: **S3 is not part of the Bandwidth Alliance**, so S3 → Cloudflare egress is still billed at $0.12/GB. Moving media to S3 increases cost and adds a plugin dependency.

If separation is needed later: **Cloudflare R2** (egress $0). Defer to RUN; do not build it in the three days.

**Consequence:** every post is accessible immediately throughout the six-month lifetime. No tiering or retrieval latency.

---

## 5. Cloudflare — replaces Route 53 + CloudFront + ACM

This is the most cost-saving change in the entire proposal.

### 5.1 What it replaces

| Exclude | Savings / benefit |
|---|---|
| Route 53 hosted zone + queries | $0.50/month + query cost |
| CloudFront | Eliminates cache behaviors, Min TTL, CloudFront Function cookie bypass, and two-layer purge ordering |
| ACM | Cloudflare provides free edge certificates + origin certificates |
| S3 OAC | Not needed |
| S3 offload plugin | Removes one dependency |

### 5.2 Free-plan features required

| Feature | Free | Notes |
|---|---|---|
| Cache Rules | ✅ | **Maximum 10 rules** (Pro 25, Business 50, Ent 300). Requires 2. |
| Purge Everything | ✅ | |
| Purge by URL | ✅ | |
| Edge TTL / Browser TTL in Cache Rule | ✅ | |
| Bypass cache (action) | ✅ | |
| Serve stale while revalidating | ✅ | |
| Origin Cache Control | ✅ but **always enabled; cannot be disabled** | Disabling is Enterprise-only |

Purge by tag / prefix / hostname: **the design does not depend on them**—use only Purge Everything + Purge by URL. If the Free plan offers tag purging, that is upside and allows higher TTLs and more precise purging.

Confirm in the dashboard before finalizing: authoritative DNS, proxy/CDN, bandwidth for web content, Universal SSL, Origin CA certificate, Brotli, HTTP/3.

### 5.3 Configuration

- Proxy **ON** (orange cloud) for `@` and `www`
- SSL/TLS mode **Full (strict)** + Cloudflare Origin CA certificate on Nginx
- 2 Cache Rules:

```
Rule 1  bypass : URI path contains /wp-admin/, /wp-login.php, /wp-json/,
                 /wp-cron.php, /xmlrpc.php          → Bypass cache
Rule 2  static : extension css|js|woff2|webp|jpg|png|svg
                 → Eligible for cache, Edge TTL 1 month
```

**HTML is not cached at the edge**—intentionally. Cloudflare caches static assets only; Nginx FastCGI at the origin handles page cache and is explicitly purged when content changes. This eliminates the need for a cookie-based bypass rule, avoids an edge stale window, and lets visitors see editorial changes on the next request. Use 2/10 Free slots.
- Always Use HTTPS, Auto Minify OFF (assets are already minified in CI)

### 5.4 Two items to review before finalizing

- **ToS 2.8** — the Free plan restricts serving large volumes of non-HTML content (video, large files). This site serving images through the CDN is normal web usage, but this must be understood to avoid adding video later.
- **Rate limiting / WAF free** — a free tier exists, but the rule count is very limited. If protection for `wp-login.php` is planned, check it first; if insufficient, fail2ban at the origin takes responsibility.

---

## 6. Backup

All backup storage is S3 Standard, with no lifecycle transition. Every post must be accessible immediately throughout its lifetime.

### 6.1 Design

| Type | Storage | Frequency | Retention |
|---|---|---|---|
| Instance snapshot (including media) | Lightsail | 1×/day | 4 rolling |
| DB dump (`mysqldump` gzip) | S3 Standard | 2×/day | 7 days |
| Terraform state | S3 Standard | every apply | versioning ON |

- **The snapshot is the media backup**—do not mirror media to S3; that is duplication.
- Four rolling rather than seven: sufficient for an ephemeral site. RPO 24h, RTO 30–60 minutes.
- Lightsail snapshots are **incremental**—only changed blocks are billed, not four full copies.

### 6.2 Isolation

S3 durability ≠ protection against accidental deletion / corrupt writes / leaked credentials.

- **Versioning ON** for the backup bucket (inexpensive; protects against accidental overwrite/deletion)
- **Dedicated IAM credentials for backup**, separate from SES credentials. Policy permits only `s3:PutObject` to the backup prefix—no `DeleteObject`.
- `prevent_destroy` in Terraform for the state bucket + backup bucket

### 6.3 Restore — must be tested for real on Day 3

Not a checkbox. Process:

1. Create a new instance from the latest snapshot
2. Attach the static IP (or test through a temporary IP)
3. Verify: WordPress loads, post count is correct, media displays
4. **Measure actual RTO**, record it in `RUNBOOK.md`
5. Delete the test instance

Restore uses a **clean-room instance** and does not overwrite production.

---

## 7. Monitoring — $0

**Use Lightsail built-in metrics + alarms only.** No CloudWatch agent and no custom metrics.

Built-in Lightsail metrics: CPU utilization, burst capacity, network in/out, status check. Alarms on these metrics are free in the Lightsail console.

**Why not the CloudWatch agent:** RAM and disk usage are **not** built-in Lightsail metrics—they require an agent + custom metrics, and custom metrics are billed:

| CloudWatch (ap-southeast-1) | Price |
|---|---|
| Custom metric (first 10,000) | $0.30/metric-month |
| Standard-resolution alarm | $0.10/alarm-metric-month |
| Log ingestion (Standard class) | $0.70/GB |

Do not assume that “10 custom metrics + 10 alarms + 5GB logs are free.” Over six months, 10 metrics + 10 alarms = **~$24**, 28% of the budget—more than the entire backup cost.

**Replacement for RAM/disk:** a cron script on the instance checks `free -m` and `df -h`, then sends email through SES if thresholds are exceeded. Cost is ~$0.

**Required alarms (Lightsail, free):**
- CPU utilization > 80% for 10 minutes
- Burst capacity < 20%
- Status check failed

---

## 8. Costs

### 8.1 Cost table

| Item | /month | 6 months |
|---|---|---|
| Lightsail Small 2GB | $12.00 | $72.00 |
| Lightsail snapshot (4 rolling, incremental, ~40GB base @ $0.05/GB-mo) | ~$1.80 | ~$10.80 |
| S3 Standard — TF state + DB dump (~2GB) | ~$0.05 | ~$0.30 |
| SES (low volume, $0.0001/recipient) | ~$0.01 | ~$0.06 |
| Cloudflare | $0 | $0 |
| CloudWatch | $0 | $0 |
| Route 53 | not used | $0 |
| CloudFront | not used | $0 |
| **Base** | **~$13.86** | **~$83.16** |
| Temporary 4GB upgrade for several import days (~$0.40/day) | — | ~$1.20 |
| **Estimated total** | | **~$85** |

$85 gross against $200 credits provides ample margin. 86% of cost is the $12/month Lightsail itself, and nothing is cheaper at this load, so this is close to the architecture cost floor.


### 8.2 Account — eligibility verified

Target account: **Free Tier, USD 200 credits remaining, region `ap-southeast-1`.** The full stack can run immediately; no unlock action is required.

| Check | Command | Result |
|---|---|---|
| Organization | `organizations describe-organization` | `AWSOrganizationsNotInUseException` → **standalone, not in an Organization** |
| SCP applied to account | `organizations list-policies` | Same exception → **no SCP blocking a service or region** |
| Identity Center | `sso-admin list-instances` | `[]` |
| Principal | `sts get-caller-identity` | Regular IAM user with `AdministratorAccess` |
| **Lightsail** | `lightsail get-bundles --region ap-southeast-1` | `small_3_0` **$12.00/month**, 2 vCPU / 2GB / 60GB SSD / 3072GB transfer, `isActive: true` |
| Lightsail instance | `lightsail get-instances` | `[]` → service is available; no resources exist yet |
| Blueprint | `lightsail get-blueprints` | `ubuntu_24_04`, `isActive: true` |
| Static IP / snapshot / keypair / alarm | `get-static-ips`, `get-instance-snapshots`, `get-key-pairs`, `get-alarms` | `[]` → API is live; no resources exist yet |
| Region | `account list-regions` | `ap-southeast-1  ENABLED_BY_DEFAULT` |
| S3 | `s3api list-buckets` | `[]` → 0 buckets; both must be created |
| SES | `sesv2 get-account --region ap-southeast-1` | `ProductionAccessEnabled: false` → **SANDBOX**. `SendingEnabled: true`, quota 200 emails/24h, rate 1 msg/s |
| SES identities | `sesv2 list-email-identities` | `[]` → no domain has been verified |
| Root MFA | `iam get-account-summary` | `AccountMFAEnabled: 1` |

The principal used for verification has `AdministratorAccess`, so the above results are **not obscured by missing IAM permissions**—Lightsail is truly available, not an `AccessDenied` response misread as availability. Lightsail returning `[]` means “no resources yet,” which is fundamentally different from being blocked.

**Final Terraform values:** `bundle_id = "small_3_0"`, `blueprint_id = "ubuntu_24_04"`. The `small_2_0` generation is no longer active.

**A cheaper bundle was considered and excluded:** `small_ipv6_3_0` at **$10.00/month**, with the same 2GB/2vCPU/60GB/3TB but IPv6-only. It would save $12 over the full period. It is excluded because `aws_lightsail_static_ip` is IPv4 and an IPv6-only instance cannot reach IPv4-only endpoints (apt, WP core updates, some APIs). Record this so no one assumes the $12 option is an oversight.

### 8.3 Four figures that must be reported separately

| | |
|---|---|
| **Gross AWS cost (6 months)** | ~$85 |
| **Remaining credits** | $200 |
| **Net cash outlay** | **~$0** |
| **Run rate after credits are exhausted** | **~$13.9/month** |

Credits are a bill-payment method; they **do not** reduce the gross architectural cost. Do not report net $0 while omitting gross cost.

$85 gross against $200 credits is ample margin—it is no longer a design-blocking constraint. Retain the $100 cap as operational discipline to detect unexpected costs.

### 8.4 Guardrails

- **AWS Budget alerts** at $50 and $85
- **Cost Anomaly Detection** — already enabled: `Default-Services-Monitor` (DIMENSIONAL/SERVICE) + DAILY, CONFIRMED subscription. Do not recreate it.
- **Address the existing zero-spend budget.** The account has a budget limit of **$1.00/month**, threshold ACTUAL > $0.01, currently in **ALARM**. When Lightsail runs at $12/month, it will alert continuously → alert fatigue → notifications are disabled and the actual guardrail is lost. **Modify or delete it when creating the $50/$85 alerts.**
- **Organizations SCP cannot be used** on a standalone account—it has been verified. An IAM deny policy blocks only the operational user; it does **not** block root or other administrators. Weekly region checks are not enforcement—record this limitation in the runbook.
- **Separate operational credentials.** The account currently has only **one IAM user, with `AdministratorAccess`**. Do not put that key on the instance. Create separate backup credentials (only `s3:PutObject` to the prefix) and SES credentials, separate from each other—sections 6.2 and 10.2.

### 8.5 Items not yet verified

| Item | Why | Where to verify |
|---|---|---|
| **Lightsail snapshot pricing $0.05/GB-month** | `get-bundles` does not return snapshot pricing; no API can retrieve it | Lightsail pricing page. At ~$10.80/6 months, it is worth checking |
| **The `pgds-tfstate` bucket name is available** | It can only be checked by attempting creation or calling `head-bucket` | The first bootstrap `terraform apply` reports it if there is a conflict |
| **EC2 quota if a test instance is needed** | Standard On-Demand quota is **5 vCPU**—enough for one t4g.small but insufficient to create a parallel clean-room restore test with EC2 | Request an increase in advance if that method is chosen. The default restore test uses a new Lightsail instance, so this does not apply |

---

## 9. Terraform

**Yes.** Rationale—and the ephemeral lifetime is an argument **for**, not against it:

1. **Verifiable teardown.** `terraform destroy` provides complete teardown. Manual deletion can leave snapshots, a static IP, or buckets generating costs after the website closes.
2. **Resize/rebuild during the build.** Lightsail resize is migration work (section 10). Manual execution at 2 AM on Day 3 is error-prone; Terraform provides a repeatable code path.
3. **The surface is already small.** Cloudflare replaces Route 53 + CloudFront + ACM + OAC, leaving only ~100–150 lines of AWS infrastructure.

### 9.1 State — S3, not DynamoDB

The S3 backend supports locking through **`use_lockfile = true`**; **DynamoDB-based locking is deprecated**. State + lock are both in S3, with no additional service required. **Marginal cost is ~$0.**

```hcl
terraform {
  backend "s3" {
    bucket       = "pgds-tfstate"
    key          = "prod/terraform.tfstate"
    region       = "ap-southeast-1"
    encrypt      = true
    use_lockfile = true
  }
}
```

### 9.2 Stack separation

**Bootstrap** (apply once, `prevent_destroy`):
- S3 state bucket (versioning ON)
- S3 backup bucket (versioning ON)

Keep these separate so destroying the main stack does not delete its backups and state.

**Main:**
- `aws_lightsail_instance` — `bundle_id = "small_3_0"`, `blueprint_id = "ubuntu_24_04"` (both verified as `isActive` in `ap-southeast-1`, section 8.2)
- `aws_lightsail_static_ip` + `aws_lightsail_static_ip_attachment`
- `aws_lightsail_instance_public_ports`
- IAM user + policy: SES send, S3 backup write
- SES domain identity + DKIM

**Do not include in Terraform:**
- **Snapshots** — create via cron/manually. Putting them in Terraform makes every plan report drift.
- **The entire WordPress layer** — installation, theme, plugins, content. LEMP bootstrap via `user_data` is acceptable; do not attempt to make the WordPress layer declarative in three days.

**Cloudflare provider:** optional. DNS is only a few records, but because it is critical at cutover, managing it in Terraform is also reasonable.

*Read the provider registry while writing the actual module* to finalize `aws_lightsail_instance` arguments (`bundle_id`, `blueprint_id`, `ip_address_type`...).

---

## 10. Operations

### 10.1 Resize — a migration, not a toggle

Lightsail resize is **not** in-place. Actual process:

1. Snapshot the current instance
2. Create a new instance from the snapshot with the larger bundle
3. Detach the static IP from the old instance → attach it to the new instance
4. Verify: WordPress loads, database connects, media displays
5. Cut over and delete the old instance

This is not a zero-risk autoscaling toggle. There is brief downtime in step 3. Terraform makes it repeatable, but it remains an operation with risk—do it only when needed and after a snapshot exists.

### 10.2 Security

**Do:**
- Cloudflare proxy ON → origin IP is not public
- Lightsail firewall: allow 80/443 only from **Cloudflare IP ranges**, SSH 22 only from the administrator IP
- Nginx validates a **secret header** injected by a Cloudflare Transform Rule → blocks direct-IP access
- SSH key-only, `PasswordAuthentication no`, fail2ban
- 2FA for WordPress admin (Two Factor plugin)
- `DISALLOW_FILE_EDIT = true`
- HSTS, CSP
- Cloudflare Origin CA certificate, SSL Full (strict)

**Credentials:** static IAM access keys on the instance (Lightsail has no instance role like EC2). State clearly that this is a known weakness. Compensate with least-privilege policy (SES send + S3 put to the backup prefix), store the key under `/root` mode 600, and rotate if exposure is suspected.

### 10.3 Exit plan — mandatory

For an ephemeral site, its end of life must decide where the 2,000 posts will go. Without an exit plan, either data is lost or infrastructure continues running and wasting money.

**Order (export BEFORE destroy):**

1. WXR export (`wp export`) + `mysqldump` → download locally + upload to S3
2. Tarball the full `wp-content/uploads` → download locally
3. *(Optional)* Static-export the entire site by crawling if URLs must remain readable after shutdown
4. Verify that the exports can be opened
5. `terraform destroy`
6. Delete remaining snapshots (not in Terraform state)
7. Delete the backup bucket (bootstrap stack with `prevent_destroy`—remove the flag first)

Record this in `RUNBOOK.md` along with the **scheduled decommission date** and **responsible owner**.

---

## 11. Infrastructure schedule — Day 1

| Task | Estimate |
|---|---|
| Bootstrap Terraform (2 buckets) + main stack apply | 1.5h |
| Install LEMP + PHP 8.3 + MariaDB + Redis, tune per section 4.1 | 1.5h |
| WordPress core + 4 plugins + `wp-config.php` hardening | 1h |
| Cloudflare: zone, DNS, proxy, SSL Full strict, origin certificate, 2 Cache Rules | 1h |
| SES: domain identity, DKIM, verify, test email | 0.5h |
| Backup: snapshot cron + DB-dump cron → S3 | 0.5h |
| Lightsail alarm + RAM/disk cron script | 0.5h |
| Firewall + secret header + fail2ban | 0.5h |
| **Total** | **~7h** |

**Tight for one day.** Dependencies outside our control—all must be handled on **D0**, not deferred to Day 1:

- **SES production access:** the account is in sandbox (`ProductionAccessEnabled: false`, quota 200 emails/24h, can send only to verified addresses). The request takes **24h+** → this is the sole D0 item that still risks delaying the schedule.
- **DNS delegation:** nameservers must point to Cloudflare. Propagation can take several hours → do this before Day 1 if the domain is currently with another provider.
- **SES domain identity + DKIM:** `list-email-identities` is currently empty. DKIM requires DNS records in Cloudflare, so it must be configured **after** nameservers point to Cloudflare.
- **Measure total media size:** determines the 60GB or 80GB bundle.
- **Fix the $1/month zero-spend budget currently in ALARM** (section 8.4) before Lightsail runs.

---

## 12. Risks

| Risk | Level | Mitigation |
|---|---|---|
| SES sandbox is not upgraded in time | **High** | Request production access on D0—it takes 24h+ and is the only remaining blocker |
| $1/month zero-spend budget alerts continuously → alert fatigue → actual guardrail is disabled | Medium | Modify or delete it when creating the $50/$85 alerts |
| Slow DNS propagation on Day 1 | Medium | Delegate nameservers to Cloudflare before Day 1 |
| One-instance SPOF | Medium | **Explicitly accepted.** RTO 30–60 minutes |
| Image processing pushes 2GB into swap | Medium | `nice` + reduce `pm.max_children`; temporarily upgrade to 4GB |
| Static IAM key on the instance | Low | Least-privilege policy, mode 600, rotate if exposure is suspected |
| Media exceeds 60GB disk | Low | Measure total size on D0. If exceeded → 4GB bundle (80GB) or R2 |
| Cloudflare ToS 2.8 (non-HTML content) | Low | Images through CDN are normal usage; do not add video |
| SCP cannot be used on a standalone account | Low | Record the limitation in the runbook |
