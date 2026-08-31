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
- YouTube posters: `wp pgds yt-sync` pulls thumbnails and durations locally.
