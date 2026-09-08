#!/bin/sh
# =============================================================================
# Add the deterministic, development-only PGDS editorial fixtures.
#
# Run from the repository root:
#   cd infra/local && docker compose up -d && ./sync.sh
#   docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-scripts/setup.sh'
#   docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/preview/seed.sh'
#
# Run the canonical local setup first. This script imports 180 preview articles,
# CMS-managed media, homepage curation and eight teaching entries. It does not install
# or configure WordPress and never connects to production or runs yt-sync.
# =============================================================================
set -eu

PREVIEW_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WP_PATH="$(CDPATH= cd -- "$PREVIEW_DIR/../.." && pwd)"
WP="wp --path=$WP_PATH --allow-root"
MEDIA_MANIFEST="$PREVIEW_DIR/preview-media.json"
IMG_DIR="$PREVIEW_DIR/media"
export PGDS_PREVIEW_MANIFEST="$MEDIA_MANIFEST"

if [ ! -r "$MEDIA_MANIFEST" ]; then
  echo "ERROR: preview media manifest is missing or unreadable: $MEDIA_MANIFEST" >&2
  exit 1
fi

if [ ! -f "$PREVIEW_DIR/preview-content.json" ] || [ ! -f "$PREVIEW_DIR/preview-teachings.json" ] || [ ! -f "$PREVIEW_DIR/seed-preview.php" ] || [ ! -f "$MEDIA_MANIFEST" ]; then
  echo "ERROR: preview fixture files are missing from $PREVIEW_DIR. Re-run ./sync.sh." >&2
  exit 1
fi

if [ ! -d "$IMG_DIR" ]; then
  echo "ERROR: checked-in article media is missing from $IMG_DIR. Re-run ./sync.sh." >&2
  exit 1
fi

echo "==> Installing WordPress and activating the theme..."
if ! $WP core is-installed 2>/dev/null; then
  echo "ERROR: WordPress is not installed. Run infra/local/scripts/setup.sh first." >&2
  exit 1
fi

if ! $WP theme is-active pgds 2>/dev/null; then
  echo "ERROR: The pgds theme is not active. Run infra/local/scripts/setup.sh first." >&2
  exit 1
fi

for fixture in $($WP eval '$data = json_decode( file_get_contents( (string) getenv( "PGDS_PREVIEW_MANIFEST" ) ), true ); if ( ! is_array( $data ) ) { WP_CLI::error( "Invalid preview media manifest." ); } foreach ( (array) ( $data["assets"] ?? array() ) as $asset ) { echo sanitize_file_name( (string) ( $asset["fixture"] ?? "" ) ) . "\n"; }'); do
  if [ -z "$fixture" ] || [ ! -f "$IMG_DIR/$fixture" ]; then
    echo "ERROR: expected fixture image $IMG_DIR/$fixture is missing." >&2
    exit 1
  fi
done

echo "==> Importing preview content (dry run gate)..."
$WP pgds import --file="$PREVIEW_DIR/preview-content.json" --batch=200 --dry-run

echo "==> Importing preview content..."
$WP pgds import --file="$PREVIEW_DIR/preview-content.json" --batch=200


attachment_id_by_key() {
  $WP post list --post_type=attachment --post_status=inherit --meta_key=_pgds_preview_attachment_key --meta_value="$1" --field=ID --posts_per_page=1
}

manifest_asset_field() {
  asset_id="$1"
  field="$2"
  $WP eval '$data = json_decode( file_get_contents( (string) getenv( "PGDS_PREVIEW_MANIFEST" ) ), true ); if ( ! is_array( $data ) ) { WP_CLI::error( "Invalid preview media manifest." ); } foreach ( (array) ( $data["assets"] ?? array() ) as $asset ) { if ( (string) ( $asset["id"] ?? "" ) === "'"$asset_id"'" ) { echo (string) ( $asset["'"$field"'"] ?? "" ); break; } }'
}

