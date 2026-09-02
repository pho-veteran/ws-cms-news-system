# Architecture — pgds / vihn.id.vn

Current-state architecture of the running system, plus a request-level walkthrough from
browser to database and back.

**This document describes what is deployed, not what was proposed.** Where the running
system differs from `docs/initial_entries/PROPOSAL_02_AWS_INFRA_COST.md`, the deployed
state wins and the difference is recorded in §7. The proposals remain the design record;
they are not a description of production.

Verified 2026-09-01 against the live origin (SSH) and the public edge (HTTP). Facts
sourced from the repository carry a `file:line` reference; facts sourced from the running
host say so.

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

The compute backend is EC2, not Lightsail, because `compute_backend = "ec2"` in
`infra/terraform/main/terraform.tfvars:8`. The account is capped at the `micro_3_0`
Lightsail bundle, so the `small_3_0` bundle the proposal costed cannot be created. Both
backends exist in Terraform and are mutually exclusive via `count`; flipping the variable
back would destroy the working origin.

---

## 2. AWS architecture

```
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
                            │  :22     ← 171.244.16.211/32 (admin)
                            │            + transient runner /32 (CI)
                            │  egress  → 0.0.0.0/0
                            └────────────┬─────────────┘
                                         │
     ┌───────────────────────────────────▼────────────────────────────────────┐
     │  EC2 t4g.small · i-047f1e4d31db00df6 · EIP 3.1.122.66 · AZ …-1b        │
     │  default VPC, first default subnet, IMDSv2 required                    │
     │  ────────────────────────────────────────────────────────────────────  │
     │   nginx 1.24                                                           │
     │     ├─ static files  → Cache-Control: immutable, 1 year                │
     │     └─ FastCGI cache  zone WP:32m · /var/cache/nginx/fcgi · 1g · 12h   │
     │           │  X-Cache: HIT | MISS | BYPASS                              │
     │           ▼                                                            │
     │   PHP 8.3-FPM   pm=ondemand · max_children=6 · unix socket             │
     │           │                                                            │
     │           ├──────────────► MariaDB 10.11   innodb_buffer_pool 256M     │
     │           │                               max_connections 40           │
     │           └──────────────► Redis 7         maxmemory 160mb, allkeys-lru│
     │                                            object-cache.php drop-in    │
     │   media on local disk (no S3 offload)                                  │
     │   fail2ban: jails sshd + wordpress-auth                                │
     │   cron: db-backup ×2/day · health-alert ×6/hour · wp-cron ×12/hour     │
     └───────┬──────────────────────────────────┬────────────────┬────────────┘
             │ gzip mysqldump                   │ SES SendEmail  │ CloudWatch
             ▼                                  ▼                ▼  metrics
   ┌──────────────────────┐        ┌───────────────────┐   ┌──────────────────┐
   │ S3 pgds-backup-…     │        │  Amazon SES       │   │ 3 alarms → SNS   │
   │  db-dumps/  7d expiry│        │  (SANDBOX)        │   │  pgds-alarms     │
   │  versioning ON       │        │  IAM user pgds-ses│   │  → email         │
   │  SSE-AES256          │        └───────────────────┘   └──────────────────┘
   │  prevent_destroy     │
   ├──────────────────────┤        ┌────────────────────────────────────────┐
   │ S3 pgds-tfstate-…    │        │ 4 AWS Budgets: 50 / 160 / 190 lifetime │
   │  TF state + lockfile │        │   + 40/mo run-rate  → email            │
   │  versioning ON       │        └────────────────────────────────────────┘
   └──────────────────────┘
             ▲
             │ terraform apply
   ┌─────────┴───────────────────────────────────────────────────────────────┐
   │ GitHub Actions · push to main                                            │
   │  lint-php ─┐                                                             │
   │  lint-js  ─┴─► build ─► deploy ──► OIDC AssumeRole pgds-github-deploy    │
   │                                     open :22 for runner /32              │
   │                                     rsync theme → reload FPM → flush     │
   │                                     FastCGI → purge Cloudflare → revoke  │
   └──────────────────────────────────────────────────────────────────────────┘
```

Everything below the double line is Terraform-managed except Cloudflare, which is
configured by `infra/scripts/pgds-cloudflare-setup.sh` and the dashboard. There is no
Cloudflare provider in the Terraform stack.

### 2.1 Why the topology looks like this

**Cloudflare replaces Route 53 + CloudFront + ACM.** That is the single largest cost
decision in the build: the free plan supplies authoritative DNS, edge TLS, and a CDN for
static assets, so the AWS surface shrinks to one instance plus two buckets. AWS DNS and
CDN line items are $0.

