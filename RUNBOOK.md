# RUNBOOK — Phật giáo và Đời sống

Operating the WordPress system. Supplements `docs/initial_entries/PROPOSAL_01`
and `PROPOSAL_02`.

> **The origin runs on EC2, not Lightsail.** This account cannot create a Lightsail
> bundle above `micro_3_0` (1 GB), and §2 rules out 1 GB on technical grounds. The
> origin is `i-047f1e4d31db00df6` (`t4g.small`, 2 vCPU / 2 GB, arm64) at Elastic IP
> `3.1.122.66`. Set `compute_backend = "lightsail"` and re-apply once AWS Support
> lifts the cap. Full rationale and the cost delta: `infra/terraform/README.md`.
>
> **Measured RTO: 21.7 minutes** (drill run 2026-08-31, details in §4).

## 1. Deploy the theme

GitHub Actions deploys pushes to `main` through `.github/workflows/deploy.yml`. The workflow
runs PHP and JavaScript linting, fetches self-hosted fonts, builds and verifies the hashed
assets, then deploys only if every gate succeeds. It deploys only the runtime theme files;
WordPress core, the database, and plugins are never deployed by this pipeline.

Configure every repository secret documented in `.github/workflows/README.md` before the
first deployment. The workflow uses a dedicated key-only deployment account and a pinned SSH
host key; it does not allowlist GitHub Actions IP ranges. Short-lived AWS credentials open port
22 only to the active runner's IPv4 `/32`, then the workflow revokes the exact security-group
rule under `if: always()`. A later serialized deploy removes a leaked `github-actions deploy`
rule first if a job was force-killed before cleanup could run.

The post-deployment order is mandatory (proposal §12.1): **origin first, edge second**.

1. rsync the runtime theme → 2. reload PHP-FPM (resets opcache) → 3. flush FastCGI → 4. purge Cloudflare only when theme assets changed.

Purging Cloudflare before the origin can re-cache stale origin content at the edge.
Overlapping deployments are serialized by the GitHub Actions concurrency group. After a run,
verify that no temporary rule remains:

```bash
aws ec2 describe-security-group-rules --region ap-southeast-1 \
  --filters Name=group-id,Values=<production-security-group-id> \
  --query "SecurityGroupRules[?starts_with(Description || '', 'github-actions deploy ')]"
```

For a break-glass manual deployment, first build the runtime assets and fonts from the theme
directory, then use the same order and exclusions as `.github/workflows/deploy.yml`. Do not
rsync `node_modules`, `src`, `tools`, build configuration, package files, ESLint configuration,
or the theme README.

## 2. Rollback

```bash
git revert <sha>
git push origin main       # triggers the same gated production deployment pipeline
```

The blast radius remains one theme directory. If GitHub Actions or the production server is
unavailable, use the break-glass manual deployment procedure in §1 to restore the prior
runtime theme version, then follow the same origin-first cache purge order.

## 3. Manual cache purge

```bash
sudo find /var/cache/nginx/fcgi -type f -delete      # flush the entire HTML cache
# Verify: curl -sI https://site/ | grep X-Cache      (HIT on the second request)
```

Content edited in the admin already purges itself: the `pgds-cache-flush.php` mu-plugin
hooks `transition_post_status` (**not** `save_post` — proposal §5.6 specified `save_post`,
but that fires for autosaves and revisions and misses a trash/untrash, so the implementation
uses the status transition and flushes when either the old or new status is `publish`). It
also hooks `deleted_post`, `edited_term`, `wp_update_nav_menu`, `switch_theme`, and
`customize_save_after`. Drafting does not purge; publishing, editing a published post,
unpublishing, and trashing all do.

Scope: this purges the **origin** FastCGI cache only and makes no Cloudflare API call. That
is correct, because the edge never holds HTML — but it also means a changed **static asset**
is not purged from the edge by this path. Only a deploy purges Cloudflare.

## 4. Restore from a snapshot (SPOF — target RTO 30–60 min)

Restore uses a **clean-room instance** and never overwrites production (§6.3).

On EC2 (current backend):