ensure_media_asset() {
  asset_id="$1"
  file="$2"
  existing=$(attachment_id_by_key "asset-$asset_id")

  if [ -n "$existing" ]; then
    printf '%s\n' "$existing"
    return
  fi

  title=$(manifest_asset_field "$asset_id" source_title)
  alt=$(manifest_asset_field "$asset_id" alt)
  source_url=$(manifest_asset_field "$asset_id" source_url)
  author=$(manifest_asset_field "$asset_id" author)
  license=$(manifest_asset_field "$asset_id" license)
  license_url=$(manifest_asset_field "$asset_id" license_url)
  caption="$author — $license"

  attachment=$($WP media import "$file" --title="$title" --alt="$alt" --caption="$caption" --porcelain)
  $WP post update "$attachment" --post_excerpt="$caption" --post_content="$source_url" >/dev/null
  $WP post meta update "$attachment" _pgds_preview_attachment_key "asset-$asset_id" >/dev/null
  $WP post meta update "$attachment" _pgds_preview_asset_id "$asset_id" >/dev/null
  $WP post meta update "$attachment" _pgds_preview_source_url "$source_url" >/dev/null
  $WP post meta update "$attachment" _pgds_preview_author "$author" >/dev/null
  $WP post meta update "$attachment" _pgds_preview_license "$license" >/dev/null
  $WP post meta update "$attachment" _pgds_preview_license_url "$license_url" >/dev/null
  printf '%s\n' "$attachment"
}

echo "==> Importing licensed fixtures into the WordPress Media Library..."
for asset_id in $($WP eval '$data = json_decode( file_get_contents( (string) getenv( "PGDS_PREVIEW_MANIFEST" ) ), true ); if ( ! is_array( $data ) ) { WP_CLI::error( "Invalid preview media manifest." ); } foreach ( (array) ( $data["assets"] ?? array() ) as $asset ) { echo sanitize_key( (string) ( $asset["id"] ?? "" ) ) . "\n"; }'); do
  fixture=$(manifest_asset_field "$asset_id" fixture)
  ensure_media_asset "$asset_id" "$IMG_DIR/$fixture" >/dev/null
done

echo "==> Assigning featured images through WordPress post metadata..."
$WP eval '
  $data = json_decode( file_get_contents( (string) getenv( "PGDS_PREVIEW_MANIFEST" ) ), true );
  if ( ! is_array( $data ) ) {
    WP_CLI::error( "Invalid preview media manifest." );
  }

  foreach ( (array) ( $data["assignments"]["featured"] ?? array() ) as $assignment ) {
    $posts = get_posts(
      array(
        "post_type"      => "post",
        "post_status"    => "any",
        "posts_per_page" => 1,
        "fields"         => "ids",
        "meta_key"       => "_pgds_source_id",
        "meta_value"     => (string) ( $assignment["source_id"] ?? "" ),
      )
    );
    if ( ! $posts ) {
      WP_CLI::warning( "Missing preview post: " . (string) ( $assignment["source_id"] ?? "" ) );
      continue;
    }

    $post_id  = (int) $posts[0];
    $asset_id = (string) ( $assignment["asset_id"] ?? "" );
    if ( "" === $asset_id ) {
      delete_post_thumbnail( $post_id );
      continue;
    }

    $attachments = get_posts(
      array(
        "post_type"      => "attachment",
        "post_status"    => "inherit",
        "posts_per_page" => 1,
        "fields"         => "ids",
        "meta_key"       => "_pgds_preview_asset_id",
        "meta_value"     => $asset_id,
      )
    );
    if ( ! $attachments ) {
      WP_CLI::error( "Missing imported preview asset: " . $asset_id );
    }

    $attachment_id = (int) $attachments[0];
    set_post_thumbnail( $post_id, $attachment_id );
    if ( 0 === (int) wp_get_post_parent_id( $attachment_id ) ) {
      wp_update_post( array( "ID" => $attachment_id, "post_parent" => $post_id ) );
    }
  }
'