**The origin is not reachable from the internet except through Cloudflare.** Ports 80 and
443 admit only Cloudflare's published ranges (`infra/terraform/main/variables.tf:98-131`,
15 IPv4 + 7 IPv6). A direct request to `http://3.1.122.66/` times out — verified. This is
network-level enforcement, not application-level: the intended `X-Origin-Verify` secret
header check is commented out in `infra/nginx/pgds.conf:37-39` and is absent from the live
nginx config, so the security group is the only thing standing between the public internet
and the origin. Adding a Cloudflare IP range to the allowlist is therefore a real trust
decision, not a formality.

**Single instance, single AZ, accepted SPOF.** No load balancer, no auto-scaling group, no
RDS, no multi-AZ. RTO is 30–60 minutes from an AMI. For a six-month site on a $100
operational cap, HA is not cost-justified — this is a deliberate trade recorded in the
proposal, not an oversight.

**Media lives on the instance disk.** No S3 media offload: S3 is not in the Bandwidth
Alliance, so S3→Cloudflare egress still bills at $0.12/GB, while disk→Cloudflare egress
inside the instance's allowance does not. 5.1 GB of 58 GB is in use, so there is ample
headroom.

**No EC2 instance role.** Lightsail has no instance-profile equivalent, and the scripts
were written for Lightsail first, so credentials are static IAM access keys in root-owned
`600` env files (`infra/terraform/main/ec2.tf:197-202`). This is a known weakness,
compensated by least-privilege policies. On EC2 an instance profile would be strictly
better; it was not adopted because the backend flip happened after the scripts existed.

### 2.2 Compute

`aws_instance.app` (`infra/terraform/main/ec2.tf:167-195`):

- Type `t4g.small` (Graviton, ~10% cheaper than the x86 equivalent).
- AMI resolved at plan time from Canonical's SSM parameter
  `/aws/service/canonical/ubuntu/server/24.04/stable/current/arm64/hvm/ebs-gp3/ami-id`
  (`ec2.tf:63-68`) — never a hardcoded ID.
- Root volume: 60 GB `gp3`, `encrypted = true`, `delete_on_termination = false`.
- `http_tokens = "required"` — IMDSv2 only.
- `lifecycle.ignore_changes = [ami, user_data]`.

That `ignore_changes` block is the most important line in the compute config and the
reason production has survived. Without it, two things would destroy the instance on
routine applies: the SSM parameter resolves to a **new AMI ID** every Ubuntu release, and
`user_data` diffs are treated as replacement triggers. The consequence to be explicit
about, spelled out at `ec2.tf:210-234`: **editing `user_data.sh` has no effect on the
running instance.** cloud-init ran it once at first boot. Any change to that script must
be applied over SSH by hand, or it exists only for the next instance built from scratch.

The EIP (`ec2.tf:237-247`) is attached to the instance and survives rebuild, which is what
makes the Cloudflare A record stable across a restore.

### 2.3 RAM budget

2 GB is the binding constraint on this box, and the tuning is derived from it rather than
guessed (`infra/terraform/main/user_data.sh:56-96`):

| Component | Setting | Budget |
|---|---|---|
| OS + nginx | — | ~250 MB |
| MariaDB | `innodb_buffer_pool_size = 256M`, `max_connections = 40` | ~400 MB |
| Redis | `maxmemory 160mb`, `allkeys-lru` | ~160 MB |
| PHP-FPM | `pm = ondemand`, `pm.max_children = 6`, idle timeout 10s | ~360 MB |
| **Total** | | **~1.17 GB** |

Measured on the live host: 578 MB used, 1003 MB in buff/cache, 1256 MB available, swap
0 B used. The configuration is behaving as designed — swap is insurance against OOM, not
capacity, and any sustained swap usage means the tuning is wrong.

Two decisions worth preserving: `pm = ondemand` (not `dynamic`) keeps idle workers from
holding RAM, and the FastCGI cache is on **disk**, not tmpfs — tmpfs would consume the same
2 GB the workers need, so the OS page cache is left to manage it.

### 2.4 Storage

Both buckets are created by a separate bootstrap root (`infra/terraform/bootstrap/`) with
its own local state, so `terraform destroy` on the main stack cannot delete the backups or
the state that describes it. Both carry `prevent_destroy = true`, versioning, SSE-AES256,
and all four public-access blocks set.

| Bucket | Contents | Lifecycle |
|---|---|---|
| `pgds-tfstate-334156771769` | `main/terraform.tfstate` + lockfile | noncurrent versions expire at 90 days, keep 10 |
| `pgds-backup-334156771769` | `db-dumps/` | current and noncurrent expire at 7 days |

