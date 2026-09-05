#!/usr/bin/env bash
#
# Exercise the production release helper inside a disposable Linux filesystem.
# Run through Docker so no host PHP or systemd installation is required.

set -Eeuo pipefail

readonly REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
readonly IMAGE=debian:bookworm-slim

exec docker run --rm --interactive \
  --mount "type=bind,source=$REPO_ROOT,target=/repo,readonly" \
  "$IMAGE" \
  bash -s <<'CONTAINER'
set -Eeuo pipefail
export DEBIAN_FRONTEND=noninteractive
missing_packages=()
command -v cmp >/dev/null || missing_packages+=(coreutils)
command -v find >/dev/null || missing_packages+=(findutils)
command -v php >/dev/null || missing_packages+=(php-cli)
command -v rsync >/dev/null || missing_packages+=(rsync)
command -v flock >/dev/null || missing_packages+=(util-linux)
if [ "${#missing_packages[@]}" -gt 0 ]; then
  apt-get update -qq
  apt-get install -y -qq "${missing_packages[@]}" >/dev/null
fi

install -d -m 0755 /var/www/pgds/wp-content/themes /var/www/pgds/wp-content/plugins
install -d -m 0755 -o www-data -g www-data /var/www/pgds/wp-content/mu-plugins
install -d -m 0755 /var/cache/nginx/fcgi /var/lib/pgds-deploy
install -d -m 0750 -o nobody -g nogroup /var/lib/pgds-deploy/incoming
install -d -m 0750 -o root -g root /var/lib/pgds-deploy/history
install -d -m 0755 /usr/local/sbin
install -m 0755 /repo/infra/scripts/pgds-deploy-release /usr/local/sbin/pgds-deploy-release

cat > /usr/local/sbin/systemctl <<'SYSTEMCTL'
#!/usr/bin/env bash
set -Eeuo pipefail
[ "$1" = reload ] && [ "$2" = php8.3-fpm ]
printf '%s\n' reload >> /tmp/systemctl-calls
[ ! -e /tmp/fail-reload ]
SYSTEMCTL
chmod 0755 /usr/local/sbin/systemctl

fail() {
  printf 'FAIL: %s\n' "$*" >&2
  exit 1
}

assert_file() {
  [ -f "$1" ] || fail "missing file: $1"
}

assert_content() {
  [ "$(< "$1")" = "$2" ] || fail "unexpected content in $1"
}

assert_absent() {
  [ ! -e "$1" ] || fail "unexpected path exists: $1"
}

new_release() {
  local id="$1"
  local root="/var/lib/pgds-deploy/incoming/$id"
  install -d -m 0700 -o nobody -g nogroup \
    "$root/theme/pgds/assets/dist" \
    "$root/plugins/pgds-lunar-calendar/includes" \
    "$root/mu-plugins"

  cat > "$root/theme/pgds/style.css" <<EOF
/* $id */
EOF
  cat > "$root/theme/pgds/functions.php" <<'PHP'
<?php
PHP
  cat > "$root/theme/pgds/assets/dist/main.1234abcd.css" <<'CSS'
body {}
CSS
  cat > "$root/theme/pgds/assets/dist/app.1234abcd.js" <<'JS'
'use strict';
JS
  cat > "$root/theme/pgds/assets/dist/manifest.json" <<'JSON'
{"main.css":"main.1234abcd.css","app.js":"app.1234abcd.js"}
JSON
  cat > "$root/plugins/pgds-lunar-calendar/pgds-lunar-calendar.php" <<'PHP'
<?php
PHP
  cat > "$root/plugins/pgds-lunar-calendar/includes/class-rest-controller.php" <<'PHP'
<?php
PHP
  cat > "$root/mu-plugins/pgds-cache-flush.php" <<'PHP'
<?php
PHP
  cat > "$root/mu-plugins/pgds-lunar-loader.php" <<'PHP'
<?php
PHP
  chown -R nobody:nogroup "$root"
  (
    cd "$root"
    find theme plugins mu-plugins -type f -print0 \
      | LC_ALL=C sort -z \
      | xargs -0 sha256sum > MANIFEST.sha256
  )
  chown nobody:nogroup "$root/MANIFEST.sha256"
}

run_success() {
  local id="$1"
  /usr/local/sbin/pgds-deploy-release "$id" >"/tmp/$id.log" 2>&1 \
    || { cat "/tmp/$id.log" >&2; fail "expected $id to promote"; }
}

run_failure() {
  local id="$1"
  shift
  if /usr/local/sbin/pgds-deploy-release "$id" >"/tmp/$id.log" 2>&1; then
    cat "/tmp/$id.log" >&2
    fail "expected $id to fail: $*"
  fi
}

# A valid release promotes all managed locations, clears only cache files, reloads
# PHP-FPM, and moves the validated payload into root-owned history.
printf old > /var/cache/nginx/fcgi/page.cache
install -d -m 0755 /var/cache/nginx/fcgi/subdir
new_release valid
run_success valid
assert_content /var/www/pgds/wp-content/themes/pgds/style.css '/* valid */'
assert_file /var/www/pgds/wp-content/plugins/pgds-lunar-calendar/pgds-lunar-calendar.php
assert_file /var/www/pgds/wp-content/mu-plugins/pgds-cache-flush.php
assert_absent /var/cache/nginx/fcgi/page.cache
[ -d /var/cache/nginx/fcgi/subdir ] || fail 'cache directory was deleted'
assert_absent /var/lib/pgds-deploy/incoming/valid
[ "$(stat -c '%u:%g' /var/lib/pgds-deploy/history/valid)" = '0:0' ] \
  || fail 'history release is not root-owned'
