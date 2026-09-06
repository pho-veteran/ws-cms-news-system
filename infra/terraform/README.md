# Terraform — pgds infrastructure

## Current state: running on the EC2 fallback, not Lightsail

**Account `334156771769`, 2026-08-31.** Both stacks are fully applied. The origin is
live at Elastic IP **3.1.122.66** (`i-047f1e4d31db00df6`, `t4g.small`, 2 vCPU / 2 GB,
arm64) — **not** on Lightsail, because this account cannot create a Lightsail bundle
large enough.

`compute_backend = "ec2"` selects the fallback. `"lightsail"` remains the default and
the preferred target; switch back only after AWS Support lifts the cap.

> **Application data durability — 2026-09-06.** Production intentionally has **no application
> backup or recovery point**. The running EC2 origin and attached root volume are the only
> application copy. Loss or corruption is unrecoverable and requires rebuilding the system and
> reseeding its content. Terraform remote state is infrastructure metadata only; it cannot
> recover the application.

### Why Lightsail could not be used

`CreateInstances` refuses every bundle above `micro_3_0`:

```text
InvalidInputException: Sorry, your account can not create an instance using this
Lightsail plan size. Please try a smaller plan size or contact Customer Support
if you need to use a larger plan.
```

The `small_3_0` 2 GB bundle is therefore unavailable. EC2 `t4g.small` is the minimum
viable fallback but cannot meet the original $100 / six-month cap because its instance,
IPv4, disk, and egress bill separately.

| Component | Monthly | 6 months |
|---|---:|---:|
| `t4g.small` — cheapest 2 GB Graviton | $15.48 | **$92.86** |
| Public IPv4 — mandatory for a reachable origin | $3.65 | $21.90 |
| gp3 40 GB — the smallest disk the media allowance can fit on | $3.84 | $23.04 |
| **Absolute floor, at zero egress** | | **$137.80** |

The original budget is unreachable with 2 GB RAM on this account. The options are to raise
the Lightsail cap via Support, accept the EC2 cost against available credits, or change the
requirement.

Savings evaluated and rejected:

- **Shrink the volume 60 → 40 GB.** EBS volumes cannot shrink. This requires a new volume,
  data migration, and a swap, with real downtime and configuration work.
- **Use a 1 GB Lightsail bundle.** Rejected: the measured runtime budget requires 2 GB.

### To get back to Lightsail

1. Request a Lightsail plan-size increase through the AWS Support Center.
2. Set `compute_backend = "lightsail"` in `terraform.tfvars`.
3. Run `terraform apply`. This destroys the EC2 instance and Elastic IP and creates the
   Lightsail instance and static IP. As of 2026-09-06, there is no application backup or
   recovery point: the running EC2 origin and attached root volume are the only application
   copy, and loss or corruption requires rebuilding and reseeding. The public IP changes, so
   repoint Cloudflare's A record.

---

Two independent Terraform roots remain. The bootstrap root owns the Terraform remote-state
bucket, keeping the state that manages `main` separate from runtime resources.

```text
infra/terraform/
├── bootstrap/   apply once, local backend, owns the remote-state bucket
└── main/        the actual infrastructure, S3 backend (`use_lockfile`)
```

## Terraform version requirement

`main/versions.tf` uses the S3 backend's native `use_lockfile = true`.
**Terraform >= 1.10.0 is required.** The former v1.9.8 binary rejects `use_lockfile` as an
unknown argument. Both stacks were validated with Terraform v1.10.5.

## Stack 1 — `bootstrap/`

Creates the Terraform remote-state bucket that everything else depends on:

| Resource | Purpose |
|---|---|
| `aws_s3_bucket.tfstate` (`pgds-tfstate-<account_id>`) | Terraform remote state for the `main` stack |

The bucket has versioning ON, SSE-S3 (AES256) encryption, all public access blocked, and
`prevent_destroy = true`. Its name is suffixed with the AWS account ID via
`data.aws_caller_identity`, not hardcoded.

It uses a **local backend** (its own `terraform.tfstate` file next to the config, not
committed). It must: it creates the remote backend, so it cannot depend on that backend
existing yet.

Variables: only `aws_region` (default `ap-southeast-1`).

## Stack 2 — `main/`

The runtime infrastructure. **S3 backend, `use_lockfile = true`** — no DynamoDB table.

| File | Resources |
|---|---|
| `lightsail.tf` | Lightsail instance, static IP, and public ports — gated on `compute_backend == "lightsail"` |
| `ec2.tf` | EC2 instance, EIP, security group, and rules — the active fallback origin, gated on `compute_backend == "ec2"` |
| `iam.tf` | SES sender user (`pgds-ses`) and its least-privilege policy |
| `ses.tf` | SES domain identity + DKIM, gated on `var.domain_name != ""` |
| `budgets.tf` | Four budgets: three lifetime and one monthly run-rate anomaly budget |
| `alarms.tf` | SNS topic, email subscription, and three CloudWatch alarms, gated on EC2 |
| `github-oidc.tf` | GitHub OIDC provider and narrowly scoped deploy role, gated on EC2 |
| `user_data.sh` | LEMP bootstrap script run on first boot; shared by both backends |

Exactly one compute backend exists at a time — `count` / `for_each` on
`var.compute_backend` means the unused one has zero resources rather than being commented out.

### Instance and firewall

- **Lightsail path (preferred, currently blocked):** `bundle_id = "small_3_0"`
  (2 vCPU / 2 GB / 60 GB SSD / 3 TB).
- **EC2 path (active):** `t4g.small` on Canonical's Ubuntu 24.04 arm64 AMI resolved through
  SSM, 60 GB gp3, `delete_on_termination = false`, and IMDSv2 required.