State locking uses S3 native `use_lockfile = true` (`versions.tf:15-31`); DynamoDB-based
locking is deprecated and is not used, so there is no second service to pay for.

### 2.5 IAM

Two users, both with static keys, both least-privilege (`infra/terraform/main/iam.tf`):

- **`pgds-backup`** — inline policy `pgds-backup-put-only`. `s3:PutObject` on
  `arn:aws:s3:::pgds-backup-334156771769/db-dumps/*` and `s3:ListBucket` on the bucket
  conditioned to `StringLike s3:prefix = db-dumps/*`. **No `s3:DeleteObject`** — a
  compromised origin can write backups but cannot erase them, which combined with bucket
  versioning is what makes the backup path resistant to a leaked key.
- **`pgds-ses`** — inline policy `pgds-ses-send-only`. `ses:SendEmail` and
  `ses:SendRawEmail`, `Resource = "*"`. Separate from the backup key by design, so alert
  email cannot touch S3 and backups cannot send mail.

Secret-key outputs are marked `sensitive`; access-key IDs are not.

### 2.6 Email

`aws_ses_domain_identity.app` and `aws_ses_domain_dkim.app` are both gated on
`var.domain_name != ""` (`ses.tf:12-20`), and the applied value is `domain_name = ""`
(`terraform.tfvars:15`). **Both resources therefore have `count = 0` and SES DKIM is not
Terraform-managed**, even though the site is live on a real domain. The path is proven —
it was exercised end-to-end against `example.com`, produced 3 DKIM tokens, and destroyed
cleanly — so only the variable value is missing.

SES is still in the **sandbox** (`ProductionAccessEnabled: false`; production access
submitted 2026-09-01, status PENDING). Sending works only to verified recipients, which is
why `pgds-health-alert.sh` targets `success@simulator.amazonses.com` — the sandbox always
accepts it, and it proves the entire send path end to end. The instance also runs
`wp-mail-smtp`; there is no local MTA (`sendmail: not found`), so WordPress transactional
mail depends on that plugin's configuration.

### 2.7 Observability

Three CloudWatch alarms, all `AWS/EC2`, all dimensioned to the instance, all firing to SNS
topic `pgds-alarms` on both alarm and OK transitions (`alarms.tf`):

| Alarm | Metric | Condition |
|---|---|---|
| `pgds-cpu-high` | `CPUUtilization` | Average > 80 for 2 × 300 s |
| `pgds-cpu-credits-low` | `CPUCreditBalance` | Average < 60 for 6 × 300 s |
| `pgds-status-check-failed` | `StatusCheckFailed` | Maximum > 0 for 2 × 60 s |

`treat_missing_data` is `"missing"` for the two CPU alarms and `"breaching"` for the status
check — a box that stops reporting status is treated as broken, which is the correct
default for the one alarm that detects a dead instance.

RAM and disk are deliberately **not** CloudWatch metrics. They are not built-in for EC2,
and custom metrics bill at $0.30/metric-month plus $0.10/alarm-month — 10 metrics + 10
alarms over six months is ~$24, more than the entire backup cost. Instead
`pgds-health-alert.sh` runs every 10 minutes and emails via SES when memory > 85%, root
disk > 80%, swap > 256 MB, or any of nginx / php8.3-fpm / mariadb / redis-server is down,
with a 3-hour cooldown per alert. Cost ~$0.

Four AWS Budgets guard spend: `pgds-lifetime-50`, `pgds-lifetime-160-projection-exceeded`,
`pgds-lifetime-190-credits-nearly-gone` (all ANNUALLY, so they do not reset inside the
six-month life), and `pgds-monthly-run-rate` at $40 (FORECASTED 100% + ACTUAL 80%).

### 2.8 Deploy identity

No AWS access key is stored in GitHub. `aws_iam_openid_connect_provider.github` plus
`aws_iam_role.github_deploy` (`github-oidc.tf`) let Actions federate in, and the trust
policy pins the OIDC subject to GitHub's **immutable** form, which embeds numeric owner and
repository IDs rather than names:

```
repo:pho-veteran@128946325/ws-cms-news-system@1352508712:ref:refs/heads/main
```

Renaming the repo or the org does not silently widen who can assume the role, and only
`refs/heads/main` matches — a branch or a fork cannot.

The role's entire permission set is two mutating actions, scoped to exactly one security
group ARN:

```
ec2:AuthorizeSecurityGroupIngress   on aws_security_group.app[0].arn
ec2:RevokeSecurityGroupIngress      on aws_security_group.app[0].arn
ec2:DescribeSecurityGroups          on *
ec2:DescribeSecurityGroupRules      on *
```