```bash
# 1. Snapshot = an AMI of the running origin. --no-reboot avoids downtime.
aws ec2 create-image --region ap-southeast-1 --instance-id <origin-id> \
  --name "pgds-restore-$(date +%s)" --no-reboot

# 2. Wait for State=available, then launch from it into the same SG and subnet.
aws ec2 run-instances --region ap-southeast-1 --image-id <ami> \
  --instance-type t4g.small --key-name pgds-deploy \
  --security-group-ids <sg> --subnet-id <subnet> \
  --metadata-options 'HttpTokens=required'

# 3. Verify BEFORE touching production: services up, post count, media, front page.
# 4. Cut over by reassociating the Elastic IP, then terminate the old instance.
```

On Lightsail (once the cap is lifted): console → Snapshots → create instance from the
most recent snapshot → detach the static IP from the old instance and attach it to the
new one → verify → delete the old instance.

Either way: `nginx -t && systemctl reload nginx`, then verify `X-Cache`, the front
page, one post, and one category.

**Measured RTO: 21.7 minutes** — drill run 2026-08-31 against the live origin:

| Phase | Time |
|---|---|
| AMI (snapshot) creation | 11.2 min |
| Launch → serving HTTP 200 | 10.5 min |
| **Total from a cold start** | **21.7 min** |

Verified on the restored host: nginx, php-fpm, MariaDB and Redis all active; 25 posts
and 17 categories intact; `pgds` theme active; front page rendering. Well inside the
30–60 min target. With a nightly snapshot already on hand only the second phase
applies, so a real incident is ~10 min.

The drill's temporary security-group rules were revoked, the test instance
terminated, and the AMI and its snapshot deleted — re-check for orphans after any
future drill, since a forgotten snapshot bills at $0.05/GB-month.

## 5. Scaling under high traffic (proposal §8.2)

**On EC2 (current backend)** a resize *is* in place, unlike Lightsail — this is one of the
few ways the forced move to EC2 made operations easier:

```bash
aws ec2 stop-instances  --region ap-southeast-1 --instance-ids i-047f1e4d31db00df6
aws ec2 modify-instance-attribute --region ap-southeast-1 \
  --instance-id i-047f1e4d31db00df6 --instance-type t4g.medium
aws ec2 start-instances --region ap-southeast-1 --instance-ids i-047f1e4d31db00df6
```

The Elastic IP survives a stop/start, so no DNS change is needed. Downtime is the boot
time, ~1–2 minutes. Take a snapshot first anyway (§4). Set `ec2_instance_type` in
`terraform.tfvars` to match afterwards or the next `plan` will propose reverting it —
`variables.tf` only permits `t4g.small` and `t4g.medium`.

After resizing **re-tune for the new RAM** (§4.1 of the proposal derives every worker count
from the RAM budget): on 4 GB, `pm.max_children` can go to ~12 and
`innodb_buffer_pool_size` to ~512M. Leaving 2 GB tuning on a 4 GB box wastes the upgrade.

Cost: `t4g.medium` is $0.0424/h vs $0.0212/h, so ~+$15/month while it is up. Scale back down
after the traffic peak.

**On Lightsail (if the bundle cap is ever lifted)** resize is a migration, not a toggle:
snapshot → create a larger-bundle instance → move the static IP → verify → delete the old
instance. There is brief downtime at the IP move.

## 6. YouTube API quota

- `videos.list` costs 1 unit per call, batching up to 50 IDs per call. Default quota is
  10,000 units/day.
- The `pgds_fetch_yt_meta` WP-Cron event runs once daily at **03:40 site-local time**
  (`Asia/Ho_Chi_Minh`) to fetch `duration` and `title`. Registered by
  `wp-content/themes/pgds/inc/cron.php`; it calls the same code as `wp pgds yt-sync`, so
  there is one implementation of the §6.4 rules. Fallback: use the stored meta; never
  overwrite it with an empty value.
- Because `DISABLE_WP_CRON` is set (see `infra/wp-config-hardening.sample.php`), the event
  only fires when system cron runs `wp cron event run --due-now`. **If that crontab entry
  is missing, this job silently never runs.** Verify with:

  ```bash
  wp cron event list --fields=hook,next_run_gmt,recurrence | grep pgds_fetch_yt_meta
  # force one run:
  wp cron event run pgds_fetch_yt_meta
  ```

