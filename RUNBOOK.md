# RUNBOOK — Phật giáo và Đời sống

Operating the WordPress system on Lightsail. Supplements `docs/initial_entries/PROPOSAL_01`
and `PROPOSAL_02`.

> `Measured RTO`: fill in after the restore drill in D4 (proposal §13).

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

1. Lightsail console → Snapshots → create a new instance from the most recent snapshot.
2. Detach the static IP from the old instance, attach it to the new one.
3. `nginx -t && systemctl reload nginx`; verify `X-Cache`, the front page, one post,
   one category.
4. **Measured RTO: **\_\_** minutes** (fill in during the drill).

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

- Planned decommission date: **\_\_** | Owner: **\_\_**

## 9. Rollback owner

- Name: **\_\_** | Contact: **\_\_** | Rollback trigger: 5xx above 5% for 5 minutes, or a
  blank page.
