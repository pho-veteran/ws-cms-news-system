#!/usr/bin/env bash
# Static release-boundary checks that do not require a booted WordPress instance.
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

fail() {
  echo "ERROR: $*" >&2
  exit 1
}

for slug in \
  tin-phat-su song-an-lanh am-thuc-chay loi-song-xanh phat-tich \
  media video emagazine tot-doi-dep-dao vietnam-buddhism; do
  wrapper="wp-content/themes/pgds/category-${slug}.php"
  test -f "$wrapper" || fail "missing category wrapper: $wrapper"
  grep -Fq "get_theme_file_path( '/category.php' )" "$wrapper" \
    || fail "category wrapper does not delegate to the shared renderer: $wrapper"
done

grep -Fq 'array_chunk( $vids, 50 )' wp-content/themes/pgds/inc/cli-import.php \
  || fail 'YouTube videos.list batching is no longer capped at 50 IDs'
grep -Fq 'if ( $fail_rate > 0.02 )' wp-content/themes/pgds/inc/cli-import.php \
  || fail 'import dry-run stop threshold is no longer greater than 2%'
grep -Fq "add_action( 'transition_post_status', 'pgds_flush_page_cache_on_transition'" wp-content/mu-plugins/pgds-cache-flush.php \
  || fail 'cache purge is no longer driven by transition_post_status'
if grep -Eq "add_action\( *['\"]save_post['\"].*pgds_flush_page_cache" wp-content/mu-plugins/pgds-cache-flush.php; then
  fail 'cache purge regressed to save_post'
fi

origin_line="$(grep -n -- '- name: Promote release at origin' .github/workflows/deploy.yml | cut -d: -f1)"
edge_line="$(grep -n -- '- name: Purge Cloudflare when assets changed' .github/workflows/deploy.yml | cut -d: -f1)"
test -n "$origin_line" && test -n "$edge_line" && test "$origin_line" -lt "$edge_line" \
  || fail 'deploy must promote and purge the origin before purging the edge'

legacy_files="$(git grep -l -E 'tin-giao-hoi|su-kien-le-hoi|chua-am|di-tich-danh-thang|nguoi-tot-viec-tot|thien-nguyen|infographic-emagazine' -- \
  'wp-content/themes/pgds/*.php' 'wp-content/themes/pgds/inc/*.php' 'tools/*.php' || true)"
while IFS= read -r file; do
  test -z "$file" && continue
  case "$file" in
    wp-content/themes/pgds/inc/cpt-tax.php|tools/verify-taxonomy.php) ;;
    *) fail "retired category slug remains in runtime lookup: $file" ;;
  esac
done <<< "$legacy_files"

echo '==> Static EPIC #3 release contracts passed.'
