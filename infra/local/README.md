# Local dev environment (Docker)

For **verifying the theme actually runs** before deploying. This is not production —
production uses Nginx FastCGI cache, see `infra/nginx/`.

## Requirements

- Docker running.
- Assets built: `cd wp-content/themes/pgds && npm run build` (`assets/dist/` is gitignored,
  and the theme loads no CSS/JS without it).

## Run

```bash
cd infra/local
docker compose up -d                              # db + redis + wordpress (apache)

# php -l across the theme and mu-plugins (catches syntax errors)
docker compose run --rm wpcli /scripts/lint.sh

# install WP, activate the theme, seed categories, import sample data
docker compose run --rm wpcli /scripts/setup.sh
```

Open http://localhost:8080 — the front page renders 11 blocks.
Admin: http://localhost:8080/wp-admin (admin / admin123).

## Tear down

```bash
docker compose down          # keep data
docker compose down -v       # drop volumes too (start clean next time)
```

## Notes

- The theme and mu-plugins are mounted straight from the repo, so PHP edits show up
  immediately. CSS/JS changes still need a rebuild.
- The Redis object cache is installed via the `redis-cache` plugin, which needs network
  access on first run.

### This stack has ONE of the four §8 plugins — do not verify schema here

`setup.sh` installs only `redis-cache`. The origin runs all four from Proposal 02 §8:

| Plugin | Local | Origin |
|---|---|---|
| `redis-cache` | yes | yes |
| `autodescription` (The SEO Framework) | **no** | yes |
| `two-factor` | **no** | yes |
| `wp-mail-smtp` | **no** | yes |

That matters for one specific check. §7 splits schema ownership: `NewsArticle`,
`BreadcrumbList` and `WebSite` belong to the SEO plugin, while the theme emits only
`VideoObject` and `NewsMediaOrganization`. The §13 gate is "confirm that **no schema is
emitted twice**" — and that is **unfalsifiable locally**, because the plugin that owns half
the output is absent. A local page showing one `VideoObject` block proves nothing about
duplication.

Verify it against the origin instead:

```bash
ssh ubuntu@<origin> \
  "curl -s -H 'Cookie: wordpress_logged_in_probe=1' http://127.0.0.1/<a-post-slug>/" \
  | grep -o 'application/ld+json'
```

Measured on the origin 2026-09-01: 2 blocks — `WebSite` + `WebPage` (plugin) and
`VideoObject` (theme), no type repeated. The `wordpress_logged_in_*` cookie is required or
FastCGI serves a cached copy and the response says nothing about the current code.

The theme's own `NewsArticle` output stays behind `PGDS_EMIT_ARTICLE_SCHEMA` precisely so
this split cannot collide; see `inc/seo-schema.php`.
- YouTube posters: `wp pgds yt-sync` pulls thumbnails and durations locally.