No S3, no SES, no instance control. The role can open an SSH hole in one security group and
close it again; that is all it can do. File transfer authority is a separate SSH key, so
compromising the AWS role alone does not grant a deploy.

---

## 3. Cost

Measured monthly run rate is ~$24.89 (`variables.tf:181-197`): `t4g.small` at $0.0212/h,
60 GB gp3 at $0.096/GB, Elastic IP at $0.005/h. Six months gross ≈ $149. This is roughly
double the $12/month Lightsail figure the proposal costed, entirely because the account
cannot create the `small_3_0` bundle — EC2 bills instance, storage, IPv4, and egress
separately, where the Lightsail bundle folded all four into one price.

Against $200 of remaining credits, net cash is still ~$0. The `$190` budget exists
precisely to fire before the credits run out, and the `$40` monthly run-rate budget leaves
~$15 of headroom over measured spend to catch a runaway — which for this architecture
almost always means egress at $0.12/GB beyond the first 100 GB.

Credits are a payment method, not a discount: gross architectural cost is ~$149 and should
always be reported alongside the ~$0 net.

---

## 4. Full-stack deepdive: front → backend

Four paths matter. Each was verified against the live site.

### 4.1 Anonymous read, warm — the case that must be fast

```
Browser
  └─► Cloudflare edge
        │  Cache Rule 2 matches the extension → HTML? no. Asset? serve from edge.
        │  For HTML: not cached at the edge by design → cf-cache-status: DYNAMIC
        └─► origin :443
              └─► nginx
                    │  $skip evaluated → 0
                    │  fastcgi_cache key "$scheme$request_method$host$request_uri"
                    │  → HIT
                    └─► response from /var/cache/nginx/fcgi, PHP never invoked
```

Measured, two consecutive requests to `/`:

```
req1   x-cache: MISS   cf-cache-status: DYNAMIC
req2   x-cache: HIT    cf-cache-status: DYNAMIC
```

And for a hashed asset:

```
/wp-content/themes/pgds/assets/dist/main.a04ae8eb.css
  cache-control: public, max-age=31536000, immutable
  cf-cache-status: HIT
```

The division of labour is the core of the design: **the edge caches assets, the origin
caches HTML.** HTML is deliberately absent from both Cloudflare cache rules. That choice
removes three problems at once — no cookie-based bypass rule is needed at the edge, there
is no edge stale window to reason about, and an editor's change is visible on the next
request rather than after a TTL. The cost is that every HTML request reaches the origin;
the FastCGI cache is what makes that cheap.

`Cache-Control: immutable` with a one-year max-age is only safe because filenames carry a
content hash (`main.a04ae8eb.css`). This is why the build emits `[contenthash]` names and a
manifest instead of using query-string cache busting.

### 4.2 Anonymous read, cold — the full stack

A cache miss on `/` traverses every layer:

1. **Cloudflare** proxies to the origin over HTTPS. The origin certificate is Let's
   Encrypt (`CN=YR2`, expires 2026-11-30), auto-renewed by the `certbot` cron job.
2. **nginx** evaluates `$skip` (`infra/nginx/pgds.conf:45-51`). Any of these sets it to 1:
   `POST`, non-empty `?s=`, non-empty `?preview=`, a URI matching
   `/wp-admin/|/wp-login\.php|/wp-json/|/wp-cron\.php|/xmlrpc\.php`, or a cookie matching
   `wordpress_logged_in_|wp-postpass_|comment_author_`. `$skip` feeds both
   `fastcgi_cache_bypass` and `fastcgi_no_cache`, so a skipped request neither reads nor
   writes the cache. Here it is 0.
3. A `map` lookup against `/etc/nginx/redirects.map` (26 entries live) returns a 301 target
   for legacy URLs; empty means continue.
4. **Security-ordered deny blocks run before the PHP handler** — PHP under
   `/wp-content/uploads/`, `wp-config.php`, `.ht*`, `readme.html`, `license.txt`, and
   dotfiles other than `.well-known`. The ordering is load-bearing and was learned the hard
   way: placed after the `\.php$` location, these files reached PHP-FPM
   (`infra/nginx/pgds.conf:81-101`).
5. `try_files $uri $uri/ /index.php?$args` hands off to **PHP-FPM** over
   `unix:/run/php/php8.3-fpm.sock`. `pm = ondemand` may need to fork a worker; the ceiling
   is 6.
