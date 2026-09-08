#!/bin/sh
# Replace only local editorial content while preserving site configuration,
# canonical categories, static pages, users, navigation and the lunar fallback.
set -eu

PREVIEW_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WP_PATH="$(CDPATH= cd -- "$PREVIEW_DIR/../.." && pwd)"
WP="wp --path=$WP_PATH --allow-root"

if ! $WP core is-installed 2>/dev/null; then
  echo "ERROR: WordPress is not installed. Run infra/local/scripts/setup.sh first." >&2
  exit 1
fi

echo "==> Removing existing articles and teachings from the disposable local stack..."
$WP eval '
  foreach ( array( "post", "pgds_teaching" ) as $post_type ) {
    $ids = get_posts(
      array(
        "post_type"      => $post_type,
        "post_status"    => "any",
        "posts_per_page" => -1,
        "fields"         => "ids",
      )
    );
    foreach ( $ids as $post_id ) {
      wp_delete_post( (int) $post_id, true );
    }
  }

  $attachments = get_posts(
    array(
      "post_type"      => "attachment",
      "post_status"    => "inherit",
      "posts_per_page" => -1,
      "fields"         => "ids",
    )
  );
  foreach ( $attachments as $attachment_id ) {
    if ( "site-logo" === get_post_meta( $attachment_id, "_pgds_local_fixture_key", true ) ) {
      continue;
    }
    wp_delete_attachment( (int) $attachment_id, true );
  }
'

$WP cache flush >/dev/null 2>&1 || true

echo "==> Local editorial content cleared; static pages, taxonomy and site settings were preserved."
