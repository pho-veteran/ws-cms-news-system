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
docker compose up -d --build                      # db + redis + wordpress (apache)
./sync.sh                                         # copy repo files into the shared Docker volume

# php -l across the theme and mu-plugins (catches syntax errors)
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-scripts/lint.sh'

# install WP, activate the theme, seed lunar data, import sample data
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-scripts/setup.sh'

# isolated CMS plus EPIC #3 category/SEO/importer/cron/cache regressions
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/tests/cms-editor-regression.sh'
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/tests/compatibility-regression.sh'
```

Open http://localhost:8080 — the front page renders 11 blocks.
Admin: http://localhost:8080/wp-admin (admin / admin123).

## Development preview dataset

For a rich, reproducible editorial corpus, layer the development-only fixture bundle in
[`tools/preview/`](../../tools/preview/) onto the canonical local setup. It contains 180
articles across the four editorial surfaces, eight teaching entries, 25 checked-in licensed
image fixtures (including four local YouTube posters). The seed verifies every checksum, imports
content media into the Media Library, and assigns imagery through normal CMS fields.

The bundle does not own the site's identity, static theme logo, header, footer, category setup,
pages, menus, supporting post types or lunar/sidebar content. The theme logo is a static asset
and is never created as Media Library data. This fixture corpus must never be deployed or
imported into production.

```bash
cd infra/local
docker compose up -d --build
./sync.sh

# Establish the original local site, then add the article fixtures.
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-scripts/setup.sh'
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/preview/reset.sh'
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/preview/seed.sh'
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/preview/verify.sh'
```

The seed uses stable `preview-2026-` source IDs and stable Media Library asset keys. It never runs
`wp pgds yt-sync` or contacts a remote media source. Re-run `./sync.sh` after changing files.

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
