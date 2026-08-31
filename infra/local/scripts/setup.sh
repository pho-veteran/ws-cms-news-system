#!/bin/sh
# =============================================================================
# Bootstrap WordPress local + kich hoat theme pgds + seed + import sample data.
# Chay: docker compose run --rm wpcli /scripts/setup.sh
# =============================================================================
set -e

WP="wp --path=/var/www/html --allow-root"

echo "==> Cho WordPress core san sang..."
until $WP core is-installed 2>/dev/null || $WP core version 2>/dev/null; do
  # Core file da co (image tu chep), chi can db. Neu chua cai thi cai.
  break
done

if ! $WP core is-installed 2>/dev/null; then
  echo "==> Cai WordPress..."
  $WP core install \
    --url="http://localhost:8080" \
    --title="Phật giáo và Đời sống" \
    --admin_user="admin" \
    --admin_password="admin123" \
    --admin_email="admin@example.com" \
    --skip-email
fi

echo "==> Thiet lap tieng Viet + permalink dep..."
$WP option update blogname "Phật giáo và Đời sống"
$WP option update blogdescription "Tin tức, đời sống và văn hóa Phật giáo"
$WP rewrite structure '/%postname%/' --hard
$WP option update timezone_string 'Asia/Ho_Chi_Minh'

echo "==> Cai plugin can thiet (Redis object cache)..."
$WP plugin install redis-cache --activate || echo "  (bo qua neu khong co mang)"
$WP redis enable 2>/dev/null || echo "  (redis enable bo qua)"

echo "==> Kich hoat theme pgds (tu seed 13 category)..."
$WP theme activate pgds

echo "==> Seed category (goi truc tiep phong khi hook chua chay)..."
$WP eval 'if (function_exists("pgds_seed_categories")) { pgds_seed_categories(); echo "seeded\n"; }'

echo "==> Flush rewrite (video-sitemap.xml)..."
$WP rewrite flush --hard

echo "==> Import sample data (dry-run truoc)..."
$WP pgds import --file=/tools/sample-data/data.sample.json --batch=200 --dry-run || true
echo "==> Import that..."
$WP pgds import --file=/tools/sample-data/data.sample.json --batch=200 || true

echo "==> Danh dau bai dau lam Tin noi bat (lead) de trang chu co du lieu..."
FIRST_ID=$($WP post list --post_type=post --posts_per_page=1 --field=ID --orderby=date --order=DESC)
if [ -n "$FIRST_ID" ]; then
  $WP post meta update "$FIRST_ID" _pgds_is_featured 1
  $WP post meta update "$FIRST_ID" _pgds_feature_rank 1
  $WP post meta update "$FIRST_ID" _pgds_photo_story 1
fi

echo "==> Tao trang tinh co ban..."
$WP post create --post_type=page --post_status=publish --post_title="Giới thiệu" --post_content="Chuyên trang tin tức Phật giáo." >/dev/null || true
$WP post create --post_type=page --post_status=publish --post_title="Liên hệ" --post_content="toasoan@phatgiaovadoisong.vn" >/dev/null || true

echo "==> Tao 1 muc Loi Phat day..."
$WP post create --post_type=pgds_teaching --post_status=publish --post_title="Buông bỏ chấp niệm để tâm an nhiên" >/dev/null || true

echo ""
echo "==> XONG. Mo http://localhost:8080  (admin/admin123 tai /wp-admin)"