echo "==> Assigning inline images through the WordPress editor content..."
$WP eval '
  $data = json_decode( file_get_contents( (string) getenv( "PGDS_PREVIEW_MANIFEST" ) ), true ); if ( ! is_array( $data ) ) { WP_CLI::error( "Invalid preview media manifest." ); }
  foreach ( (array) ( $data["assignments"]["inline_images"] ?? array() ) as $assignment ) {
    $posts = get_posts(
      array(
        "post_type"      => "post",
        "post_status"    => "any",
        "posts_per_page" => 1,
        "fields"         => "ids",
        "meta_key"       => "_pgds_source_id",
        "meta_value"     => (string) ( $assignment["source_id"] ?? "" ),
      )
    );
    if ( ! $posts ) {
      WP_CLI::warning( "Missing inline-image preview post: " . (string) ( $assignment["source_id"] ?? "" ) );
      continue;
    }

    $asset_id   = (string) ( $assignment["asset_id"] ?? "" );
    $attachments = get_posts(
      array(
        "post_type"      => "attachment",
        "post_status"    => "inherit",
        "posts_per_page" => 1,
        "fields"         => "ids",
        "meta_key"       => "_pgds_preview_asset_id",
        "meta_value"     => $asset_id,
      )
    );
    if ( ! $attachments ) {
      WP_CLI::error( "Missing inline preview asset: " . $asset_id );
    }

    $post_id       = (int) $posts[0];
    $attachment_id = (int) $attachments[0];
    $size          = sanitize_key( (string) ( $assignment["size"] ?? "large" ) );
    $image         = wp_get_attachment_image(
      $attachment_id,
      $size,
      false,
      array(
        "class"   => "wp-image-" . $attachment_id . " pgds-preview-inline-image",
        "loading" => "lazy",
      )
    );
    if ( ! $image ) {
      WP_CLI::error( "Could not render inline preview asset: " . $asset_id );
    }

    $caption     = wp_get_attachment_caption( $attachment_id );
    $block_attrs = wp_json_encode(
      array(
        "id"       => $attachment_id,
        "sizeSlug" => $size,
      )
    );
    $figure      = sprintf(
      "<!-- wp:image %s -->\n<figure class=\"wp-block-image size-%s\">%s%s</figure>\n<!-- /wp:image -->",
      $block_attrs,
      esc_attr( $size ),
      $image,
      $caption ? "<figcaption>" . esc_html( $caption ) . "</figcaption>" : ""
    );
    $content = (string) get_post_field( "post_content", $post_id );
    $count   = 0;
    $content = str_replace( "<!--pgds-preview-inline-image-->", $figure, $content, $count );
    if ( 0 === $count ) {
      $stored_id = (int) get_post_meta( $post_id, "_pgds_preview_inline_image_id", true );
      $block_ids = array();
      foreach ( parse_blocks( $content ) as $block ) {
        if ( "core/image" === (string) ( $block["blockName"] ?? "" ) && isset( $block["attrs"]["id"] ) ) {
          $block_ids[] = (int) $block["attrs"]["id"];
        }
      }
      if ( $stored_id !== $attachment_id || ! in_array( $attachment_id, $block_ids, true ) ) {
        WP_CLI::error(
          sprintf(
            "Expected one inline-image marker for %s, found none and no matching CMS image block.",
            (string) ( $assignment["source_id"] ?? "" )
          )
        );
      }
      continue;
    }
    if ( 1 !== $count ) {
      WP_CLI::error(
        sprintf(
          "Expected one inline-image marker for %s, found %d.",
          (string) ( $assignment["source_id"] ?? "" ),
          $count
        )
      );
    }

    wp_update_post( array( "ID" => $post_id, "post_content" => $content ) );
    update_post_meta( $post_id, "_pgds_preview_inline_image_id", $attachment_id );
  }
'

