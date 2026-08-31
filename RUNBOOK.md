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
host key; it does not allowlist GitHub Actions IP ranges.

The post-deployment order is mandatory (proposal §12.1): **origin first, edge second**.

1. rsync the runtime theme → 2. reload PHP-FPM (resets opcache) → 3. flush FastCGI → 4. purge Cloudflare only when theme assets changed.

Purging Cloudflare before the origin can re-cache stale origin content at the edge.
Overlapping deployments are serialized by the GitHub Actions concurrency group.

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
hooks `save_post`.

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

## 5. Scaling the bundle under high traffic (proposal §8.2)

Lightsail does **not** resize in place:
snapshot → create a larger-bundle instance → move the static IP → verify → delete the old
instance.
For holidays: bump 2 GB → 4 GB (~$0.40/day), then scale back down. Worth doing temporarily
before importing 2,000 posts as well.

## 6. YouTube API quota

- `videos.list` costs 1 unit per call, batching up to 50 IDs per call. Default quota is
  10,000 units/day.
- The `pgds_fetch_yt_meta` cron runs once daily to fetch `duration` and `title`. Fallback:
  use the stored meta; never overwrite it with an empty value.
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
| `pgds-snapshot.sh` | **manual / CI, not cron** | Daily AMI + 4-rolling retention (§6.1) |

Thresholds: memory ≥85% (of `MemAvailable`, not `used` — §4.1 budgets ~850 MB *for*
the page cache), disk ≥80%, swap ≥256 MB. §4.1 is explicit that regular swap use means
the configuration is wrong, hence the low swap bar. Alerts have a 3-hour cooldown so a
sustained problem cannot mail every 10 minutes and get itself muted.

**Why the snapshot job is not in cron on the origin:** it needs
`ec2:DeregisterImage` and `ec2:DeleteSnapshot`, and a credential that can delete
backups must not sit on an internet-facing box. Run it from the admin workstation:

```bash
PGDS_INSTANCE_ID=i-047f1e4d31db00df6 ./infra/scripts/pgds-snapshot.sh
```

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

`pgds-lifetime-50` and `pgds-lifetime-85` track *cumulative* spend toward the $100 cap
(ANNUALLY, so they do not reset inside the six-month life). `pgds-monthly-run-rate`
($45, forecast at 100% + actual at 80%) catches a runaway — almost always egress.
**Lower it to ~20 after moving back to Lightsail**, or it stops meaning anything.
Details: `infra/terraform/README.md`.

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