[ "$(wc -l < /tmp/systemctl-calls)" -eq 1 ] || fail 'PHP-FPM was not reloaded once'

# Invalid IDs cannot escape the incoming root.
run_failure '../escape' 'path traversal ID'

# Symlinks, foreign owners, unmanaged files, incomplete/duplicate manifests,
# checksum mismatches, invalid PHP, and invalid asset manifests are rejected.
new_release symlink
ln -s style.css /var/lib/pgds-deploy/incoming/symlink/theme/pgds/linked.css
chown -h nobody:nogroup /var/lib/pgds-deploy/incoming/symlink/theme/pgds/linked.css
run_failure symlink 'payload symlink'

new_release owner
chown root:root /var/lib/pgds-deploy/incoming/owner/theme/pgds/style.css
run_failure owner 'foreign entry owner'

new_release extra
printf '<?php\n' > /var/lib/pgds-deploy/incoming/extra/mu-plugins/unmanaged.php
chown nobody:nogroup /var/lib/pgds-deploy/incoming/extra/mu-plugins/unmanaged.php
(
  cd /var/lib/pgds-deploy/incoming/extra
  find theme plugins mu-plugins -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum > MANIFEST.sha256
)
chown nobody:nogroup /var/lib/pgds-deploy/incoming/extra/MANIFEST.sha256
run_failure extra 'unmanaged mu-plugin'

new_release missing
sed -i '/style.css$/d' /var/lib/pgds-deploy/incoming/missing/MANIFEST.sha256
run_failure missing 'missing manifest entry'

new_release duplicate
cat /var/lib/pgds-deploy/incoming/duplicate/MANIFEST.sha256 \
  >> /var/lib/pgds-deploy/incoming/duplicate/MANIFEST.sha256.tmp
cat /var/lib/pgds-deploy/incoming/duplicate/MANIFEST.sha256 \
  >> /var/lib/pgds-deploy/incoming/duplicate/MANIFEST.sha256.tmp
mv /var/lib/pgds-deploy/incoming/duplicate/MANIFEST.sha256.tmp \
  /var/lib/pgds-deploy/incoming/duplicate/MANIFEST.sha256
chown nobody:nogroup /var/lib/pgds-deploy/incoming/duplicate/MANIFEST.sha256
run_failure duplicate 'duplicate manifest entries'

new_release checksum
printf tampered >> /var/lib/pgds-deploy/incoming/checksum/theme/pgds/style.css
run_failure checksum 'checksum mismatch'

new_release php-invalid
printf '<?php function broken( {\n' \
  > /var/lib/pgds-deploy/incoming/php-invalid/theme/pgds/functions.php
(
  cd /var/lib/pgds-deploy/incoming/php-invalid
  find theme plugins mu-plugins -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum > MANIFEST.sha256
)
chown -R nobody:nogroup /var/lib/pgds-deploy/incoming/php-invalid
run_failure php-invalid 'PHP syntax error'

new_release asset-invalid
printf '{"main.css":"missing.1234abcd.css","app.js":"app.1234abcd.js"}\n' \
  > /var/lib/pgds-deploy/incoming/asset-invalid/theme/pgds/assets/dist/manifest.json
(
  cd /var/lib/pgds-deploy/incoming/asset-invalid
  find theme plugins mu-plugins -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum > MANIFEST.sha256
)
chown -R nobody:nogroup /var/lib/pgds-deploy/incoming/asset-invalid
run_failure asset-invalid 'missing manifest target'

# Holding the lock blocks a concurrent promotion and leaves its staging tree intact.
new_release locked
(
  flock -n 8 || exit 1
  sleep 3
) 8>/run/lock/pgds-deploy.lock &
lock_pid=$!
sleep 1
run_failure locked 'lock contention'
wait "$lock_pid"
[ -d /var/lib/pgds-deploy/incoming/locked ] || fail 'locked release staging was removed'

# A reload failure after file writes restores all managed runtime bytes, removes a
# newly introduced plugin, reloads the restored tree, and leaves no cache files.
printf '/* previous */\n' > /var/www/pgds/wp-content/themes/pgds/style.css
rm -rf /var/www/pgds/wp-content/plugins/pgds-lunar-calendar
printf '<?php // previous cache\n' > /var/www/pgds/wp-content/mu-plugins/pgds-cache-flush.php
rm -f /var/www/pgds/wp-content/mu-plugins/pgds-lunar-loader.php
printf stale > /var/cache/nginx/fcgi/rollback.cache
new_release rollback
: > /tmp/fail-reload
run_failure rollback 'PHP-FPM reload failure'
rm -f /tmp/fail-reload
assert_content /var/www/pgds/wp-content/themes/pgds/style.css '/* previous */'
assert_absent /var/www/pgds/wp-content/plugins/pgds-lunar-calendar
assert_content /var/www/pgds/wp-content/mu-plugins/pgds-cache-flush.php '<?php // previous cache'
assert_absent /var/www/pgds/wp-content/mu-plugins/pgds-lunar-loader.php
assert_absent /var/cache/nginx/fcgi/rollback.cache

# Successful releases retain only the newest five immutable release payloads.
for sequence in 1 2 3 4 5 6; do
  id="retention-$sequence"
  new_release "$id"
  run_success "$id"
  sleep 1
done
[ "$(find /var/lib/pgds-deploy/history -mindepth 1 -maxdepth 1 -type d | wc -l)" -eq 5 ] \
  || fail 'history did not retain exactly five releases'
assert_absent /var/lib/pgds-deploy/history/valid
assert_file /var/lib/pgds-deploy/history/retention-6/MANIFEST.sha256

printf 'pgds-deploy-release disposable tests passed\n'
CONTAINER