echo "==> Assigning gallery media through the WordPress editor content..."
$WP eval '
  $data = json_decode( file_get_contents( (string) getenv( "PGDS_PREVIEW_MANIFEST" ) ), true ); if ( ! is_array( $data ) ) { WP_CLI::error( "Invalid preview media manifest." ); }
  foreach ( (array) ( $data["assignments"]["galleries"] ?? array() ) as $gallery ) {
    $posts = get_posts(
      array(
        "post_type"      => "post",
        "post_status"    => "any",
        "posts_per_page" => 1,
        "fields"         => "ids",
        "meta_key"       => "_pgds_source_id",
        "meta_value"     => (string) ( $gallery["source_id"] ?? "" ),
      )
    );
    if ( ! $posts ) {
      WP_CLI::warning( "Missing gallery preview post: " . (string) ( $gallery["source_id"] ?? "" ) );
      continue;
    }

    $attachment_ids = array();
    foreach ( (array) ( $gallery["asset_ids"] ?? array() ) as $asset_id ) {
      $attachments = get_posts(
        array(
          "post_type"      => "attachment",
          "post_status"    => "inherit",
          "posts_per_page" => 1,
          "fields"         => "ids",
          "meta_key"       => "_pgds_preview_asset_id",
          "meta_value"     => (string) $asset_id,
        )
      );
      if ( ! $attachments ) {
        WP_CLI::error( "Missing gallery preview asset: " . (string) $asset_id );
      }
      $attachment_ids[] = (int) $attachments[0];
    }

    $post_id   = (int) $posts[0];
    $content   = (string) get_post_field( "post_content", $post_id );
    $content   = preg_replace( "/\\n*\\[gallery\\s+ids=\"[^\"]*\"[^\\]]*\\]\\s*$/", "", $content );
    $shortcode = sprintf(
      "[gallery ids=\"%s\" columns=\"%d\" size=\"%s\"]",
      implode( ",", $attachment_ids ),
      max( 1, (int) ( $gallery["columns"] ?? 2 ) ),
      sanitize_key( (string) ( $gallery["size"] ?? "large" ) )
    );
    wp_update_post( array( "ID" => $post_id, "post_content" => rtrim( $content ) . "\n\n" . $shortcode ) );
    update_post_meta( $post_id, "_pgds_preview_gallery_ids", implode( ",", $attachment_ids ) );
  }
'

echo "==> Assigning local video posters through WordPress post metadata..."
$WP eval '
  $data = json_decode( file_get_contents( (string) getenv( "PGDS_PREVIEW_MANIFEST" ) ), true ); if ( ! is_array( $data ) ) { WP_CLI::error( "Invalid preview media manifest." ); }
  foreach ( (array) ( $data["assignments"]["video_posters"] ?? array() ) as $assignment ) {
    $posts = get_posts(
      array(
        "post_type"      => "post",
        "post_status"    => "any",
        "posts_per_page" => 1,
        "fields"         => "ids",
        "meta_key"       => "_pgds_source_id",
        "meta_value"     => (string) ( $assignment["source_id"] ?? "" ),
      )
    );
    $attachments = get_posts(
      array(
        "post_type"      => "attachment",
        "post_status"    => "inherit",
        "posts_per_page" => 1,
        "fields"         => "ids",
        "meta_key"       => "_pgds_preview_asset_id",
        "meta_value"     => (string) ( $assignment["asset_id"] ?? "" ),
      )
    );
    if ( ! $posts || ! $attachments ) {
      WP_CLI::error( "Missing preview video post or local poster attachment." );
    }

    $post_id       = (int) $posts[0];
    $attachment_id = (int) $attachments[0];
    update_post_meta( $post_id, "_pgds_youtube_poster_id", $attachment_id );
    update_post_meta( $post_id, "_pgds_youtube_poster", (string) wp_get_attachment_image_url( $attachment_id, "pgds-lead" ) );
    if ( 0 === (int) wp_get_post_parent_id( $attachment_id ) ) {
      wp_update_post( array( "ID" => $attachment_id, "post_parent" => $post_id ) );
    }
  }
'

echo "==> Applying deterministic article metadata..."
$WP eval-file "$PREVIEW_DIR/seed-preview.php"

echo "==> Regenerating responsive image variants..."
$WP pgds media-variants --regenerate

$WP cache flush >/dev/null 2>&1 || true

echo ""
echo "==> PREVIEW DATASET READY. Open http://localhost:8080"
echo "    Media is editable in WordPress under Media > Library and in each post's featured, inline, and gallery content."
echo "    The dataset contains 20 posts for each editorial primary category and eight teaching entries."
echo "    Run $PREVIEW_DIR/verify.sh to assert the fixture contract."
