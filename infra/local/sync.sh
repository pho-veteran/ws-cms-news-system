#!/usr/bin/env bash
# =============================================================================
# Push the repo's wp-content into the running containers with `docker cp`.
#
# Why this exists: the compose file declares bind mounts, but they only work when
# the Docker daemon shares this machine's filesystem. When DOCKER_HOST points at
# a remote/socket-forwarded daemon (as it does in this dev environment), the
# daemon resolves those host paths against ITS OWN filesystem, finds nothing, and
# silently mounts empty directories. The theme then appears absent inside the
# container and `wp theme activate pgds` fails.
#
# `docker cp` streams through the API instead of the daemon's filesystem, so it
# works in both cases. Re-run it after every edit to the theme, mu-plugins, or
# scripts. It is idempotent.
#
# Run: ./sync.sh          (from infra/local)
# =============================================================================
set -euo pipefail

cd "$(dirname "$0")"
REPO_ROOT="$(cd ../.. && pwd)"

# Resolve the container names compose assigned, rather than hardcoding a project prefix.
wp_container="$(docker compose ps -q wordpress)"
if [ -z "$wp_container" ]; then
  echo "ERROR: the wordpress container is not running. Run 'docker compose up -d' first." >&2
  exit 1
fi

echo "==> Syncing wp-content into the wordpress container..."
docker exec "$wp_container" mkdir -p \
  /var/www/html/wp-content/themes/pgds \
  /var/www/html/wp-content/mu-plugins

# `docker cp <dir>/. <dest>` copies the directory CONTENTS, so re-running does not
# nest pgds/pgds. Excludes are handled by copying only what the theme needs at
# runtime: node_modules is a build-time dependency and is 100MB+, so it stays out.
docker exec "$wp_container" sh -c 'rm -rf /tmp/pgds-sync && mkdir -p /tmp/pgds-sync'
tar -C "$REPO_ROOT/wp-content/themes" \
  --exclude='pgds/node_modules' \
  --exclude='pgds/.git' \
  -cf - pgds | docker cp - "$wp_container:/tmp/pgds-sync"
docker exec "$wp_container" sh -c '
  rm -rf /var/www/html/wp-content/themes/pgds &&
  mv /tmp/pgds-sync/pgds /var/www/html/wp-content/themes/pgds &&
  chown -R www-data:www-data /var/www/html/wp-content/themes/pgds
'

docker cp "$REPO_ROOT/wp-content/mu-plugins/." \
  "$wp_container:/var/www/html/wp-content/mu-plugins/" >/dev/null
docker exec "$wp_container" chown -R www-data:www-data /var/www/html/wp-content/mu-plugins

echo "==> Syncing tools and scripts (for the wpcli container to reach via the shared wp_core volume)..."
docker exec "$wp_container" mkdir -p /var/www/html/.pgds-tools /var/www/html/.pgds-scripts
docker cp "$REPO_ROOT/tools/." "$wp_container:/var/www/html/.pgds-tools/" >/dev/null
docker cp "$REPO_ROOT/infra/local/scripts/." "$wp_container:/var/www/html/.pgds-scripts/" >/dev/null
docker exec "$wp_container" sh -c 'chmod +x /var/www/html/.pgds-scripts/*.sh'

echo "==> Sync complete."
echo "    Theme:   /var/www/html/wp-content/themes/pgds"
echo "    Tools:   /var/www/html/.pgds-tools    (referenced as /tools by the scripts)"
echo "    Scripts: /var/www/html/.pgds-scripts"
