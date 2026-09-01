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

# The dry run is a GATE, not a formality (proposal §9.2: "dry run failure rate > 2%
# means stop; do not import"). `|| true` on this line defeated it entirely: the importer
# correctly exits non-zero above 2%, and the script then ran the real import anyway.
# That made the one safety check in the migration path decorative — exactly the "start
# importing while D0 is incomplete" risk §15 rates High.
echo "==> Importing sample data (dry run first — a failure here stops the script)..."
$WP pgds import --file="$TOOLS/sample-data/data.sample.json" --batch=200 --dry-run
echo "==> Performing the actual import..."
$WP pgds import --file="$TOOLS/sample-data/data.sample.json" --batch=200

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

# WITH a body: pgds_teaching is publicly_queryable, so this has a real single route served
# by single.php. Created with a title alone it rendered an empty article — heading, meta,
# then prev/next with nothing between. See the same fix in seed-media.sh.
# Idempotent, and WITH a body.
#
# Two defects here, both found by opening the rendered route:
#
#   1. No existence check. `wp post create` ran unconditionally, so every re-run of this
#      script added another copy — the database held TWO "Buông bỏ chấp niệm" posts. The
#      sidebar block then listed the same teaching twice, and each duplicate had its own
#      public URL competing with the original.
#   2. No --post_content. pgds_teaching is publicly_queryable, so each item has a real
#      single route served by single.php; with an empty body it rendered a bare shell —
#      heading, meta line, then prev/next with nothing between.
#
# Same seed_teaching pattern as seed-media.sh: create when absent, backfill an empty body,
# never overwrite a body someone has written.
echo "==> Creating one Buddhist teaching item..."
PGDS_TEACHING_TITLE="Buông bỏ chấp niệm để tâm an nhiên"
PGDS_TEACHING_BODY="<p>“Người thả được gánh nặng xuống mới biết vai mình vốn nhẹ.”</p>
<p>Chấp niệm là việc giữ mãi một ý nghĩ, một mong muốn hay một vết thương và không cho nó đi qua. Buông bỏ không có nghĩa là quên, cũng không phải bỏ mặc — mà là thôi dùng sức để níu giữ điều đã qua.</p>
<p>Thực hành cụ thể: khi một ý nghĩ cũ trở lại, chỉ cần nhận biết “đây là ý nghĩ cũ”, không tranh luận với nó, không nuôi thêm. Nó sẽ tự nhạt dần như mọi hiện tượng khác.</p>"

PGDS_TEACHING_ID="$($WP post list --post_type=pgds_teaching --title="$PGDS_TEACHING_TITLE" --field=ID | head -1)"
if [ -z "$PGDS_TEACHING_ID" ]; then
  $WP post create --post_type=pgds_teaching --post_status=publish \
    --post_title="$PGDS_TEACHING_TITLE" --post_content="$PGDS_TEACHING_BODY" >/dev/null || true
elif [ -z "$($WP post get "$PGDS_TEACHING_ID" --field=post_content | tr -d '[:space:]')" ]; then
  $WP post update "$PGDS_TEACHING_ID" --post_content="$PGDS_TEACHING_BODY" >/dev/null || true
fi

echo ""
echo "==> COMPLETE. Open http://localhost:8080  (admin/admin123 at /wp-admin)"