- The job requires `PGDS_YT_API_KEY` in `wp-config.php`. Without it the event still fires
  but returns immediately rather than doing partial work — check the log for
  `PGDS_YT_API_KEY is not defined` if durations stop updating.
- Private or removed videos: set the `_pgds_video_unavailable=1` meta to hide the facade and
  drop the schema.

## 7. Monitoring and scheduled maintenance

**Not CloudWatch.** §7 rejects the CloudWatch agent: RAM and disk are not built-in
instance metrics, so they need custom metrics, which §7 costs at ~$24 over six months —
28% of the budget, more than all backups combined — to watch two numbers. Free
platform alarms still cover CPU utilisation, burst capacity, and the status check.

Everything else runs from `infra/scripts/`, installed at `/usr/local/sbin/` on the
origin. Credentials live in `/root/.pgds-backup.env`, root-owned mode 600 (§10.2).

| Script | Schedule | What it does |
|---|---|---|
| `pgds-db-backup.sh` | `17 3,15 * * *` UTC (cron) | `mysqldump --single-transaction` → gzip → S3 `db-dumps/`, plus 2 local copies (§6.1) |
| `pgds-health-alert.sh` | `*/10 * * * *` (cron) | RAM/disk/swap thresholds + service liveness → syslog, and SES email when configured (§7) |
| AWS DLM policy | `19:40` UTC daily (AWS-side) | Daily AMI + 4-rolling retention (§6.1) — `infra/terraform/main/dlm.tf` |
| `pgds-snapshot.sh` | **manual, on demand** | Ad-hoc AMI before a resize or migration |

Thresholds: memory ≥85% (of `MemAvailable`, not `used` — §4.1 budgets ~850 MB *for*
the page cache), disk ≥80%, swap ≥256 MB. §4.1 is explicit that regular swap use means
the configuration is wrong, hence the low swap bar. Alerts have a 3-hour cooldown so a
sustained problem cannot mail every 10 minutes and get itself muted.

**Why the daily snapshot is DLM and not cron on the origin.** Pruning needs
`ec2:DeregisterImage` and `ec2:DeleteSnapshot`, and a credential that can delete backups
must not sit on an internet-facing box — that is exactly the credential an attacker wants
after a WordPress compromise. DLM resolves this rather than trading it away: AWS runs the
schedule and the pruning server-side under the `pgds-dlm-ami-management` role, so the delete
capability lives in IAM and never on the instance. The origin gained no new credential.

Applied 2026-09-02, policy `policy-04ea69769e8b12ff7`. Before that date **nothing created a
snapshot on a schedule** — `user_data.sh` never installed the cron entry that
`pgds-snapshot.sh`'s header suggests, so the account held exactly one AMI, from the
2026-08-31 restore drill, while §6.3 measured RTO against "the latest snapshot".

Verify it is running — a schedule that silently stopped looks identical to one that never
fired:

```bash
aws dlm get-lifecycle-policy --policy-id policy-04ea69769e8b12ff7 --region ap-southeast-1 \
  --query 'Policy.{state:State,status:StatusMessage}'
aws ec2 describe-images --owners self --region ap-southeast-1 \
  --filters Name=tag:CreatedBy,Values=dlm \
  --query 'sort_by(Images,&CreationDate)[].{n:Name,d:CreationDate}' --output table
```

Expect up to 4 rows, the newest under ~25h old. DLM starts within an hour of 19:40 UTC
(02:40 ICT), chosen clear of both db-backup runs so a dump and an AMI never contend for the
same 2 GB and 2 vCPUs.

`pgds-snapshot.sh` remains the **manual** path for an ad-hoc restore point — before a resize
or migration. Run it from the admin workstation, not the origin:

```bash
PGDS_INSTANCE_ID=i-047f1e4d31db00df6 ./infra/scripts/pgds-snapshot.sh
```

It tags its images `pgds-auto-*` while DLM tags its own `CreatedBy=dlm`, so the two
retention pools are independent and neither prunes the other's images.

It prunes the AMI **and** its backing EBS snapshot together. Deregistering an AMI alone
orphans the snapshot, which keeps billing at $0.05/GB-month invisibly — check for
orphans after any manual snapshot work:

