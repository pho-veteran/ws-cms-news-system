# RUNBOOK — Phật giáo và Đời sống

Operating the WordPress system on Lightsail. Supplements `docs/initial_entries/PROPOSAL_01`
and `PROPOSAL_02`.

> `Measured RTO`: fill in after the restore drill in D4 (proposal §13).

## 1. Deploy the theme

```bash
# CI (GitHub Actions on push to main): lint -> build -> rsync -> purge
# Manual (during the 4-day build):
cd wp-content/themes/pgds && npm ci && npm run build
rsync -az --delete wp-content/themes/pgds/ deploy@HOST:/var/www/pgds/wp-content/themes/pgds/
ssh deploy@HOST 'sudo systemctl reload php8.3-fpm && sudo find /var/cache/nginx/fcgi -type f -delete'
```

Purge order after deploy (proposal §12.1): **origin first, edge second**.
1. rsync theme → 2. reload php-fpm (resets opcache) → 3. flush FastCGI → 4. purge Cloudflare
(only when assets changed).

> **Note:** `.github/workflows/` does not exist yet, so the CI pipeline described above is
> not wired up. Deployment is currently the manual rsync path, and the rollback below does
> not auto-deploy.

## 2. Rollback

```bash
git revert <sha> && git push        # ~60s once CI exists; today, re-rsync manually
# Or rsync the previous build back; the blast radius is one theme directory.
```

## 3. Manual cache purge

```bash
sudo find /var/cache/nginx/fcgi -type f -delete      # flush the entire HTML cache
# Verify: curl -sI https://site/ | grep X-Cache      (HIT on the second request)
```

Content edited in the admin already purges itself: the `pgds-cache-flush.php` mu-plugin
hooks `save_post`.

## 4. Restore from a snapshot (SPOF — target RTO 30–60 min)

1. Lightsail console → Snapshots → create a new instance from the most recent snapshot.
2. Detach the static IP from the old instance, attach it to the new one.
3. `nginx -t && systemctl reload nginx`; verify `X-Cache`, the front page, one post,
   one category.
4. **Measured RTO: ______ minutes** (fill in during the drill).

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

## 7. Metrics to watch (CloudWatch)

CPU burst < 20%, RAM > 85%, disk > 80%, 5xx > 1%, healthcheck failures.
Snapshot at 03:00 ICT, DB dump every 6 hours.

## 8. Exit plan (before decommissioning)

```bash
wp export --dir=/backup/wxr        # export all content as WXR
tar czf /backup/uploads.tgz wp-content/uploads
# Pull both somewhere safe BEFORE tearing down the infrastructure.
```

- Planned decommission date: ______  |  Owner: ______

## 9. Rollback owner

- Name: ______  |  Contact: ______  |  Rollback trigger: 5xx above 5% for 5 minutes, or a
  blank page.
