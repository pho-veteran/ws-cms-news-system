#!/bin/sh
# =============================================================================
# Bootstrap local WordPress, activate the pgds theme, seed data, and import sample data.
#
# Run: ./sync.sh && docker compose run --rm wpcli /var/www/html/.pgds-scripts/setup.sh
#
# The scripts and tools directories are NOT bind-mounted (see docker-compose.yml).
# ./sync.sh copies them into the shared wp_core volume, so they are reachable at
# /var/www/html/.pgds-scripts and /var/www/html/.pgds-tools inside any service.
# =============================================================================
set -e

WP="wp --path=/var/www/html --allow-root"
TOOLS="/var/www/html/.pgds-tools"

echo "==> Waiting for WordPress core to be ready..."
until $WP core is-installed 2>/dev/null || $WP core version 2>/dev/null; do
  # Core files already exist (copied from the image); only the database is needed. Install if not installed.
  break
done

if ! $WP core is-installed 2>/dev/null; then
  echo "==> Installing WordPress..."
  $WP core install \
    --url="http://localhost:8080" \
    --title="Phật giáo và Đời sống" \
    --admin_user="admin" \
    --admin_password="admin123" \
    --admin_email="admin@example.com" \
    --skip-email
fi

echo "==> Setting Vietnamese language and pretty permalinks..."
$WP option update blogname "Phật giáo và Đời sống"
$WP option update blogdescription "Tin tức, đời sống và văn hóa Phật giáo"
$WP rewrite structure '/%postname%/' --hard
$WP option update timezone_string 'Asia/Ho_Chi_Minh'

echo "==> Installing the required plugin (Redis object cache)..."
$WP plugin install redis-cache --activate || echo "  (skipped if no network is available)"
$WP redis enable 2>/dev/null || echo "  (redis enable skipped)"

echo "==> Activating the pgds theme (which seeds 13 categories)..."
$WP theme activate pgds

echo "==> Seeding categories (call directly in case the hook has not run)..."
$WP eval 'if (function_exists("pgds_seed_categories")) { pgds_seed_categories(); echo "seeded\n"; }'

echo "==> Flushing rewrite rules (video-sitemap.xml)..."
$WP rewrite flush --hard

echo "==> Importing sample data (dry run first)..."
$WP pgds import --file="$TOOLS/sample-data/data.sample.json" --batch=200 --dry-run || true
echo "==> Performing the actual import..."
$WP pgds import --file="$TOOLS/sample-data/data.sample.json" --batch=200 || true

echo "==> Marking the first post as Featured News (lead) so the homepage has data..."
FIRST_ID=$($WP post list --post_type=post --posts_per_page=1 --field=ID --orderby=date --order=DESC)
if [ -n "$FIRST_ID" ]; then
  $WP post meta update "$FIRST_ID" _pgds_is_featured 1
  $WP post meta update "$FIRST_ID" _pgds_feature_rank 1
  $WP post meta update "$FIRST_ID" _pgds_photo_story 1
fi

echo "==> Creating basic static pages..."
$WP post create --post_type=page --post_status=publish --post_title="Giới thiệu" --post_content="Chuyên trang tin tức Phật giáo." >/dev/null || true
$WP post create --post_type=page --post_status=publish --post_title="Liên hệ" --post_content="toasoan@phatgiaovadoisong.vn" >/dev/null || true

echo "==> Creating one Buddhist teaching item..."
$WP post create --post_type=pgds_teaching --post_status=publish --post_title="Buông bỏ chấp niệm để tâm an nhiên" >/dev/null || true

echo ""
echo "==> COMPLETE. Open http://localhost:8080  (admin/admin123 at /wp-admin)"
