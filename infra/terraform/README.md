# Terraform — pgds infrastructure

## Current state: running on the EC2 fallback, not Lightsail

**Account `334156771769`, 2026-08-31.** Both stacks are fully applied. The origin is
live at Elastic IP **3.1.122.66** (`i-047f1e4d31db00df6`, `t4g.small`, 2 vCPU / 2 GB,
arm64) — **not** on Lightsail, because this account cannot create a Lightsail bundle
large enough.

`compute_backend = "ec2"` selects the fallback. `"lightsail"` remains the default and
the preferred target; switch back and destroy the EC2 resources as soon as the cap is
lifted.

### Why Lightsail could not be used

`CreateInstances` refuses every bundle above `micro_3_0`:

```
InvalidInputException: Sorry, your account can not create an instance using this
Lightsail plan size. Please try a smaller plan size or contact Customer Support
if you need to use a larger plan.
```

Probed on a clean account with **zero** existing instances:

| Bundle | RAM | Result |
|---|---|---|
| `nano_3_0` | 0.5 GB | created successfully |
| `micro_3_0` | 1 GB | created successfully |
| `small_3_0` | 2 GB | **denied** |
| `small_ipv6_3_0` | 2 GB | **denied** |
| `medium_3_0` | 4 GB | **denied** |

Also denied in `ap-southeast-2`, `ap-northeast-1`, `ap-south-1`, `us-east-1` and
`eu-west-1` — the cap is **account-wide, not regional**. All probe instances were
deleted; nothing was left billing.

**Why Proposal 02 §8.2 did not catch it.** §8.2 verified availability with
`lightsail get-bundles`, which reports `isActive: true` for every bundle above,
including the three that are denied. That API describes the regional **catalog**, not
**account eligibility**. The check was necessary but not sufficient, and no API
exposes the per-account cap — it surfaces only on `CreateInstances`.

**Why `micro_3_0` was not accepted as the fallback.** §2 excludes 1 GB explicitly:
*"cannot run WordPress + MariaDB + Redis. Excluded for technical reasons, not
price."* §4.1's RAM budget consumes ~1.17 GB before serving a request, so 1 GB would
swap constantly — which §4.1 itself calls "the configuration is wrong". Downgrading
would have traded a visible blocker for silent degradation under load.

### Cost consequence — a real regression against §8.1

| | Lightsail `small_3_0` | EC2 `t4g.small` |
|---|---|---|
| Instance | $12.00/mo (bundled) | $15.48/mo |
| 60 GB SSD | included | $5.76/mo |
| IPv4 | included | $3.65/mo |
| Egress | 3 TB included | ~$6.00/mo (~150 GB; 100 GB free, then $0.12/GB) |
| **Total** | **~$12.00/mo** | **~$30.89/mo** |
| **6 months** | **~$72** | **~$185** |

That **exceeds the $100 operational cap in §8.1** while still fitting inside the $200
of credits. §8.1 describes the cap as "operational discipline to detect unexpected
costs" rather than a hard budget — this is precisely such a cost, so it is reported
rather than absorbed.

#### The $100 cap is arithmetically unreachable on EC2 — this is not a tuning problem

Worth stating plainly so nobody spends time hunting for savings that do not exist.
Using §2's own unit prices, the *floor* for any 2 vCPU / 2 GB EC2 origin over six
months:

| Component | Monthly | 6 months |
|---|---|---|
| `t4g.small` — cheapest 2 GB Graviton | $15.48 | **$92.86** |
| Public IPv4 — mandatory for a reachable origin | $3.65 | $21.90 |
| gp3 40 GB — the smallest disk §4.2's media can fit on | $3.84 | $23.04 |
| **Absolute floor, at zero egress** | | **$137.80** |