6. **WordPress boots.** `object-cache.php` (Redis Object Cache plugin, Predis 2.4.0) is
   present and connected, so options, terms, and post meta resolve from Redis at
   `127.0.0.1:6379` instead of MariaDB. `WP_CACHE_KEY_SALT` is set in `wp-config.php`, which
   namespaces keys so a second site on the same Redis cannot collide.
7. **The theme loads.** `functions.php:15-43` defines `PGDS_VERSION`/`PGDS_DIR`/`PGDS_URI`
   then requires 13 modules from `inc/` in a fixed order. The order encodes dependencies:
   `setup` (theme support, image sizes, menus) → `icons` → `nav-walker` → `enqueue` →
   `cpt-tax` (post types + taxonomy) → `meta-fields` → `template-tags` → `query-blocks` →
   `cron` → `seo-schema` → `admin-ux` → `customizer` → `cli-import` (which returns
   immediately unless `WP_CLI` is defined). `functions.php` contains no logic of its own.
8. **Template resolution.** `front-page.php` serves `/`. For a *paged* front page it
   delegates to `index.php` instead (`front-page.php:12-27`), so `/page/2/` is the plain
   chronological list — the curated layout exists only on page 1. Single posts use
   `single.php`, categories use `category.php`, everything else falls back to `archive.php`
   then `index.php`.
9. **The front page runs 15 queries through one dedup pass.** `pgds_home_blocks()`
   (`inc/query-blocks.php:290-432`) calls `PGDS_Used_Ids::reset()`, then executes blocks in
   a fixed order, each marking the IDs it consumed into a request-local static array that
   the next block passes as `post__not_in`. Order matters because earlier blocks have first
   claim: curated feature slots (by `_pgds_feature_rank`) → photo panel → media
   feature/thumbs/bullets → category grids → three-category columns → Vietnam Buddhism →
   site-wide backfill and mixed list → popular sidebar. Two deliberate exceptions: the
   Emagazine/Infographic tab queries bypass the tracker entirely (only one tab is visible
   at a time, so overlap is invisible), and `pgds_query_popular()`'s fallback query can
   reintroduce an already-used post rather than render an empty sidebar.
10. **Asset URLs come from the manifest.** `pgds_manifest()` reads
    `assets/dist/manifest.json` once per request and returns `[]` on any failure — missing,
    unreadable, or invalid JSON. `pgds_asset_url()` then returns `null`, and **neither the
    stylesheet nor the script is enqueued**. The page still renders, unstyled. That is the
    failure mode to recognise: a site that looks broken but throws no error means the build
    did not run. Live manifest:
    `{"main.css":"main.a04ae8eb.css","app.js":"app.f5ba66b6.js"}`.
11. **JSON-LD is emitted on `wp_head`** — `NewsMediaOrganization` at priority 20 (front
    page only), `VideoObject` at 21 (singular posts with `_pgds_youtube_id` and not
    `_pgds_video_unavailable`), optional `NewsArticle` at 22 (off unless
    `PGDS_EMIT_ARTICLE_SCHEMA` is defined). All encoded with `wp_json_encode` and
    `JSON_HEX_TAG | JSON_HEX_AMP`, never string concatenation.
12. **nginx stores the response** for 12 hours (`fastcgi_cache_valid 200 301 302 12h`) and
    stamps `X-Cache: MISS`. `fastcgi_cache_lock on` means concurrent misses for the same key
    wait for one PHP execution instead of stampeding all 6 workers.
    `fastcgi_cache_use_stale error timeout http_500 http_503` serves stale content if PHP
    fails — note `http_502` is absent because nginx rejects it as an invalid value
    (`pgds.conf:120-129`).

**Client-side**, the bundle is one IIFE loaded with `defer` (`inc/enqueue.php:110-116`), and
`jquery-migrate` is dequeued on the front end. `index.js` boots three modules on
`DOMContentLoaded`: mobile nav, YouTube facade, media tabs. Fonts are preloaded at
`wp_head` priority 1 and self-hosted, so no third-party font request is made.

The **video facade** is the one performance behaviour worth understanding.
`template-parts/video-facade.php` renders a `<figure data-pgds="youtube-facade">` with a
poster image and a play button — **no iframe**. Only on click does
`src/js/modules/youtube-facade.js` build one pointing at
`youtube-nocookie.com/embed/<id>?autoplay=1&rel=0&modestbranding=1`, remove the poster, and
move focus into it. No YouTube JavaScript, cookies, or requests exist until a reader asks
for the video. If `_pgds_video_unavailable = '1'`, the facade is replaced by a plain
"Video không còn khả dụng" line and the `VideoObject` schema and sitemap entry are both
dropped — one meta key governs all three.