```bash
aws ec2 describe-snapshots --owner-ids self --region ap-southeast-1 \
  --query 'Snapshots[].{id:SnapshotId,desc:Description}' --output table
```

### Verifying a backup is actually restorable

A backup that has never been read is a guess. Spot-check periodically:

```bash
aws s3 cp s3://pgds-backup-<account_id>/db-dumps/<newest>.sql.gz /tmp/v.sql.gz
gzip -t /tmp/v.sql.gz                          # integrity
zcat /tmp/v.sql.gz | grep -c 'CREATE TABLE'    # expect 12+
zcat /tmp/v.sql.gz | grep -o '_pgds_[a-z_]*' | sort -u   # theme meta keys present
```

Last verified 2026-08-31: 12 tables, `wp_posts` present, all eight `_pgds_*` meta keys
intact.

### Budget alarms

Four budgets, all emailing `budget_notification_emails`. The three lifetime budgets are
ANNUALLY so they do not reset inside the six-month life, and track *cumulative* spend:

| Budget | Limit | Fires on |
|---|---|---|
| `pgds-lifetime-50` | $50 | ACTUAL 100% |
| `pgds-lifetime-160-projection-exceeded` | $160 | ACTUAL 100% |
| `pgds-lifetime-190-credits-nearly-gone` | $190 | ACTUAL 100% + FORECASTED 100% |
| `pgds-monthly-run-rate` | $40 | FORECASTED 100% + ACTUAL 80% |

The $190 budget is tied to the $200 credit balance — it is the one that warns before the
credits run out and real cash starts. The $40 monthly budget sits ~$15 above the measured
$24.89 run rate to catch a runaway, which for this architecture is almost always egress at
$0.12/GB beyond the first 100 GB. **Lower it to ~20 after moving back to Lightsail**, or it
stops meaning anything. Authoritative values: `infra/terraform/main/budgets.tf`.

## 7b. Cloudflare edge + cutover (§5.3, §11)

> **Cutover is DONE.** `vihn.id.vn` is live behind the Cloudflare proxy — verified
> 2026-09-02: the site returns HTTP 200 with `server: cloudflare`, HTML shows
> `X-Cache: MISS` then `HIT` on a second request, and a hashed asset returns
> `cf-cache-status: HIT` with `Cache-Control: public, max-age=31536000, immutable`.
> `wp-login.php` returns `X-Cache: BYPASS`, so Cache Rule 1 and the nginx `$skip`
> conditions are both working. The steps below are kept as the procedure to repeat if
> the zone is ever rebuilt; each one is marked with its current state.

The origin firewall admits 80/443 from Cloudflare ranges ONLY (§10.2), which means **the
site is unreachable from the internet except through the proxy** — verified: a direct
`curl http://3.1.122.66/` from outside those ranges times out. That is deliberate, and it
is what makes the order below matter.