- `user_data.sh` installs nginx, PHP 8.3-FPM (`pm=ondemand`, `pm.max_children=6`), MariaDB
  10.11 (`innodb_buffer_pool_size=256M`), Redis (`maxmemory 160mb`, `allkeys-lru`), a 2 GB
  swapfile, fail2ban, and SSH hardening. It does not install WordPress itself.
- Public ports 80/443 accept Cloudflare's published IPv4/IPv6 ranges. SSH accepts only
  `ssh_admin_cidrs`, which has no default and rejects `0.0.0.0/0`.

### IAM — SES sender

`pgds-ses` has only `ses:SendEmail` and `ses:SendRawEmail` permissions. Its static access
credentials are stored on the origin under `/root`, mode 600; secret-key outputs are marked
`sensitive = true` and must never be placed in shell history or CI logs.

### SES — placeholder domain

`var.domain_name` defaults to `""`. SES identity and DKIM resources use
`count = var.domain_name == "" ? 0 : 1`, so applying with the placeholder creates no
unverifiable SES identity. Once a real domain is chosen, set `domain_name`, apply, then add
the three DKIM CNAME records from `terraform output ses_dkim_tokens` to Cloudflare DNS.

### Budgets

`main/budgets.tf` creates four COST/USD budgets. They notify
`budget_notification_emails`:

| Budget | Limit | Period | Purpose |
|---|---:|---|---|
| `pgds-lifetime-50` | $50 | ANNUALLY | Mid-project signal |
| `pgds-lifetime-160-projection-exceeded` | $160 | ANNUALLY | Six-month EC2 projection exceeded |
| `pgds-lifetime-190-credits-nearly-gone` | $190 | ANNUALLY | Credits nearly consumed |
| `pgds-monthly-run-rate` | $40 | MONTHLY | Anomaly detection |

## Apply order

1. **Bootstrap first.**

   ```bash
   cd infra/terraform/bootstrap
   ~/.local/bin/terraform init
   ~/.local/bin/terraform apply
   terraform output tfstate_bucket_name   # e.g. pgds-tfstate-334156771769
   ```

2. **Wire the remote-state bucket name into `main`'s backend**, then init and apply:

   ```bash
   cd infra/terraform/main
   # Edit versions.tf: replace REPLACE_WITH_TFSTATE_BUCKET with the output above.
   ~/.local/bin/terraform init
   ~/.local/bin/terraform apply \
     -var="key_pair_name=<existing administrator SSH key-pair name>" \
     -var='pgds_deploy_public_key=<dedicated-github-actions-ed25519-public-key>' \
     -var='ssh_admin_cidrs=["<your-ip>/32"]' \
     -var='budget_notification_emails=["you@example.com"]'
   # domain_name remains "" until a real domain exists
   ```

Prefer a gitignored `terraform.tfvars` over repeated `-var` flags. Never commit the sensitive
`pgds_deploy_public_key` value.

### Release-boundary lifecycle

`key_pair_name` is an administrator SSH key-pair name; it is distinct from the required,
sensitive `pgds_deploy_public_key`, which creates the fixed `pgds-deploy` CI account at first
boot. Terraform supplies the helper, the dedicated public key, deploy-owned incoming area,
root-owned release history, and the one fixed sudo command. It does not deploy WordPress core,
database data, uploads, content, options, terms, unrelated plugins, or execute setup, seed,
import, or WP-CLI.

EC2 deliberately ignores `user_data` changes for the running host. Existing hosts are migrated
by the GitHub Actions workflow through the legacy deploy account; replacement hosts receive the
restricted boundary through `user_data` at first boot. Release work always uses `pgds-deploy`.

## Required variables (`main`)

| Variable | Required? | Notes |
|---|---|---|
| `key_pair_name` | yes, no default | administrator SSH key-pair name for instance access; not the GitHub Actions deploy credential |
| `pgds_deploy_public_key` | yes, sensitive, no default | dedicated GitHub Actions Ed25519 public key for fixed `pgds-deploy` |
| `ssh_admin_cidrs` | yes, no default | list; rejects `0.0.0.0/0` |
| `budget_notification_emails` | yes, no default | email list for the budgets |
| `domain_name` | no, default `""` | leave empty until a real domain is chosen |
| `aws_region`, `availability_zone`, `instance_name`, `bundle_id`, `blueprint_id`, `cloudflare_ipv4_cidrs`, `cloudflare_ipv6_cidrs` | no | sensible defaults; override only if needed |

## Exit-plan teardown order

As of 2026-09-06, production intentionally has **no application backup or recovery point**.
The running EC2 origin and attached root volume are the only application copy. Loss or
corruption is unrecoverable and requires rebuilding the system and reseeding its content.
Decommissioning does not create an application export.

1. `cd infra/terraform/main && terraform destroy` — tears down the instance, static IP,
   firewall rules, SES identity, and budgets. Cloudflare DNS/records are outside this stack
   unless the optional Cloudflare provider was added; remove those records manually if so.
2. Leave the Terraform remote-state bucket until no stack needs it. If it must be removed,
   first remove its `prevent_destroy` protection from `bootstrap/main.tf` and apply that
   change, then destroy the bucket from `infra/terraform/bootstrap`. Alternatively, retain
   it at its small ongoing cost after confirming no stack still needs the state.

Record the actual decommission date and responsible owner in `RUNBOOK.md` when this is
executed for real.

## Known limitations

- SES sender credentials reside on the instance under `/root`, mode 600; this is a known
  limitation of the current deployment model.
- Weekly region/SCP checks are not real enforcement on a standalone account. This is a process
  gap Terraform cannot close.