### 4.3 Editor writes a post — cache invalidation

```
wp-admin  (Cloudflare Rule 1 bypass · nginx $skip=1 · X-Cache: BYPASS — verified)
  └─► PHP-FPM → MariaDB write
        └─► transition_post_status fires
              └─► pgds_flush_page_cache()  (mu-plugin)
                    └─► recursive unlink of /var/cache/nginx/fcgi
                          └─► next anonymous request: X-Cache: MISS, fresh HTML
```

`wp-content/mu-plugins/pgds-cache-flush.php` hooks `transition_post_status` (**not**
`save_post`, which `CLAUDE.md` and `RUNBOOK.md:65` both still claim), plus `deleted_post`,
`edited_term`, `wp_update_nav_menu`, `switch_theme`, and `customize_save_after`. It flushes
when either the old or new status is `publish`, skipping autosaves and revisions — so
publishing, editing a published post, unpublishing, and trashing all purge, while drafting
does not.

Before deleting anything it validates the target: it `realpath`s the directory and refuses
filesystem roots, `ABSPATH`, `WP_CONTENT_DIR`, and any directory containing `wp-config.php`
or `wp-load.php`. Symlinks are unlinked rather than followed. This matters because the
function's whole job is a recursive delete driven by a constant that `wp-config.php` can
override.

Scope is worth being precise about: this purges the **origin** FastCGI cache only. It makes
no Cloudflare API call. That is coherent, because the edge never holds HTML — but it also
means an editor's change to a **static asset** is not reflected at the edge by this path.
Only a deploy purges Cloudflare.

Because HTML lives only in the origin cache and is purged on write, the edge and origin can
never disagree about article content. That property is the reason for the "HTML is not
cached at the edge" rule, and any change that starts caching HTML at Cloudflare would
invalidate it.

### 4.4 Deploy — origin first, edge second

`.github/workflows/deploy.yml`, triggered on push to `main`, serialized by concurrency
group `pgds-production-deploy` with `cancel-in-progress: false` so two deploys never
interleave.

Gates: `lint-php` (`php -l` across theme + mu-plugins) and `lint-js` (`eslint src/js/**`)
run in parallel; `build` needs both; `deploy` needs `build`. The build gate asserts the
manifest exists, is valid JSON, names `main.css`/`app.js`, and that both point at files
matching `^main\.[a-f0-9]{8}\.css$` / `^app\.[a-f0-9]{8}\.js$` which are actually present.

The deploy job then:

1. Rebuilds the theme itself (`npm ci`, `npm run fonts`, `npm run build`) rather than
   downloading the `build` job's artifact.
2. Writes the SSH key and pinned `known_hosts` at mode `600`.
3. Federates to AWS via OIDC — `id-token: write`, `aws-actions/configure-aws-credentials@v6`
   assuming `AWS_DEPLOY_ROLE_ARN`. No long-lived key.
4. **Opens SSH for itself.** It first revokes stale port-22 rules left by earlier runs,
   discovers its own egress IP via `api.ipify.org` with `checkip.amazonaws.com` as fallback,
   validates it is genuinely IPv4 through Python's `ipaddress`, and authorizes exactly that
   `/32` with a description carrying the run ID and attempt.
5. `rsync -az --delete` of `wp-content/themes/pgds/` to the remote theme path, excluding
   `node_modules/ src/ tools/ build.mjs package.json package-lock.json eslint.config.mjs
   README.md`. `assets/dist/` is **not** excluded — the built output is the payload. Blast
   radius is the theme directory; core, database, and plugins are never touched.
6. **Purge, in this exact order:** `sudo systemctl reload <php-fpm> && sudo find
   <fastcgi_cache_dir> -type f -delete`. The `&&` means a failed FPM reload aborts the flush
   rather than flushing against stale opcode.
7. Purge Cloudflare (`purge_everything`) **only if** `git diff HEAD^ HEAD` touched
   `src/`, `build.mjs`, `package-lock.json`, or `assets/fonts/`.
8. Revoke the SSH rule — `if: always()`, `continue-on-error: true`.

Order is the business rule: **origin first, edge second.** Reversed, the edge would refetch
and re-cache the old asset from an origin that had not yet reloaded.

Three ways this pipeline can fail quietly, worth knowing before trusting a green check:

- **The edge purge can be skipped when it was needed.** Detection compares only `HEAD^` to
  `HEAD`. A push containing several commits where an earlier one changed `src/` but the last
  one did not evaluates `changed=false`, and the edge keeps serving the old asset. Hashed
  filenames limit the damage, but the purge did not happen.