`infra/scripts/pgds-cloudflare-setup.sh` turns §5.3 into one idempotent, self-verifying
command. It sets SSL to Full (strict), Always Use HTTPS on, Brotli on, Auto Minify off,
and creates exactly the **two** Cache Rules §5.3 budgets (2 of the Free plan's 10):

1. bypass `/wp-admin/`, `/wp-login.php`, `/wp-json/`, `/wp-cron.php`, `/xmlrpc.php`
2. cache static assets by extension, edge TTL 31 days

HTML is absent from both, on purpose (§5.2/§5.6): the edge caches assets only, Nginx
FastCGI owns the page cache and is purged on a publish transition (§3). That is what
removes the edge stale window and means no cookie-based bypass rule is needed.

```bash
# Dry run first — resolves the zone and reports its status without changing anything.
CF_API_TOKEN=<scoped token> CF_DOMAIN=<domain> ./infra/scripts/pgds-cloudflare-setup.sh --dry-run
CF_API_TOKEN=<scoped token> CF_DOMAIN=<domain> ./infra/scripts/pgds-cloudflare-setup.sh
```

Use a **scoped** token (Zone:Read + Zone Settings:Edit + Cache Rules:Edit for this zone),
never the Global API Key, which cannot be scoped and can do anything to every zone.

Note the script REPLACES the cache ruleset when it runs, so a rule added by hand in the
dashboard is removed. Intentional: the cache design depends on knowing exactly what is
there.

### Cutover order

1. **DONE.** Add the domain to Cloudflare, delegate its nameservers, wait for zone
   `status: active` (§11 — propagation can take hours; do it before Day 1).
2. **DONE, but not as specified.** The origin serves 443 with a **Let's Encrypt**
   certificate (`CN=YR2`, expires 2026-11-30) obtained via DNS-01 and auto-renewed by the
   `certbot` cron job — not the Cloudflare **Origin CA** certificate §5.3 specified. Either
   satisfies Full (strict). Let's Encrypt was kept because certbot already automates
   renewal, where an Origin CA cert is a 15-year key to rotate by hand. Recorded so nobody
   "corrects" it back and breaks renewal.
3. **DONE.** Run the script above.
4. **NOT DONE — SES DKIM is not Terraform-managed.** `domain_name` is still `""` in
   `terraform.tfvars`, so `aws_ses_domain_identity.app` and `aws_ses_domain_dkim.app` both
   have `count = 0` and `terraform output ses_dkim_tokens` returns an empty list. The domain
   identity `vihn.id.vn` nevertheless exists and shows `VerificationStatus: SUCCESS`,
   because it was created outside Terraform. Setting `domain_name = "vihn.id.vn"` and
   re-applying would bring it under management — do that in a maintenance window and expect
   Terraform to want to create an identity that already exists (import it rather than let
   the apply fail). The path itself is proven: it was exercised end to end against the
   IANA-reserved `example.com`, produced 3 tokens, and destroyed cleanly.
5. **DENIED — action required.** SES production access was requested and **refused**:

   ```
   ProductionAccessEnabled: false
   Details.ReviewDetails.Status: DENIED      # checked 2026-09-02
   Max24HourSend: 200
   ```

   This is a state change from the "PENDING" this runbook previously recorded, and it does
   not resolve itself — AWS does not re-review a denied request on its own. Someone must
   open a new request (or a Support case) with more detail about the sending use case,
   bounce handling, and list provenance. Re-check with:

   ```bash
   aws sesv2 get-account --region ap-southeast-1 \
     --query '{prod:ProductionAccessEnabled,review:Details.ReviewDetails.Status}'
   ```

   **Consequence while denied:** the account stays in the sandbox — 200 emails/24h, 1 msg/s,
   and delivery only to *verified* recipients. `pgds-health-alert.sh` therefore targets
   `success@simulator.amazonses.com`, which the sandbox always accepts and which proves the
   whole path end to end. Alerts do **not** reach the operator's mailbox until either
   production access is granted or that mailbox is verified as an SES identity — and
   verifying the single operator address is the cheap fix that makes alerting real today:

   ```bash
   aws sesv2 create-email-identity --region ap-southeast-1 --email-identity <operator@example.com>
   # then click the verification link, and point PGDS_ALERT_TO in /root/.pgds-ses.env at it
   ```

   Until one of those happens, treat health-alert email as unproven for real incidents: the
   send path works, but nothing arrives where a human is looking.
6. Run the §13 go/no-go gate.
7. **DONE.** Point the apex and `www` A records at the origin and enable the orange cloud.
   This is the cutover; everything above is reversible without visitor impact.
8. **DONE.** Confirm the edge is caching assets:
   ```bash
   curl -sI https://vihn.id.vn/wp-content/themes/pgds/assets/dist/main.<hash>.css | grep cf-cache-status
   ```
   Returns `cf-cache-status: HIT`.

### 7c. Origin verification — one of two barriers is missing

§10.2 specifies **two** independent barriers against direct-origin access:

1. the security group, admitting `:80`/`:443` from Cloudflare ranges only — **deployed**
2. a secret header injected by a Cloudflare Transform Rule and checked by nginx — **not
   deployed**

Only the first exists. The check in `infra/nginx/pgds.conf` is commented out and is absent
from the live config, so the security group is the sole thing standing between the public
internet and the origin. Verified 2026-09-02: `curl http://3.1.122.66/` from outside a
Cloudflare range times out, so the origin is **not** currently exposed — this is a missing
layer of defence in depth, not an open door.

Why it matters anyway: the allowlist is 15 IPv4 + 7 IPv6 ranges shared by **every**
Cloudflare customer. Anyone who can route a request through Cloudflare — their own free zone
pointed at `3.1.122.66` — comes from an allowlisted address. The header is what distinguishes
*our* zone from *any* zone.

**Enabling it is a two-sided change and the order is not optional.** The Transform Rule must
be injecting the header *before* nginx starts requiring it. Reverse the order and every
request, Cloudflare's included, gets a 403 — a total outage that presents as an origin
failure rather than a config change.

```
1. Cloudflare → Rules → Transform Rules → Modify Request Header
     set static   X-Origin-Verify: <generate: openssl rand -hex 32>
2. Confirm it is arriving, before requiring it:
     add_header X-Seen-Verify $http_x_origin_verify always;   # temporary, then remove
     curl -sI https://vihn.id.vn/ | grep -i x-seen-verify
3. Put the secret in an untracked include — NOT in pgds.conf, which is committed:
     /etc/nginx/conf.d/pgds-origin-secret.conf   →   map $http_x_origin_verify $pgds_origin_ok { "<secret>" 1; default 0; }
4. Uncomment the check in pgds.conf, then:  nginx -t && systemctl reload nginx
5. Re-verify through the edge (expect 200) and directly (expect 403 if you can reach it).
```

Rotation: update the Transform Rule first, then the include — during the gap both values
must be accepted, so keep two entries in the map until the change has propagated.

## 8. Exit plan (before decommissioning)

```bash
wp export --dir=/backup/wxr        # export all content as WXR
tar czf /backup/uploads.tgz wp-content/uploads
# Pull both somewhere safe BEFORE tearing down the infrastructure.
```

- Planned decommission date: **\_\_** | Owner: **\_\_**

## 9. Rollback owner

- Name: **\_\_** | Contact: **\_\_** | Rollback trigger: 5xx above 5% for 5 minutes, or a
  blank page.

## 10. Handover documents (§14)

| Deliverable | Where |
|---|---|
| Deploy / rollback / purge / restore / resize / quota / exit plan | this file |
| Local setup, asset build, running the import | `wp-content/themes/pgds/README.md` |
| Local stack, and why schema must NOT be verified there | `infra/local/README.md` |
| One-hour editor training | **`docs/EDITOR_TRAINING.md`** |
| Terraform stacks, backends, plan-size cap | `infra/terraform/README.md` |
| CI/CD gates and required secrets | `.github/workflows/README.md` |

`docs/EDITOR_TRAINING.md` is written in Vietnamese because its readers are the newsroom's
editors — the same audience as the admin field labels. It documents all 8 fields of the
"Thông tin PGDS" meta box, the four featured slots, the video workflow, and the
"edits are visible immediately" cache behaviour. Field names in it are copied from
`inc/meta-fields.php`; if a label changes there, update that document too.

Still outstanding, each needing a person rather than more code — all three are listed at the
end of the training document so the newsroom sees them:

1. **Cloudflare token** — scoped `Zone:Read`, `Zone Settings:Edit`, `Cache Rules:Edit`. No CLI
   to install; `infra/scripts/pgds-cloudflare-setup.sh` uses `curl` only. Run it with
   `--dry-run` first.
2. **Alert email** — **done 2026-09-01.** Both `alarm_notification_email` and
   `budget_notification_emails` are set to the operator's mailbox and applied. The four
   budgets deliver immediately; the SNS subscription sits in `PendingConfirmation` until
   the recipient clicks AWS's confirmation email once. Verify with:

   ```bash
   aws sns list-subscriptions-by-topic --region ap-southeast-1 \
     --topic-arn arn:aws:sns:ap-southeast-1:334156771769:pgds-alarms \
     --query 'Subscriptions[].{p:Protocol,e:Endpoint,arn:SubscriptionArn}'
   ```

   `arn: PendingConfirmation` means the link has not been clicked yet. Note the address
   lives in `infra/terraform/main/terraform.tfvars`, which is **gitignored** — so it is
   not recoverable from the repo. Re-set it after any fresh clone before applying, or the
   variable's empty default silently creates no subscription at all.
3. **Footer legal text** — Appearance → Customize → Thông tin toà soạn (§13 gate; explicitly
   not a technical decision).