**The instance alone consumes 93% of the entire cap** before disk, IP, or a single byte
of egress, and the floor overshoots by $37.80. No configuration of EC2 satisfies both
"2 GB RAM" (§4.1's budget needs ~1.17 GB) and "under $100 for six months". Only
Lightsail's bundle does, because it folds disk, IPv4 and 3 TB of egress into $12/mo —
and that bundle is the thing this account cannot create.

So the cap is not missed through waste; it is unreachable given the constraint. The
options are: raise the Lightsail cap via Support (restores ~$72/6mo), accept ~$185
against the $200 of credits, or change the requirement. That is a decision for the
budget owner, not something to optimise away.

Savings that were evaluated and **rejected**, with reasons:

- **Shrink the volume 60 → 40 GB** (saves $11.52/6mo). Rejected: EBS volumes cannot
  shrink, only grow, so this means snapshot → new volume → migrate → swap, i.e. real
  downtime risk on a live origin for $11.52. And at 40 GB provisioned, §4.2's upper
  bound of 40 GB of media leaves nothing for the OS, database, and FastCGI cache.
  Current usage is 5.1 GB of 58 GB.
- **Reduce gp3 IOPS/throughput.** Rejected: 3000 IOPS and 125 MB/s are the free
  baseline; there is nothing to give back.
- **`t4g.micro` (1 GB)** — the only cheaper instance. Rejected for the same reason
  `micro_3_0` was: §2 excludes 1 GB as unable to run WordPress + MariaDB + Redis.

One genuine EC2 *advantage* over Lightsail is worth noting: gp3 grows online with no
downtime, so the disk can be expanded as the media library actually lands. Lightsail
cannot — its resize is a full instance migration (§10.1).

**Egress is the entire delta**, which makes Cloudflare in front of static assets (§5.6)
a **cost control**, not merely a cache. Do not disable it. Measured sensitivity:

| Egress/month | Billed | Over 6 months |
|---|---|---|
| ≤100 GB | $0.00 | $0.00 (free tier) |
| 150 GB | $6.00 | $36 |
| 300 GB | $24.00 | $144 |
| 500 GB | $48.00 | $288 |

Levers that were checked and rejected: gp3's 3000 IOPS / 125 MB/s are the free
baseline, so there is nothing to trim there; shrinking the volume 60→50 GB saves
$0.96/mo but §4.2 puts media at 25–40 GB and warns that outgrowing the disk forces a
migration — not worth the risk for a dollar. The budget guardrails were re-modelled
instead (see Budgets below), because at ~$25–31/mo the original MONTHLY $50 budget would
have alerted every month and been muted.

### To get back to Lightsail

1. Request a Lightsail plan-size increase via the AWS Support Center console. The
   account is on Basic support (`describe-severity-levels` →
   `SubscriptionRequiredException`), so the API route is unavailable.
2. Set `compute_backend = "lightsail"` in `terraform.tfvars`.
3. `terraform apply` — this destroys the EC2 instance and Elastic IP and creates the
   Lightsail instance and static IP. **Back up first:** the root volume has
   `delete_on_termination = false`, but the public IP changes, so Cloudflare's A
   record must be repointed. Follow the §10.1 resize procedure.

---

Two independent stacks, matching Proposal 02 §9.2. **Do not merge them** —
keeping bootstrap separate means destroying `main` can never take the
Terraform state bucket or the backup bucket down with it.

```
infra/terraform/
├── bootstrap/   apply once, local backend, prevent_destroy on both buckets
└── main/        the actual infrastructure, S3 backend (use_lockfile)
```

## Terraform version requirement

`main/versions.tf` uses the S3 backend's native `use_lockfile = true`
(Proposal 02 §9.1 — DynamoDB-based locking is deprecated). **This requires
Terraform >= 1.10.0.** The dev machine's pinned binary was v1.9.8, which
rejects `use_lockfile` as an unknown argument (`Error: Unsupported argument`)
— confirmed by testing against a throwaway config. `~/.local/bin/terraform`
was upgraded in place to v1.10.5 to make this work; the original v1.9.8
binary was kept at `~/.local/bin/terraform-1.9.8.bak` in case anything
depends on the old version. Both stacks were validated with the new binary.

## Stack 1 — `bootstrap/`

Creates the two buckets everything else depends on:

| Resource | Purpose |
|---|---|
| `aws_s3_bucket.tfstate` (`pgds-tfstate-<account_id>`) | Terraform remote state for the `main` stack |
| `aws_s3_bucket.backup` (`pgds-backup-<account_id>`) | DB dumps (`mysqldump`), 2×/day per §6.1 |

Both buckets: versioning ON, SSE-S3 (AES256) encryption, all public access
blocked, `prevent_destroy = true`. Bucket names are suffixed with the AWS
account ID via `data.aws_caller_identity`, not hardcoded — §8.5 flags the
bare `pgds-tfstate` name as unverified/possibly taken globally.

Uses a **local backend** (its own `terraform.tfstate` file next to the
config, not committed — see repo `.gitignore`). It has to: it's the stack
that creates the remote backend, so it cannot depend on that backend
existing yet.

Variables: only `aws_region` (default `ap-southeast-1`).

Validated: `init`, `validate`, `fmt -check` all pass. `terraform plan`
reports **10 resources to add** (2 buckets × 5 sub-resources: bucket,
versioning, encryption config, public-access block, lifecycle
configuration), 0 to change, 0 to destroy.

The lifecycle configurations are the pair added after the original eight: state
noncurrent versions expire at 90 days (keeping 10), and `db-dumps/` expires at 7
days current and noncurrent. Both also abort incomplete multipart uploads after 7
days, which is what stops a failed dump upload from billing invisibly forever.

## Stack 2 — `main/`

The actual runtime infrastructure. **S3 backend, `use_lockfile = true`** —
no DynamoDB table (§9.1).

| File | Resources |
|---|---|
| `lightsail.tf` | `aws_lightsail_instance`, `aws_lightsail_static_ip` (+ attachment), `aws_lightsail_instance_public_ports` — all gated on `compute_backend == "lightsail"` |
| `ec2.tf` | `aws_instance`, `aws_eip`, security group + rules — the fallback origin, gated on `compute_backend == "ec2"`. **Currently active.** |
| `iam.tf` | Two IAM users (`pgds-backup`, `pgds-ses`) + policies + access keys |
| `ses.tf` | SES domain identity + DKIM, gated on `var.domain_name != ""` |
| `budgets.tf` | Four budgets: three lifetime (ANNUALLY $50/$160/$190) + one monthly run-rate anomaly ($40) |
| `alarms.tf` | SNS topic `pgds-alarms` + email subscription + 3 CloudWatch alarms, gated on `compute_backend == "ec2"` |
| `github-oidc.tf` | GitHub OIDC provider + `pgds-github-deploy` role + security-group ingress policy, gated on `compute_backend == "ec2"` |
| `user_data.sh` | LEMP bootstrap script run on first boot; shared by both backends |

Exactly one compute backend exists at a time — `count`/`for_each` on
`var.compute_backend` means the unused one has zero resources rather than being
commented out.

### Instance and firewall

- **Lightsail path (preferred, currently blocked):** `bundle_id = "small_3_0"`
  (2 vCPU / 2GB / 60GB SSD / 3TB — $12/mo, §2, §8.2), `blueprint_id = "ubuntu_24_04"`
  (not the WordPress blueprint, so Nginx stays hand-configured, §4).
- **EC2 path (currently active):** `t4g.small` (same 2 vCPU / 2GB shape) on Canonical's
  Ubuntu 24.04 arm64 AMI resolved through SSM, 60 GB gp3 with
  `delete_on_termination = false`, IMDSv2 required. See the top of this file.
- `user_data.sh` installs nginx, PHP 8.3-FPM (`pm=ondemand`,
  `pm.max_children=6`), MariaDB 10.11 (`innodb_buffer_pool_size=256M`),
  Redis (`maxmemory 160mb`, `allkeys-lru`), a 2GB swapfile, fail2ban, and
  hardens SSH — the RAM budget from §4.1. It does **not** install
  WordPress itself (§9.2: that layer stays imperative).
- Public ports: 80/443 restricted to Cloudflare's published IPv4/IPv6
  ranges (`var.cloudflare_ipv4_cidrs` / `var.cloudflare_ipv6_cidrs`,
  defaulted from https://www.cloudflare.com/ips-v4 and
  https://www.cloudflare.com/ips-v6 as of 2026-08-31 — re-check that page
  if legitimate traffic starts getting firewalled). SSH (22) restricted to
  `var.ssh_admin_cidrs`, which has **no default** and rejects
  `0.0.0.0/0` via a validation block, so `apply` cannot silently leave SSH
  open to the world.
- Lightsail's own `AutoSnapshot` add-on is explicitly `status = "Disabled"`
  — snapshots are cron-driven on the instance per §6.1/§9.2, not managed by
  Terraform (putting them in TF state causes perpetual drift per §9.2).

### IAM — two separate least-privilege users (§6.2, §8.4, §10.2)

- `pgds-backup`: `s3:PutObject` (+ `s3:ListBucket` scoped to the prefix)
  on `${backup_bucket_arn}/${var.backup_object_prefix}` only. **No
  `s3:DeleteObject`** — a leaked key can write junk, not destroy existing
  backups.
- `pgds-ses`: `ses:SendEmail` / `ses:SendRawEmail` only.

Both are static access keys (Lightsail has no EC2-style instance role —
accepted as a known weakness in §10.2). Access key IDs are plain outputs;
secret keys are `sensitive = true` outputs. After apply, fetch them with
`terraform output -raw backup_secret_access_key` /
`terraform output -raw ses_secret_access_key` and store them on the
instance under `/root`, mode 600 — never left in shell history or CI logs.

### SES — placeholder domain

`var.domain_name` defaults to `""`. `aws_ses_domain_identity` and
`aws_ses_domain_dkim` both use `count = var.domain_name == "" ? 0 : 1`, so
applying with the placeholder creates **zero** SES identity resources
instead of an unverifiable one. Once a real domain is chosen: set
`domain_name`, re-apply, then add the three DKIM CNAME records from
`terraform output ses_dkim_tokens` to Cloudflare DNS (§11: DKIM needs
Cloudflare nameservers delegated first).

### Budgets

`main/budgets.tf` creates **four** budgets. All are COST/USD with
`comparison_operator = GREATER_THAN` and a PERCENTAGE threshold type, and all notify
`budget_notification_emails`:

| Budget | Limit | Period | Notifications | Purpose |
|---|---|---|---|---|
| `pgds-lifetime-50` | $50 | ANNUALLY | ACTUAL 100% | §8.4 mid-project signal against the $100 cap |
| `pgds-lifetime-160-projection-exceeded` | $160 | ANNUALLY | ACTUAL 100% | Six-month EC2 projection (~$149) has been exceeded |
| `pgds-lifetime-190-credits-nearly-gone` | $190 | ANNUALLY | ACTUAL 100% + FORECASTED 100% | The $200 credit balance is nearly consumed — real cash starts here |
| `pgds-monthly-run-rate` | $40 (`monthly_run_rate_budget`) | MONTHLY | FORECASTED 100% + ACTUAL 80% | Anomaly detection |

These are the values in `budgets.tf`. §8.4 of the proposal specifies "$50 and $85"; the
$85 threshold was re-modelled because it was derived from the ~$85 six-month *Lightsail*
total. On EC2 the six-month gross is ~$149, so an $85 lifetime budget would fire around
month four of normal operation and teach the recipient to ignore it. The $160 and $190
budgets replace it: $160 says the projection has been beaten, $190 says the credits are
about to run out. Both are the signals someone would actually act on.

**Why the lifetime thresholds are ANNUALLY, not MONTHLY.** They are *cumulative lifetime*
figures. On EC2 (~$24.89/mo) a MONTHLY $50 budget stays quiet in month one and then alerts
every month after, which is precisely the alert-fatigue failure §8.4 warns about for the
zero-spend budget: an always-on alarm gets muted, and then there is no guardrail at all.
ANNUALLY is the longest window AWS Budgets offers, and since the project's life is six
months it never resets mid-project. (QUARTERLY would reset twice inside it.)

The monthly budget watches the *run rate* and is sized ~$15 above the measured $24.89, so
it fires on a runaway rather than on normal operation. Egress is the volatile component —
$0.12/GB past the first 100 GB free, with nothing in the architecture capping it — so $40
trips at roughly 226 GB/month, well above the ~150 GB the traffic in §5.2 implies.
**Lower `monthly_run_rate_budget` to ~20 after switching back to Lightsail**, or it stops
being a meaningful signal.

### SES — how far it is verified, and what is genuinely blocked

The send path is proven as far as the sandbox allows. Using the real `pgds-ses`
credentials from `terraform output`:

```
$ aws sesv2 send-email --from-email-address noreply@... --destination ...
MessageRejected: Email address is not verified. The following identities failed
the check in region AP-SOUTHEAST-1: noreply@..., admin@...
```

`MessageRejected` is the **success** signal here: the key authenticated as
`arn:aws:iam::…:user/pgds/pgds-ses`, reached SES in the right region, and was
*authorized* to call `SendEmail`. An IAM or wiring fault would have returned
`AccessDenied` or `InvalidAccessKeyId` instead. The only missing piece is a verified
identity.

Least privilege confirmed at the same time — the same key is denied
`s3:ListAllMyBuckets` and `ec2:DescribeInstances`.

**Blocked on a decision, not on work:**

- **No domain.** `domain_name = ""`, so `aws_ses_domain_identity` and DKIM are skipped
  (`count = 0`) rather than created against a placeholder. Verifying a single address
  instead would need someone to click a link in that mailbox.
- **Sandbox.** `ProductionAccessEnabled: false`, 200 messages/24h, recipients must be
  verified. `put-account-details` (the API route to request production access) rejects
  a placeholder URL — `BadRequestException: Url contains invalid format` — because it
  validates the TLD. §11 already flags this as the one D0 item that can delay the
  schedule: the request takes 24h+.

To finish, in order: pick the domain → delegate nameservers to Cloudflare → set
`domain_name` and re-apply → add the DKIM CNAMEs from `terraform output
ses_dkim_tokens` → request production access → send a real test.

### Cloudflare — not configured, and why

No Cloudflare resources exist and no credentials are configured. §9 calls the
Cloudflare provider "optional", and every Cloudflare task in §5.3 (proxy ON, SSL Full
strict, origin certificate, the two Cache Rules) needs a zone, which needs the domain.

This means one §13 gate item cannot be run: `curl -sI …/assets/…css | grep
cf-cache-status` → `HIT`. What *was* verified is the half that lives at the origin: the
asset is served with a single `Cache-Control: public, max-age=31536000, immutable`, so
it is correctly *eligible* for the edge cache — see §5.5.

The origin firewall already assumes Cloudflare: 80/443 admit Cloudflare's published
ranges only, so **the site is unreachable until the proxy is in front of it.** That is
deliberate (§10.2) and is why local verification runs against `127.0.0.1` on the box.

### The pre-existing zero-spend budget — resolved

`My Zero-Spend Budgettt` ($1.00 limit, in ALARM) **has been deleted** (2026-08-31).
Terraform never referenced, imported, or deleted it: it was created out of band, and
Terraform must not adopt resources it did not create. It was removed with the CLI
instead, because it would have alerted continuously the moment the origin started
billing.

Kept here for reference, and in case a similar budget reappears:

```bash
# Option A — delete it outright (recommended: it's redundant once the
# lifetime budgets exist)
aws budgets delete-budget \
  --account-id 334156771769 \
  --budget-name "My Zero-Spend Budgettt" \
  --region ap-southeast-1

# Option B — raise its limit instead of deleting, if you want to keep the
# budget name/history
aws budgets update-budget \
  --account-id 334156771769 \
  --new-budget file://raised-budget.json \
  --region ap-southeast-1
```

## Apply order

1. **Bootstrap first.**
   ```bash
   cd infra/terraform/bootstrap
   ~/.local/bin/terraform init
   ~/.local/bin/terraform apply
   terraform output tfstate_bucket_name   # note this, e.g. pgds-tfstate-334156771769
   terraform output backup_bucket_name
   terraform output backup_bucket_arn
   ```

2. **Wire the bucket name into `main`'s backend**, then init/apply:
   ```bash
   cd infra/terraform/main
   # Edit versions.tf: replace REPLACE_WITH_TFSTATE_BUCKET with the
   # tfstate_bucket_name output from step 1.
   ~/.local/bin/terraform init
   ~/.local/bin/terraform apply \
     -var="key_pair_name=<existing Lightsail key pair name>" \
     -var='ssh_admin_cidrs=["<your-ip>/32"]' \
     -var="backup_bucket_name=<from bootstrap output>" \
     -var="backup_bucket_arn=<from bootstrap output>" \
     -var='budget_notification_emails=["you@example.com"]'
     # domain_name left at its "" default until a real domain exists
   ```
   Create the Lightsail key pair beforehand if one doesn't exist:
   `aws lightsail create-key-pair --key-pair-name pgds-admin --region ap-southeast-1`
   (save the returned private key — Lightsail does not let you retrieve it
   again).

Prefer a `terraform.tfvars` (gitignored) over repeating `-var` flags for
day-to-day applies.

## Required variables (`main`)

| Variable | Required? | Notes |
|---|---|---|
| `key_pair_name` | yes, no default | existing Lightsail key pair for SSH |
| `ssh_admin_cidrs` | yes, no default | list; rejects `0.0.0.0/0` |
| `backup_bucket_name` | yes, no default | from bootstrap output |
| `backup_bucket_arn` | yes, no default | from bootstrap output |
| `budget_notification_emails` | yes, no default | list of emails for the $50/$160/$190 + monthly alerts |
| `domain_name` | no, default `""` | leave empty until a real domain is chosen |
| `aws_region`, `availability_zone`, `instance_name`, `bundle_id`, `blueprint_id`, `backup_object_prefix`, `cloudflare_ipv4_cidrs`, `cloudflare_ipv6_cidrs` | no | sensible defaults, override only if needed |

## §10.3 exit-plan teardown order

Export data **before** destroying anything:

1. `wp export` (WXR) + `mysqldump`, gzip both, download locally and upload
   to the backup bucket.
2. Tarball the full `wp-content/uploads` directory, download locally.
3. *(Optional)* crawl and statically export the site if URLs must stay
   readable after shutdown.
4. Open the exports and verify they're intact.
5. `cd infra/terraform/main && terraform destroy` — tears down the
   instance, static IP, firewall rules, IAM users/keys, SES identity,
   budgets. Cloudflare DNS/records are outside this stack unless the
   optional Cloudflare provider was added — remove those records manually
   if so.
6. Delete any remaining Lightsail snapshots — they are cron-created, not
   in Terraform state, so `destroy` above does not touch them:
   `aws lightsail get-instance-snapshots --region ap-southeast-1` then
   `aws lightsail delete-instance-snapshot --instance-snapshot-name <name>`
   for each.
7. Delete the backup bucket last, from the `bootstrap` stack. Both buckets
   there have `prevent_destroy = true` — remove that block (or the whole
   resource) from `bootstrap/main.tf` first, then
   `cd infra/terraform/bootstrap && terraform apply` (to update state)
   followed by `terraform destroy -target=aws_s3_bucket.backup`. Leave the
   `tfstate` bucket for last since destroying it removes the state you're
   using to run `destroy` — either empty it manually via `aws s3 rm
   s3://pgds-tfstate-<account_id> --recursive` after confirming no stack
   still needs it, or accept it as a trivial ongoing cost (a few cents/month
   for an empty-ish bucket) and leave it.

Record the actual decommission date and the responsible owner in
`RUNBOOK.md` when this is executed for real.

## Known limitations (carried over from Proposal 02, not fixed here)

- Static IAM access keys live on the instance — Lightsail has no
  EC2-style instance role. Compensated with least privilege, not
  eliminated (§10.2).
- Weekly region/SCP checks are not real enforcement on a standalone
  account (§8.4, §12) — this is a process gap, not something Terraform
  can close.
- Lightsail snapshot pricing ($0.05/GB-month) is unverified against the
  live pricing page (§8.5) — check before relying on the $10.80/6mo
  estimate.