- **The Cloudflare response body is not inspected.** `curl --fail-with-body` catches
  transport and HTTP failure; a `200` carrying `"success": false` passes.
- **The SSH rule can survive the run.** The revoke step is `continue-on-error: true`, its
  AWS lookups end in `|| true`, and the final audit only warns. A hard runner kill between
  authorize and cleanup leaves port 22 open to that `/32` until the next deploy's stale-rule
  sweep or a manual removal. It is bounded, not impossible.
- **PHP lint passes vacuously if no files match.** `xargs -0 -r` does not invoke `php -l`
  when `find` returns nothing, and `-r` suppresses the empty-input error.

### 4.5 Scheduled work

`/etc/cron.d/pgds`, verified live:

| Schedule | User | Job |
|---|---|---|
| `17 3,15 * * *` | root | `pgds-db-backup.sh` — mysqldump → gzip -9 → S3 `db-dumps/` |
| `*/10 * * * *` | root | `pgds-health-alert.sh` — RAM/disk/swap/services → SES |
| `*/5 * * * *` | www-data | `wp cron event run --due-now` |

WP-Cron runs from the system scheduler rather than being triggered by visitor requests —
which is what makes it reliable on a cached site where most requests never reach PHP.

The backup script is defensive in the ways that matter: it dumps with
`--single-transaction --quick --routines --triggers --events`, **rejects a dump ≤ 1024
bytes**, validates the gzip stream before upload, uploads with SSE-AES256, and keeps the two
newest dumps locally in `/var/backups/pgds`. The size floor is the important one — it is
what stops a silently-empty dump from overwriting good backups.

**There is no snapshot cron on this host.** `pgds-snapshot.sh` carries a `41 2 * * *`
example in its comments, but `user_data.sh` never installs it and the live `cron.d/pgds` has
only the three jobs above. AMI snapshots are manual or CI-driven. If the intent was daily
rolling AMIs, that intent is currently not implemented — worth resolving explicitly, since
the documented RTO of 30–60 minutes assumes a recent snapshot exists.

---

## 5. Security posture

What is actually in place:

- Origin unreachable except from Cloudflare ranges (network-enforced, verified).
- SSH: key-only, `PasswordAuthentication no`, `PermitRootLogin no`, port 22 restricted to
  one admin `/32` plus transient CI `/32`s.
- fail2ban active with two jails: `sshd` and `wordpress-auth`.
- `DISALLOW_FILE_EDIT = true`; no theme/plugin editing from the admin.
- `two-factor` plugin installed for admin accounts.
- PHP execution denied under `/wp-content/uploads/`; `wp-config.php`, dotfiles, and
  `readme.html`/`license.txt` denied — with the deny blocks correctly ordered *before* the
  PHP handler.
- IMDSv2 required, so SSRF cannot read instance metadata via IMDSv1.
- Backup IAM key cannot delete; bucket versioned.
- Deploy uses OIDC federation, immutable-subject-pinned, with a two-action permission set.
- Redis and MariaDB bound to localhost only.

Open items, stated plainly:

- **The `X-Origin-Verify` secret-header check is not active.** Commented out in
  `infra/nginx/pgds.conf:37-39` and absent from the live config. Defence against direct-IP
  access is the security group alone. This was designed as two layers; one is deployed.
- **Static IAM keys on the instance** (no instance profile), root-owned mode 600.
- **`wp-login.php` is reachable and edge-bypassed**, so password strength is the only thing
  standing between the internet and the admin. `admin` (ID 1) and `pgdsadmin` (ID 2) are
  both administrators; both now hold generated random passwords.
- **SES sandbox** — alerts reach only verified recipients until production access is
  granted.
- **SCP cannot be used** on a standalone account. An IAM deny policy constrains the
  operational user but not root or other administrators.

---

## 6. Recovery

- **Database**: dumps every 12 hours to S3, 7-day retention, versioned bucket, two newest
  copies also on local disk.
- **Whole instance**: `pgds-snapshot.sh` creates a no-reboot AMI, tags it and its EBS
  snapshots, retains 4, deregisters older images and deletes their backing snapshots — but
  see §4.5: nothing schedules it.
- **Elastic IP** survives instance replacement, so a restore does not require a DNS change
  or wait for propagation.
- **Terraform state** is versioned with 90-day noncurrent retention, in a
  `prevent_destroy` bucket, in a separate root from the resources it manages.

The gap between "RTO 30–60 minutes" and "no snapshot schedule" is the most consequential
open item in this document. Restore has also never been exercised against the EC2 backend —
the proposal's Day-3 restore test was written for Lightsail.

---

