# Terraform — pgds infrastructure

## BLOCKER — the account cannot create the specified instance size

**Status as of 2026-08-31, account `334156771769`:** `bootstrap` is fully applied and
`main` is applied **except `aws_lightsail_instance.app`**. Everything else exists:
both S3 buckets, both IAM users with their access keys, both budgets, and the static
IP. 9 of 12 `main` resources are live.

`CreateInstances` fails for the specified bundle:

```
InvalidInputException: Sorry, your account can not create an instance using this
Lightsail plan size. Please try a smaller plan size or contact Customer Support
if you need to use a larger plan.
```

Probed empirically on a clean account with **zero** existing instances:

| Bundle | RAM | Result |
|---|---|---|
| `nano_3_0` | 0.5 GB | created successfully |
| `micro_3_0` | 1 GB | created successfully |
| `small_3_0` | 2 GB | **denied** |
| `small_ipv6_3_0` | 2 GB | **denied** |
| `medium_3_0` | 4 GB | **denied** |

So the account's plan-size ceiling is `micro_3_0`. The probe instances were deleted;
no charges were left running.

**Why Proposal 02 §8.2 did not catch this.** That section verified availability with
`lightsail get-bundles`, which reports `isActive: true` for every bundle in the table
above — including the three that are denied. `get-bundles` describes the regional
**catalog**, not **account eligibility**. The §8.2 check was necessary but not
sufficient, and no API exposes the per-account cap; it surfaces only on
`CreateInstances`.

**Why the default was not simply lowered to `micro_3_0`.** Proposal 02 §2 excludes
1 GB explicitly — *"cannot run WordPress + MariaDB + Redis. Excluded for technical
reasons, not price"* — and §4.1's RAM budget already consumes ~1.17 GB before serving
a single request. Running on 1 GB would replace a visible blocker with constant
swapping, which §4.1 calls out as "the configuration is wrong". `bundle_id` therefore
still defaults to `small_3_0`.

**To unblock:** request a Lightsail plan-size increase through AWS Support, then
re-run `terraform apply` in `main/` with no changes. Note this account is on Basic
support (`describe-severity-levels` returns `SubscriptionRequiredException`), so the
request must go through the Support Center console rather than the API.

Until then the origin does not exist, so the §13 go/no-go gate items that need a live
server (cache `X-Cache` behaviour, restore/RTO test, origin-not-reachable-by-IP) cannot
be executed.

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
reports **8 resources to add** (2 buckets × 4 sub-resources: bucket,
versioning, encryption config, public-access block), 0 to change, 0 to
destroy.

## Stack 2 — `main/`

The actual runtime infrastructure. **S3 backend, `use_lockfile = true`** —
no DynamoDB table (§9.1).

| File | Resources |
|---|---|
| `lightsail.tf` | `aws_lightsail_instance`, `aws_lightsail_static_ip` (+ attachment), `aws_lightsail_instance_public_ports` |
| `iam.tf` | Two IAM users (`pgds-backup`, `pgds-ses`) + policies + access keys |
| `ses.tf` | SES domain identity + DKIM, gated on `var.domain_name != ""` |
| `budgets.tf` | Two `aws_budgets_budget` resources ($50, $85 actual-spend alerts) |
| `user_data.sh` | LEMP bootstrap script run on first boot |

### Instance and firewall

- `bundle_id = "small_3_0"` (2 vCPU / 2GB / 60GB SSD / 3TB — $12/mo, §2, §8.2),
  `blueprint_id = "ubuntu_24_04"` (not the WordPress blueprint, so Nginx
  stays hand-configured, §4).
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

### Budgets — do NOT touch the existing zero-spend budget

`main/budgets.tf` creates two **new** budgets (`pgds-monthly-50`,
`pgds-monthly-85`) at 100% of $50 / $85 actual spend, per §8.4. It
deliberately does not reference, import, or delete the pre-existing
`My Zero-Spend Budgettt` ($1.00 limit, currently in ALARM) — that resource
was created out of band and Terraform must never adopt resources it didn't
create.

That budget **will alert continuously** once Lightsail starts billing
$12/month, which leads to alert fatigue and the real $50/$85 guardrails
getting ignored (§8.4, §12). Clean it up manually, outside Terraform,
before or right after the first `main` apply:

```bash
# Option A — delete it outright (recommended: it's redundant once the
# $50/$85 budgets exist)
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
| `budget_notification_emails` | yes, no default | list of emails for the $50/$85 alerts |
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
