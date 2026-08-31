#!/usr/bin/env bash
#
# LEMP bootstrap for the pgds Lightsail instance (Ubuntu 24.04, bundle small_3_0,
# 2 vCPU / 2GB RAM / 60GB SSD). Tuned to the RAM budget in Proposal 02 §4.1:
#
#   OS + nginx                                    ~250 MB
#   MariaDB (innodb_buffer_pool_size=256M)         ~400 MB
#   Redis   (maxmemory 160mb)                      ~160 MB
#   PHP-FPM (pm=ondemand, pm.max_children=6)       ~360 MB
#   ------------------------------------------------------
#   Total                                         ~1.17 GB, ~850 MB left for
#                                                  OS page cache.
#
# This script only provisions the LEMP layer + hardening baseline. WordPress
# core, the theme, plugins, and content are installed manually / via WP-CLI
# afterwards — see Proposal 02 §9.2 ("do not attempt to make the WordPress
# layer declarative"). Idempotent-ish: safe to inspect via
# /var/log/cloud-init-output.log, not designed to be re-run.

set -euxo pipefail
export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------------------
# 1. Base packages
# ---------------------------------------------------------------------------
apt-get update
apt-get upgrade -y
apt-get install -y \
  nginx \
  mariadb-server \
  redis-server \
  php8.3-fpm php8.3-mysql php8.3-gd php8.3-curl php8.3-mbstring \
  php8.3-xml php8.3-zip php8.3-intl php8.3-imagick \
  fail2ban \
  unattended-upgrades \
  unzip \
  curl

# ---------------------------------------------------------------------------
# 2. Swap — insurance against OOM, NOT capacity (§4.1). Regular swap usage
#    means the tuning below is wrong and should be revisited, not the swap
#    size increased.
# ---------------------------------------------------------------------------
if [ ! -f /swapfile ]; then
  fallocate -l 2G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
  # Prefer RAM over swap; only spill under real pressure.
  sysctl -w vm.swappiness=10
  echo 'vm.swappiness=10' >> /etc/sysctl.conf
fi

# ---------------------------------------------------------------------------
# 3. PHP-FPM — pm=ondemand keeps idle workers from holding RAM; max_children
#    is a starting point per §4.1, tune from real max_children-reached logs.
# ---------------------------------------------------------------------------
PHP_POOL=/etc/php/8.3/fpm/pool.d/www.conf
sed -i \
  -e 's/^pm = .*/pm = ondemand/' \
  -e 's/^pm.max_children = .*/pm.max_children = 6/' \
  -e 's/^;pm.process_idle_timeout = .*/pm.process_idle_timeout = 10s/' \
  "$PHP_POOL"

systemctl enable php8.3-fpm
systemctl restart php8.3-fpm

# ---------------------------------------------------------------------------
# 4. MariaDB — innodb_buffer_pool_size=256M per §4.1 RAM budget.
# ---------------------------------------------------------------------------
cat > /etc/mysql/mariadb.conf.d/60-pgds-tuning.cnf <<'EOF'
[mysqld]
innodb_buffer_pool_size = 256M
innodb_buffer_pool_instances = 1
innodb_log_file_size = 64M
max_connections = 40
EOF

systemctl enable mariadb
systemctl restart mariadb

# ---------------------------------------------------------------------------
# 5. Redis — object cache backend. maxmemory 160mb + allkeys-lru per §4.1.
#    Not used for FastCGI cache (that stays on disk, not tmpfs — §4.1).
# ---------------------------------------------------------------------------
sed -i \
  -e 's/^maxmemory .*/maxmemory 160mb/' \
  -e 's/^maxmemory-policy .*/maxmemory-policy allkeys-lru/' \
  /etc/redis/redis.conf

grep -q '^maxmemory ' /etc/redis/redis.conf || echo 'maxmemory 160mb' >> /etc/redis/redis.conf
grep -q '^maxmemory-policy ' /etc/redis/redis.conf || echo 'maxmemory-policy allkeys-lru' >> /etc/redis/redis.conf

systemctl enable redis-server
systemctl restart redis-server

# ---------------------------------------------------------------------------
# 6. Nginx — FastCGI cache path lives on disk (never tmpfs, §4.1: tmpfs would
#    directly consume RAM the budget above does not have). The actual
#    server block / cache zone / secret-header check ships from
#    infra/nginx/pgds.conf; this only prepares the cache directory and
#    disables the default site.
#
#    The directory name MUST stay /var/cache/nginx/fcgi. Three places have to
#    agree on it and there is no runtime error if they do not:
#      - infra/nginx/http-snippet.conf  fastcgi_cache_path
#      - wp-content/mu-plugins/pgds-cache-flush.php  PGDS_FCGI_CACHE_DIR
#      - this script
#    An earlier version created ".../fastcgi" here while the other two used
#    ".../fcgi". The purge then deleted files from a directory nginx never wrote
#    to, so it always "succeeded" while every cached page stayed stale —
#    silently breaking the one requirement §5.1 calls non-negotiable, that an
#    editor's change is visible to anonymous readers on the next request.
#
#    php-fpm must be able to delete files here, because the mu-plugin purges as
#    the web user rather than through a privileged helper (§5.4). Both nginx and
#    php-fpm run as www-data on Ubuntu, so ownership alone is sufficient; the
#    group-write note in §5.4 applies only where they run as different users.
# ---------------------------------------------------------------------------
mkdir -p /var/cache/nginx/fcgi
chown -R www-data:www-data /var/cache/nginx/fcgi
chmod 750 /var/cache/nginx/fcgi
rm -f /etc/nginx/sites-enabled/default

# Placeholder for the §9.4 redirect map. infra/nginx/pgds.conf does
# `include /etc/nginx/redirects.map` inside a map{} block, and `include` is a HARD
# dependency: with the file absent nginx refuses to start entirely —
#   [emerg] open() "/etc/nginx/redirects.map" failed (2: No such file or directory)
# The map is only generated later, by `wp pgds build-redirects` after the 2,000-post
# import, so without this touch the very first deploy of the real server block takes
# the origin down instead of just missing its redirects. An empty map is valid and
# matches nothing, so creating it early is free.
touch /etc/nginx/redirects.map
chmod 644 /etc/nginx/redirects.map

systemctl enable nginx

# ---------------------------------------------------------------------------
# 7. fail2ban — default jail is enough at this stage; wp-login.php specific
#    jail is added once WordPress is installed (Proposal 02 §10.2).
# ---------------------------------------------------------------------------
systemctl enable fail2ban
systemctl restart fail2ban

# ---------------------------------------------------------------------------
# 8. SSH hardening — key-only auth (§10.2). Lightsail already disables
#    password auth on its default images, but assert it explicitly.
# ---------------------------------------------------------------------------
SSHD_CONFIG=/etc/ssh/sshd_config
sed -i \
  -e 's/^#\?PasswordAuthentication .*/PasswordAuthentication no/' \
  -e 's/^#\?PermitRootLogin .*/PermitRootLogin no/' \
  "$SSHD_CONFIG"
systemctl restart ssh || systemctl restart sshd

# ---------------------------------------------------------------------------
# 9. Unattended security upgrades — keep the OS patched with no manual cron.
# ---------------------------------------------------------------------------
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "pgds LEMP bootstrap complete." > /var/log/pgds-user-data-done.log