## 7. Drift register — repo vs. reality

Reconciled 2026-09-02. Every row below was a place the repo would have misled someone who
trusted the file over the host; the Status column records what was done about it.

| # | Claim in repo | Reality | Status |
|---|---|---|---|
| 1 | Lightsail `small_3_0` @ $12/mo | EC2 `t4g.small` @ ~$24.89/mo; account capped at `micro_3_0` | **Documented** — proposals kept as the design record; §1 and §3 here carry the deployed figures |
| 2 | "there is no domain, so no zone exists" | `vihn.id.vn` live, proxy active | **Fixed** — `RUNBOOK.md` §7b now opens with a verified cutover-done banner |
| 3 | Cutover steps written as pending | Cutover completed | **Fixed** — each step marked DONE / NOT DONE / DENIED |
| 4 | `server_name phatgiaovadoisong.vn www.…` | Live nginx uses `server_name _` | **Fixed** — `infra/nginx/pgds.conf` reconciled from the served config |
| 5 | nginx config is port 80 only, no TLS | Live serves 443 | **Fixed** — same sync |
| 6 | TLS design is Cloudflare **Origin CA** | **Let's Encrypt** (`CN=YR2`, exp. 2026-11-30), certbot-renewed | **Documented** — Let's Encrypt kept deliberately (certbot automates renewal; an Origin CA key is a manual 15-year rotation). Rationale recorded in `pgds.conf` and RUNBOOK step 2 so it is not "corrected" back |
| 7 | FastCGI zone belongs in `nginx.conf` http block | Lives in `/etc/nginx/conf.d/pgds-cache.conf` | **Benign** — equivalent context, different file; no change needed |
| 8 | Cache flush hooks `save_post` | Hooks `transition_post_status` + 5 others | **Fixed** — `CLAUDE.md` and `RUNBOOK.md` §3 corrected, with the reason `save_post` was wrong |
| 9 | Budgets $50 / $85, monthly $45 | $50 / $160 / $190, monthly $40 | **Fixed** — `infra/terraform/README.md` + `variables.tf` description now match `budgets.tf` |
| 10 | Snapshot cron `41 2 * * *` | Nothing created snapshots on a schedule | **Fixed in infrastructure** — DLM policy `policy-04ea69769e8b12ff7` applied; misleading cron example removed from the script |
| 11 | SES DKIM Terraform-managed | `domain_name = ""` → SES resources `count = 0` | **Documented** — identity exists outside Terraform and is verified; RUNBOOK step 4 explains the import needed to bring it under management |
| 12 | `X-Origin-Verify` blocks direct IP | Commented out; absent from live config | **Documented, still open** — RUNBOOK §7c gives the ordered enable procedure. Needs a Cloudflare Transform Rule; enabling nginx first 403s everything |
| 13 | `§5` resize procedure | Lightsail-only steps; backend is EC2 | **Fixed** — EC2 in-place resize documented, with the RAM re-tune the new size requires |
| 14 | SES production access "PENDING" | **DENIED** — `ReviewDetails.Status: DENIED` | **Fixed in docs, action open** — AWS does not re-review on its own; someone must re-request. Alerts reach nobody real until then |

Items 4–6 shared one root cause, now addressed: `lifecycle.ignore_changes = [user_data]`
plus a first-boot-only bootstrap means Terraform never deploys the nginx config, so it was
hand-applied over SSH and drifted silently. `infra/nginx/pgds.conf` now mirrors the served
file, and its header documents the install commands and the trap that caused the drift.

**Two traps found while reconciling**, both worth knowing before touching nginx again:

- `sites-enabled/pgds.conf` on the origin is a **regular file, not a symlink**, and its
  contents had diverged from `sites-available/pgds.conf`. nginx serves `sites-enabled`, so
  editing `sites-available` changes nothing. Check `readlink -f` before assuming.
- The two files differed in exactly the way that matters: `sites-available` carries
  `http2 on;`, which **nginx 1.24.0 rejects** (`[emerg] unknown directive "http2"` — it
  arrived in 1.25.1). Re-symlinking to "fix" the drift would have taken the origin down.
  The repo now carries the working `listen 443 ssl http2` form.

Two items remain genuinely open and need a person, not more code: the SES re-request
(#14 — and until then, verifying the operator's mailbox as an identity is the cheap fix that
makes alerting real today) and the origin header check (#12).

---

## 8. Verifying this document

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

Note `~/.ssh/pgds-deploy.pem` does **not** authenticate despite
`key_pair_name = "pgds-deploy"`; the working keys are `pgds-deploy-ec2.pem` and
`pgds-ec2.pem`.
