# RUNBOOK — Phật giáo và Đời sống

Operating the WordPress system. Supplements `docs/initial_entries/PROPOSAL_01`
and `PROPOSAL_02`.

> **The origin runs on EC2, not Lightsail.** This account cannot create a Lightsail
> bundle above `micro_3_0` (1 GB), and §2 rules out 1 GB on technical grounds. The
> origin is `i-047f1e4d31db00df6` (`t4g.small`, 2 vCPU / 2 GB, arm64) at Elastic IP
> `3.1.122.66`. Set `compute_backend = "lightsail"` and re-apply once AWS Support
> lifts the cap. Full rationale and the cost delta: `infra/terraform/README.md`.
>
> **No application backup or recovery point.** As of 2026-09-06, the running EC2 origin
> and its attached root volume are the only application copy. Loss or corruption is
> unrecoverable; rebuild the system and reseed its content.

## 1. Deploy the first-party runtime

GitHub Actions deploys pushes to `main` through `.github/workflows/deploy.yml`. The workflow
runs PHP and JavaScript linting, fetches self-hosted fonts, builds and verifies the hashed
assets, then promotes a release only if every gate succeeds. Since 2026-09-05, a release
contains the `pgds` theme runtime (including generated assets),
`pgds-lunar-calendar`, and exactly `pgds-cache-flush.php` plus `pgds-lunar-loader.php`.

Every payload file is listed in a SHA-256 manifest. The fixed unprivileged `pgds-deploy`
account uploads and atomically extracts the archive below
`/var/lib/pgds-deploy/incoming`; it cannot write the WordPress runtime. Its only sudo
command is the fixed root-owned `/usr/local/sbin/pgds-deploy-release <release-id>` helper.
That helper verifies every checksum, checks PHP and asset references, serializes promotion,
promotes only the listed first-party paths, reloads fixed `php8.3-fpm`, and flushes fixed
`/var/cache/nginx/fcgi`. A promotion failure restores the previous runtime. Validated history
is root-owned and the five newest releases are retained.

The deploy workflow must keep its order: rsync → reload php-fpm → flush FastCGI → purge
Cloudflare. Origin freshness before the edge purge prevents the edge from refetching stale
assets. The deploy helper does not modify WordPress core, database data, uploads, content,
options, terms, unrelated plugins, or other mu-plugins.

For break-glass recovery when the pipeline is unavailable, an administrator may restage one of
the retained release payloads through the restricted deploy boundary. Do not use broad root
shell commands or deployment-account commands to restore a release.

## 2. Deploy verification

After a successful deployment, verify the public site and the release-boundary health checks:

```bash
curl -sSI https://vihn.id.vn/ | grep -iE 'x-cache|cf-cache-status'
curl -sSI https://vihn.id.vn/wp-login.php | grep -i x-cache
```

The homepage must respond successfully, the public lunar REST payload must be usable, and the
hashed CSS and JavaScript asset URLs must resolve. A hard runner stop or AWS cleanup failure
can leave the bounded temporary SSH rule in place; the workflow reports it and the stale-rule
sweep removes it on its next run.

## 3. Cache behaviour

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

## 4. Application data durability

**As of 2026-09-06, production intentionally has no application backup or recovery point.**
The running EC2 origin and its attached root volume are the only application copy. Loss or
corruption of that copy is unrecoverable and requires rebuilding the system and reseeding its
content. Do not represent the Elastic IP, Terraform state, release history, or an EC2 volume
as an application recovery mechanism.

## 5. Scaling under high traffic (proposal §8.2)

**On EC2 (current backend)** a resize is in place, unlike Lightsail — this is one of the
few ways the forced move to EC2 made operations easier. Stop the instance, change its type,
start it, then verify memory and services. The Elastic IP remains attached.

Do not resize casually: the memory settings were derived for 2 GB. A larger instance needs a
conscious retune of MariaDB, Redis, PHP-FPM, and the health-alert thresholds. On Lightsail,
use the platform migration process after the account cap is lifted.

## 6. YouTube metadata

The scheduled YouTube metadata job is a WordPress cron event. Verify or force it as `www-data`:

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

**Not CloudWatch.** RAM and disk are not built-in instance metrics, so they would need
custom metrics. Free platform alarms still cover CPU utilisation, burst capacity, and the
status check.

The health-alert script runs from `infra/scripts/` and is installed at
`/usr/local/sbin/` on the origin.

| Script | Schedule | What it does |
|---|---|---|
| `pgds-health-alert.sh` | `*/10 * * * *` (cron) | RAM/disk/swap thresholds + service liveness → syslog, and SES email when configured |
| WordPress cron runner | `*/5 * * * *` (cron) | Runs due WordPress scheduled events |

Thresholds: memory ≥85% (of `MemAvailable`, not `used`), disk ≥80%, swap ≥256 MB. Regular
swap use means the configuration is wrong, hence the low swap bar. Alerts have a 3-hour
cooldown so a sustained problem cannot mail every 10 minutes and get itself muted.

### Budget alarms

Four budgets, all emailing `budget_notification_emails`. The three lifetime budgets are
ANNUALLY so they do not reset inside the six-month life, and track *cumulative* spend:

| Budget | Limit | Fires on |
|---|---:|---|
| `pgds-lifetime-50` | $50 | ACTUAL 100% |
| `pgds-lifetime-160-projection-exceeded` | $160 | ACTUAL 100% |
| `pgds-lifetime-190-credits-nearly-gone` | $190 | ACTUAL 100% + FORECASTED 100% |
| `pgds-monthly-run-rate` | $40 | FORECASTED 100% + ACTUAL 80% |

## 8. Exit plan (before decommissioning)

Decommissioning does not create an application export or recovery point. As of 2026-09-06,
application data exists only on the running EC2 origin and its attached root volume; loss or
corruption is unrecoverable and requires rebuilding and reseeding.

- Planned decommission date: **\_\_** | Owner: **\_\_**

## 9. Rollback owner

- Name: **\_\_** | Contact: **\_\_** | Rollback trigger: 5xx above 5% for 5 minutes, or a
  blank page.

## 10. Handover documents (§14)

| Deliverable | Where |
|---|---|
| Deploy / rollback / purge / resize / quota / exit plan | this file |
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

## 11. Cloudflare origin verification

The intended `X-Origin-Verify` secret-header check is not currently active. Enable it only in
this order so that Cloudflare does not receive an origin 403 during the change:

```text
1. Create the Cloudflare Transform Rule that adds the header.
2. Test it temporarily at the origin:
     add_header X-Seen-Verify $http_x_origin_verify always;   # temporary, then remove
     curl -sI https://vihn.id.vn/ | grep -i x-seen-verify
3. Put the secret in an untracked include — NOT in pgds.conf, which is committed:
     /etc/nginx/conf.d/pgds-origin-secret.conf   →   map $http_x_origin_verify $pgds_origin_ok { "<secret>" 1; default 0; }
4. Uncomment the check in pgds.conf, then:  nginx -t && systemctl reload nginx
5. Re-verify through the edge (expect 200) and directly (expect 403 if you can reach it).
```

Rotation: update the Transform Rule first, then the include — during the gap both values
must be accepted, so keep two entries in the map until the change has propagated.

## 12. Outstanding human actions

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
   not stored in the repository. Re-set it after any fresh clone before applying, or the
   variable's empty default silently creates no subscription at all.
3. **Footer legal text** — Appearance → Customize → Thông tin toà soạn (§13 gate; explicitly
   not a technical decision).
