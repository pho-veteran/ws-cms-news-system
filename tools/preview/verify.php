<?php
/**
 * Verify the complete development-only preview corpus through WordPress APIs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$failures         = 0;
$content_path     = __DIR__ . '/preview-content.json';
$media_path       = __DIR__ . '/preview-media.json';
$teaching_path    = __DIR__ . '/preview-teachings.json';
$media_directory  = __DIR__ . '/media';
$origin_url       = untrailingslashit( (string) getenv( 'PGDS_PREVIEW_ORIGIN_URL' ) );
if ( '' === $origin_url ) {
	$origin_url = untrailingslashit( home_url() );
}
$expected_slugs   = array(
	'tin-phat-su',
	'song-an-lanh',
	'am-thuc-chay',
	'loi-song-xanh',
	'phat-tich',
	'tot-doi-dep-dao',
	'emagazine',
	'video',
	'vietnam-buddhism',
);
$expected_surfaces = array(
	'tin-phat-su'      => 'article',
	'song-an-lanh'     => 'article',
	'am-thuc-chay'     => 'article',
	'loi-song-xanh'    => 'article',
	'phat-tich'        => 'article',
	'tot-doi-dep-dao'  => 'article',
	'emagazine'        => 'emagazine',
	'video'             => 'video',
	'vietnam-buddhism' => 'vietnam-buddhism',
);

/**
 * Record a failed assertion.
 *
 * @param string $message Failure description.
 * @return void
 */
$fail = static function ( $message ) use ( &$failures ) {
	WP_CLI::warning( sprintf( 'FAIL: %s', $message ) );
	++$failures;
};

/**
 * Record an exact-count assertion.
 *
 * @param string $label    Assertion label.
 * @param int    $expected Expected count.
 * @param int    $actual   Actual count.
 * @return void
 */
$expect_count = static function ( $label, $expected, $actual ) use ( $fail ) {
	if ( $expected !== $actual ) {
		$fail( sprintf( '%s expected %d, got %d', $label, $expected, $actual ) );
		return;
	}

	WP_CLI::log( sprintf( 'PASS: %s = %d', $label, $actual ) );
};

/**
 * Fetch one local route without following redirects.
 *
 * @param string $url URL.
 * @return array|WP_Error
 */
$home_parts  = wp_parse_url( home_url() );
$request_host = (string) ( $home_parts['host'] ?? 'localhost' );
if ( ! empty( $home_parts['port'] ) ) {
	$request_host .= ':' . (int) $home_parts['port'];
}
$fetch = static function ( $url ) use ( $request_host ) {
	return wp_remote_get(
		$url,
		array(
			'redirection' => 0,
			'timeout'     => 20,
			'headers'     => array( 'Host' => $request_host ),
		)
	);
};

/**
 * Count Unicode words in rendered editorial copy.
 *
 * @param string $html HTML content.
 * @return int
 */
$word_count = static function ( $html ) {
	$text  = trim( wp_strip_all_tags( (string) $html ) );
	$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
	return is_array( $words ) ? count( $words ) : 0;
};

foreach ( array( $content_path, $media_path, $teaching_path ) as $dataset_path ) {
	if ( ! is_readable( $dataset_path ) ) {
		WP_CLI::error( sprintf( 'Preview dataset file is missing: %s', $dataset_path ) );
	}
}

$records        = json_decode( (string) file_get_contents( $content_path ), true );
$media_manifest = json_decode( (string) file_get_contents( $media_path ), true );
$teachings      = json_decode( (string) file_get_contents( $teaching_path ), true );
if ( ! is_array( $records ) || ! is_array( $media_manifest ) || ! is_array( $teachings ) ) {
	WP_CLI::error( 'A preview dataset file contains invalid JSON.' );
}

$manifest_assets      = (array) ( $media_manifest['assets'] ?? array() );
$featured_assignments = (array) ( $media_manifest['assignments']['featured'] ?? array() );
$inline_assignments   = (array) ( $media_manifest['assignments']['inline_images'] ?? array() );
$gallery_assignments  = (array) ( $media_manifest['assignments']['galleries'] ?? array() );
$poster_assignments   = (array) ( $media_manifest['assignments']['video_posters'] ?? array() );

WP_CLI::log( '==> Checking generated dataset files...' );
$expect_count( 'article records', 180, count( $records ) );
$expect_count( 'teaching records', 8, count( $teachings ) );
$expect_count( 'manifest media assets', 35, count( $manifest_assets ) );
$expect_count( 'manifest featured-image assignments', 180, count( $featured_assignments ) );
$expect_count( 'manifest inline-image assignments', 68, count( $inline_assignments ) );
$expect_count( 'manifest gallery assignments', 20, count( $gallery_assignments ) );
$expect_count( 'manifest video-poster assignments', 20, count( $poster_assignments ) );

$record_by_source = array();
$dataset_counts   = array_fill_keys( $expected_slugs, 0 );
foreach ( $records as $record ) {
	$source_id    = (string) ( $record['source_id'] ?? '' );
	$primary_slug = (string) ( $record['primary_cat'] ?? '' );
	if ( ! $source_id || isset( $record_by_source[ $source_id ] ) ) {
		$fail( sprintf( 'duplicate or missing source ID: %s', $source_id ) );
		continue;
	}
	$record_by_source[ $source_id ] = $record;
	if ( isset( $dataset_counts[ $primary_slug ] ) ) {
		++$dataset_counts[ $primary_slug ];
	} else {
		$fail( sprintf( 'unsupported primary category in dataset: %s', $primary_slug ) );
	}
	if ( 'media' === $primary_slug ) {
		$fail( 'media is used as a primary category' );
	}
	if ( count( array_unique( (array) ( $record['cats'] ?? array() ) ) ) !== count( (array) ( $record['cats'] ?? array() ) ) ) {
		$fail( sprintf( 'duplicate category assignment in %s', $source_id ) );
	}
}
$expect_count( 'unique article source IDs', 180, count( $record_by_source ) );
foreach ( $dataset_counts as $slug => $count ) {
	$expect_count( sprintf( '%s dataset records', $slug ), 20, $count );
}

$emagazine_titles   = array();
$emagazine_sapos    = array();
$emagazine_quotes   = array();
$emagazine_headings = array();
$emagazine_layouts  = array();
foreach ( $records as $record ) {
	if ( 'emagazine' !== (string) ( $record['primary_cat'] ?? '' ) ) {
		continue;
	}

	$body                 = (string) ( $record['body_html'] ?? '' );
	$emagazine_titles[]   = (string) ( $record['title'] ?? '' );
	$emagazine_sapos[]    = (string) ( $record['sapo'] ?? '' );
	if ( preg_match( '/pgds-emagazine-pull-quote.*?<p>(.*?)<\/p>/su', $body, $quote_match ) ) {
		$emagazine_quotes[] = wp_strip_all_tags( $quote_match[1] );
	}
	if ( preg_match_all( '/<h2 class="wp-block-heading">(.*?)<\/h2>/su', $body, $heading_matches ) ) {
		$emagazine_headings = array_merge( $emagazine_headings, $heading_matches[1] );
	}

	$layout_positions = array();
	foreach ( array( 'wide' => 'pgds-preview-emag-wide', 'gallery' => 'pgds-preview-emag-gallery', 'full' => 'pgds-preview-emag-full', 'quote' => 'pgds-emagazine-pull-quote' ) as $label => $marker ) {
		$layout_positions[ $label ] = strpos( $body, $marker );
	}
	asort( $layout_positions, SORT_NUMERIC );
	$emagazine_layouts[] = implode( '>', array_keys( $layout_positions ) );
}
$expect_count( 'unique E-magazine titles', 20, count( array_unique( $emagazine_titles ) ) );
$expect_count( 'unique E-magazine sapos', 20, count( array_unique( $emagazine_sapos ) ) );
$expect_count( 'unique E-magazine pull quotes', 20, count( array_unique( $emagazine_quotes ) ) );
$expect_count( 'unique E-magazine chapter headings', 60, count( array_unique( $emagazine_headings ) ) );
$expect_count( 'E-magazine media rhythm variants', 4, count( array_unique( $emagazine_layouts ) ) );

WP_CLI::log( '==> Checking imported article identity and classification...' );
$preview_ids = array_map(
	'intval',
	get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_pgds_source_id',
			'meta_value'     => 'preview-2026-',
			'meta_compare'   => 'LIKE',
		)
	)
);
$all_article_ids = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
$expect_count( 'imported preview articles', 180, count( $preview_ids ) );
$expect_count( 'all local articles after replacement', 180, count( $all_article_ids ) );

$preview_by_source       = array();
$imported_category_counts = array_fill_keys( $expected_slugs, 0 );
$invalid_classifications = 0;
$invalid_bodies          = 0;
$language_errors         = 0;
$missing_editorial_meta  = 0;
foreach ( $preview_ids as $post_id ) {
	$source_id = (string) get_post_meta( $post_id, '_pgds_source_id', true );
	$record    = $record_by_source[ $source_id ] ?? null;
	if ( ! is_array( $record ) ) {
		$fail( sprintf( 'imported article has no dataset record: %s', $source_id ) );
		continue;
	}
	$preview_by_source[ $source_id ] = $post_id;
	$primary_slug = (string) $record['primary_cat'];
	++$imported_category_counts[ $primary_slug ];
	$classification = pgds_get_editorial_classification( $post_id );
	if (
		'publish' !== get_post_status( $post_id ) ||
		empty( $classification['valid'] ) ||
		$expected_surfaces[ $primary_slug ] !== (string) $classification['surface'] ||
		$primary_slug !== (string) $classification['primary_slug']
	) {
		++$invalid_classifications;
	}

	$content    = (string) get_post_field( 'post_content', $post_id );
	$words      = $word_count( $content );
	$heading_count = substr_count( strtolower( $content ), '<h2' );
	$is_video   = 'video' === $primary_slug;
	$is_magazine = 'emagazine' === $primary_slug;
	$is_long_form_valid = $words >= ( $is_magazine ? 800 : 500 ) &&
		$heading_count >= 3 &&
		false !== stripos( $content, '<blockquote' ) &&
		false !== stripos( $content, '<ul' );
	if (
		$is_magazine &&
		(
			3 !== substr_count( $content, 'class="wp-block-group pgds-emagazine-chapter"' ) ||
			false === strpos( $content, 'pgds-emagazine-pull-quote' ) ||
			false === strpos( $content, 'pgds-emagazine-figure--full' ) ||
			false === strpos( $content, 'pgds-emagazine-image-pair' )
		)
	) {
		$is_long_form_valid = false;
	}
	$is_video_valid = $words >= 80 && $words <= 180 && $heading_count >= 1 &&
		false === stripos( $content, '<blockquote' ) && false === stripos( $content, '<ul' );
	if ( ( $is_video && ! $is_video_valid ) || ( ! $is_video && ! $is_long_form_valid ) ) {
		++$invalid_bodies;
	}
	if ( 'vietnam-buddhism' === $primary_slug ) {
		$editorial_content = preg_replace( '/<figure\b[^>]*>.*?<\/figure>/isu', '', $content );
		$english_fields = implode(
			' ',
			array(
				(string) get_the_title( $post_id ),
				(string) get_post_meta( $post_id, '_pgds_sapo', true ),
				(string) $editorial_content,
				(string) get_post_meta( $post_id, '_pgds_source', true ),
				(string) get_post_meta( $post_id, '_pgds_display_author', true ),
			)
		);
		if ( preg_match( '/[ăâđêôơưàáảãạằắẳẵặầấẩẫậèéẻẽẹềếểễệìíỉĩịòóỏõọồốổỗộờớởỡợùúủũụừứửữựỳýỷỹỵ]/iu', $english_fields ) ) {
			++$language_errors;
		}
	}
	if (
		'' === trim( (string) get_post_meta( $post_id, '_pgds_sapo', true ) ) ||
		'' === trim( (string) get_post_meta( $post_id, '_pgds_source', true ) ) ||
		'' === trim( (string) get_post_meta( $post_id, '_pgds_display_author', true ) )
	) {
		++$missing_editorial_meta;
	}
}
$expect_count( 'invalid or unpublished classifications', 0, $invalid_classifications );
$expect_count( 'articles below the rich-body contract', 0, $invalid_bodies );
$expect_count( 'Vietnam Buddhism language errors', 0, $language_errors );
$expect_count( 'articles missing editorial metadata', 0, $missing_editorial_meta );
foreach ( $imported_category_counts as $slug => $count ) {
	$expect_count( sprintf( '%s imported articles', $slug ), 20, $count );
}

WP_CLI::log( '==> Checking Media Library provenance and image files...' );
$attachment_by_asset = array();
$asset_errors        = 0;
foreach ( $manifest_assets as $asset ) {
	$asset_id = (string) ( $asset['id'] ?? '' );
	$matches  = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_pgds_preview_asset_id',
			'meta_value'     => $asset_id,
		)
	);
	if ( ! $asset_id || 1 !== count( $matches ) ) {
		++$asset_errors;
		continue;
	}
	$attachment_id                    = (int) $matches[0];
	$attachment_by_asset[ $asset_id ] = $attachment_id;
	$fixture_path                     = trailingslashit( $media_directory ) . sanitize_file_name( (string) ( $asset['fixture'] ?? '' ) );
	$file                             = (string) get_attached_file( $attachment_id );
	$image_meta                       = wp_get_attachment_metadata( $attachment_id );
	if (
		! is_readable( $fixture_path ) ||
		(string) ( $asset['sha256'] ?? '' ) !== hash_file( 'sha256', $fixture_path ) ||
		(string) ( $asset['source_url'] ?? '' ) !== get_post_meta( $attachment_id, '_pgds_preview_source_url', true ) ||
		(string) ( $asset['license'] ?? '' ) !== get_post_meta( $attachment_id, '_pgds_preview_license', true ) ||
		(string) ( $asset['alt'] ?? '' ) !== get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ||
		! $file || ! is_readable( $file ) ||
		'webp' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ||
		! is_array( $image_meta ) || empty( $image_meta['sizes'] )
	) {
		++$asset_errors;
	}
}
$expect_count( 'imported attributed Media Library assets', 35, count( $attachment_by_asset ) );
$expect_count( 'Media Library provenance or file errors', 0, $asset_errors );
$all_attachment_ids = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
$all_attachment_count = count( $all_attachment_ids );
if ( $all_attachment_count < count( $attachment_by_asset ) ) {
	$fail( sprintf( 'all local Media Library attachments expected at least %d, got %d', count( $attachment_by_asset ), $all_attachment_count ) );
} else {
	WP_CLI::log( sprintf( 'PASS: all local Media Library attachments = %d (including %d preview fixtures)', $all_attachment_count, count( $attachment_by_asset ) ) );
}

WP_CLI::log( '==> Checking featured, inline and gallery relationships...' );
$featured_errors = 0;
$english_media_errors = 0;
foreach ( $featured_assignments as $assignment ) {
	$source_id     = (string) ( $assignment['source_id'] ?? '' );
	$asset_id      = (string) ( $assignment['asset_id'] ?? '' );
	$post_id       = (int) ( $preview_by_source[ $source_id ] ?? 0 );
	$attachment_id = (int) ( $attachment_by_asset[ $asset_id ] ?? 0 );
	if ( ! $post_id || ! $attachment_id || $attachment_id !== (int) get_post_thumbnail_id( $post_id ) ) {
		++$featured_errors;
	}
	if ( false !== strpos( $source_id, '-vietnam-buddhism-' ) && ! str_ends_with( $asset_id, '-en' ) ) {
		++$english_media_errors;
	}
}
$expect_count( 'featured-image assignment errors', 0, $featured_errors );

$inline_errors = 0;
foreach ( $inline_assignments as $assignment ) {
	$source_id     = (string) ( $assignment['source_id'] ?? '' );
	$asset_id      = (string) ( $assignment['asset_id'] ?? '' );
	$post_id       = (int) ( $preview_by_source[ $source_id ] ?? 0 );
	$attachment_id = (int) ( $attachment_by_asset[ $asset_id ] ?? 0 );
	$content       = $post_id ? (string) get_post_field( 'post_content', $post_id ) : '';
	$stored_ids    = array_map( 'intval', explode( ',', (string) get_post_meta( $post_id, '_pgds_preview_inline_image_ids', true ) ) );
	if (
		! $post_id || ! $attachment_id ||
		! in_array( $attachment_id, $stored_ids, true ) ||
		false === strpos( $content, 'wp-image-' . $attachment_id ) ||
		false === strpos( $content, '<!-- wp:image ' )
	) {
		++$inline_errors;
	}
	if ( false !== strpos( $source_id, '-vietnam-buddhism-' ) && ! str_ends_with( $asset_id, '-en' ) ) {
		++$english_media_errors;
	}
}
$expect_count( 'inline-image assignment errors', 0, $inline_errors );
$expect_count( 'Vietnam Buddhism non-English media records', 0, $english_media_errors );

$unresolved_markers = 0;
foreach ( $preview_ids as $post_id ) {
	if ( preg_match( '/pgds-preview-(?:inline-image|emag-wide|emag-full|emag-gallery)-->/', (string) get_post_field( 'post_content', $post_id ) ) ) {
		++$unresolved_markers;
	}
}
$expect_count( 'unresolved inline-image markers', 0, $unresolved_markers );

$gallery_errors = 0;
foreach ( $gallery_assignments as $gallery ) {
	$post_id      = (int) ( $preview_by_source[ (string) ( $gallery['source_id'] ?? '' ) ] ?? 0 );
	$expected_ids = array();
	foreach ( (array) ( $gallery['asset_ids'] ?? array() ) as $asset_id ) {
		$expected_ids[] = (int) ( $attachment_by_asset[ (string) $asset_id ] ?? 0 );
	}
	$content    = $post_id ? (string) get_post_field( 'post_content', $post_id ) : '';
	$actual_ids = array();
	foreach ( parse_blocks( $content ) as $block ) {
		if ( 'core/gallery' !== (string) ( $block['blockName'] ?? '' ) ) {
			continue;
		}
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $image_block ) {
			if ( 'core/image' === (string) ( $image_block['blockName'] ?? '' ) && isset( $image_block['attrs']['id'] ) ) {
				$actual_ids[] = (int) $image_block['attrs']['id'];
			}
		}
		break;
	}
	if ( ! $post_id || in_array( 0, $expected_ids, true ) || ! $actual_ids ) {
		++$gallery_errors;
		continue;
	}
	$stored_ids = array_map( 'intval', explode( ',', (string) get_post_meta( $post_id, '_pgds_preview_gallery_ids', true ) ) );
	if ( $expected_ids !== $actual_ids || $expected_ids !== $stored_ids ) {
		++$gallery_errors;
	}
}
$expect_count( 'gallery assignment errors', 0, $gallery_errors );

WP_CLI::log( '==> Checking Video surface metadata...' );
$poster_errors = 0;
foreach ( $poster_assignments as $assignment ) {
	$post_id       = (int) ( $preview_by_source[ (string) ( $assignment['source_id'] ?? '' ) ] ?? 0 );
	$attachment_id = (int) ( $attachment_by_asset[ (string) ( $assignment['asset_id'] ?? '' ) ] ?? 0 );
	$youtube_id    = $post_id ? (string) get_post_meta( $post_id, '_pgds_youtube_id', true ) : '';
	if (
		! $post_id || ! $attachment_id ||
		! pgds_extract_youtube_id( $youtube_id ) ||
		$attachment_id !== (int) get_post_thumbnail_id( $post_id ) ||
		$attachment_id !== (int) get_post_meta( $post_id, '_pgds_youtube_poster_id', true ) ||
		! get_post_meta( $post_id, '_pgds_youtube_dur', true ) ||
		! get_post_meta( $post_id, '_pgds_youtube_title', true ) ||
		'1' === get_post_meta( $post_id, '_pgds_video_unavailable', true )
	) {
		++$poster_errors;
	}
}
$expect_count( 'Video metadata or poster errors', 0, $poster_errors );

WP_CLI::log( '==> Checking homepage curation...' );
$featured_ids = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_pgds_is_featured',
		'meta_value'     => '1',
	)
);
$featured_ranks = array_map(
	static function ( $post_id ) {
		return (int) get_post_meta( $post_id, '_pgds_feature_rank', true );
	},
	$featured_ids
);
sort( $featured_ranks );
$expect_count( 'homepage Featured articles', 4, count( $featured_ids ) );
if ( array( 1, 2, 3, 4 ) !== $featured_ranks ) {
	$fail( 'homepage Featured ranks are not exactly 1 through 4' );
} else {
	WP_CLI::log( 'PASS: homepage Featured ranks are exactly 1 through 4' );
}
$photo_story_ids = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_pgds_photo_story',
		'meta_value'     => '1',
	)
);
$expect_count( 'homepage Photo story articles', 6, count( $photo_story_ids ) );

WP_CLI::log( '==> Checking teaching fixtures...' );
$teaching_ids = get_posts(
	array(
		'post_type'      => 'pgds_teaching',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
$invalid_teachings = 0;
foreach ( $teaching_ids as $teaching_id ) {
	if (
		'publish' !== get_post_status( $teaching_id ) ||
		! get_post_meta( $teaching_id, '_pgds_preview_teaching_id', true ) ||
		$word_count( (string) get_post_field( 'post_content', $teaching_id ) ) < 150 ||
		! has_post_thumbnail( $teaching_id )
	) {
		++$invalid_teachings;
	}
}
$expect_count( 'published teaching fixtures', 8, count( $teaching_ids ) );
$expect_count( 'invalid teaching fixtures', 0, $invalid_teachings );

WP_CLI::log( '==> Checking reader-comment fixtures...' );
$all_reader_comments = get_comments(
	array(
		'status' => 'all',
		'type'   => 'comment',
	)
);
$fixture_reader_comments = get_comments(
	array(
		'status'   => 'all',
		'type'     => 'comment',
		'meta_key' => '_pgds_preview_comment_id',
	)
);
$pending_comments = array_filter(
	$all_reader_comments,
	static function ( $comment ) {
		return '0' === (string) $comment->comment_approved;
	}
);
$article_comment_id = (int) ( $preview_by_source['preview-2026-tin-phat-su-01'] ?? 0 );
$article_comments   = $article_comment_id
	? get_comments( array( 'post_id' => $article_comment_id, 'status' => 'approve', 'type' => 'comment' ) )
	: array();
$article_fixture_comments = $article_comment_id
	? get_comments( array( 'post_id' => $article_comment_id, 'status' => 'approve', 'type' => 'comment', 'meta_key' => '_pgds_preview_comment_id' ) )
	: array();
$expect_count( 'published reader-comment fixtures', 13, count( $fixture_reader_comments ) );
$expect_count( 'pending reader comments', 0, count( $pending_comments ) );
$expect_count( 'paginated Article comment fixtures', 12, count( $article_fixture_comments ) );
$expect_count( 'Article comment pages', 2, pgds_comment_page_count( $article_comments ) );
$expect_count( 'WordPress comment-page option enabled', 1, (int) (bool) get_option( 'page_comments' ) );
$expect_count( 'WordPress comments per page', 8, (int) get_option( 'comments_per_page' ) );

WP_CLI::log( '==> Smoke-checking representative frontend routes...' );
$route_errors = 0;
$homepage_response = $fetch( $origin_url . '/' );
$homepage_markup   = is_wp_error( $homepage_response ) ? '' : (string) wp_remote_retrieve_body( $homepage_response );
$escaped_logo_url  = str_replace( '/', '\\/', PGDS_LOGO_URI );
if (
	is_wp_error( $homepage_response ) ||
	200 !== (int) wp_remote_retrieve_response_code( $homepage_response ) ||
	false === strpos( $homepage_markup, 'src="' . esc_url( PGDS_LOGO_URI ) . '"' ) ||
	false === strpos( $homepage_markup, '"url":"' . $escaped_logo_url . '"' ) ||
	1 === preg_match( '#/wp-content/uploads/[^"\']*logo#i', $homepage_markup ) ||
	3 !== preg_match_all( '/class="pgds-photo-panel__dot(?: is-active)?"/', $homepage_markup ) ||
	8 !== substr_count( $homepage_markup, 'class="pgds-media-thumb"' ) ||
	2 !== substr_count( $homepage_markup, 'class="pgds-media-bullets"' ) ||
	5 !== substr_count( $homepage_markup, 'class="pgds-play' ) ||
	false === strpos( $homepage_markup, '>Vietnam Buddhism<' ) ||
	false === strpos( $homepage_markup, '>View more<' ) ||
	false === strpos( $homepage_markup, 'Chuyên trang tin điện tử — tin tức, đời sống và văn hóa Phật giáo.' ) ||
	false === strpos( $homepage_markup, '© ' . date_i18n( 'Y' ) . ' ' . get_bloginfo( 'name' ) ) ||
	false !== strpos( $homepage_markup, 'Bản quyền thuộc về toà soạn' )
) {
	WP_CLI::warning( 'Homepage route contract failed during preview verification.' );
	++$route_errors;
}
$sample_source_ids = array(
	'preview-2026-tin-phat-su-01',
	'preview-2026-emagazine-01',
	'preview-2026-video-01',
	'preview-2026-vietnam-buddhism-01',
);
foreach ( $sample_source_ids as $source_id ) {
	$post_id  = (int) ( $preview_by_source[ $source_id ] ?? 0 );
	$url      = $post_id ? $origin_url . wp_make_link_relative( get_permalink( $post_id ) ) : '';
	$response = $url ? $fetch( $url ) : new WP_Error( 'missing_fixture' );
	$markup   = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) || '' === $markup ) {
		WP_CLI::warning( sprintf( 'Base route failed for %1$s: %2$s', $source_id, $url ) );
		++$route_errors;
	}
	$expected_footer_intro = 'preview-2026-vietnam-buddhism-01' === $source_id
		? 'A Buddhist news, culture, and lifestyle publication.'
		: 'Chuyên trang tin điện tử — tin tức, đời sống và văn hóa Phật giáo.';
	if (
		false === strpos( $markup, 'class="pgds-footer block"' ) ||
		false === strpos( $markup, $expected_footer_intro ) ||
		false === strpos( $markup, '© ' . date_i18n( 'Y' ) . ' ' . get_bloginfo( 'name' ) ) ||
		false !== strpos( $markup, 'Bản quyền thuộc về toà soạn' )
	) {
		WP_CLI::warning( sprintf( 'Shared footer contract failed for %s.', $source_id ) );
		++$route_errors;
	}
	if ( 'preview-2026-video-01' === $source_id && false === strpos( $markup, 'data-pgds="youtube-facade"' ) ) {
		WP_CLI::warning( 'Video route omitted the YouTube facade.' );
		++$route_errors;
	}
	if ( 'preview-2026-emagazine-01' === $source_id ) {
		if (
			false === strpos( $markup, 'class="pgds-emagazine-header"' ) ||
			false === strpos( $markup, 'class="pgds-emagazine"' ) ||
			! preg_match( '/class="[^"]*pgds-emagazine-chapter__number[^"]*">01</', $markup ) ||
			false === strpos( $markup, 'pgds-emagazine-figure--full' ) ||
			false === strpos( $markup, 'pgds-emagazine-image-pair' ) ||
			false === strpos( $markup, 'class="pgds-comments__form-wrap comment-box"' ) ||
			4 !== substr_count( $markup, 'class="pgds-emagazine-card"' ) ||
			false !== strpos( $markup, 'data-pgds="primary-nav"' ) ||
			false !== strpos( $markup, 'pgds-breadcrumb' ) ||
			false !== strpos( $markup, 'role="complementary"' )
		) {
			WP_CLI::warning( 'E-magazine route omitted or added an element outside its layout contract.' );
			++$route_errors;
		}
	}
	if ( 'preview-2026-vietnam-buddhism-01' === $source_id && ( false === strpos( $markup, '<html lang="en">' ) || false === strpos( $markup, 'PGDS preview editorial team' ) ) ) {
		WP_CLI::warning( 'Vietnam Buddhism route omitted its English document or display-author contract.' );
		++$route_errors;
	}
	if ( 'preview-2026-vietnam-buddhism-01' === $source_id ) {
		if (
			1 !== substr_count( $markup, 'PGDS preview editorial team' ) ||
			false === strpos( $markup, '>Comments<' ) ||
			false !== strpos( $markup, 'aria-label="Reply to ' )
		) {
			WP_CLI::warning( 'Vietnam Buddhism route failed its single-author or English comment contract.' );
			++$route_errors;
		}
	}
	if ( 'preview-2026-tin-phat-su-01' === $source_id ) {
		$form_position = strpos( $markup, 'class="pgds-comments__form-wrap comment-box"' );
		$list_position = strpos( $markup, 'class="pgds-comments__list"' );
		if (
			1 !== substr_count( $markup, 'Ban biên tập dữ liệu preview PGDS' ) ||
			8 !== substr_count( $markup, 'class="pgds-comment__card"' ) ||
			false === $form_position ||
			false === $list_position ||
			$form_position > $list_position ||
			! preg_match( '/(?:cpage=2|comment-page-2)/', $markup ) ||
			false !== strpos( $markup, 'aria-label="Trả lời ' ) ||
			false !== strpos( $markup, 'data-pgds="comment-reply"' ) ||
			false !== strpos( $markup, '<ol class="pgds-comments__list"' ) ||
			false !== strpos( $markup, 'pgds-comment__avatar' ) ||
			false !== strpos( $markup, 'says:' ) ||
			false !== strpos( $markup, 'awaiting moderation' )
		) {
			++$route_errors;
		}
	}
}
foreach ( $expected_slugs as $slug ) {
	$term     = get_category_by_slug( $slug );
	$term_url = $term instanceof WP_Term ? get_term_link( $term ) : new WP_Error( 'missing_term' );
	$url      = is_wp_error( $term_url ) ? $term_url : $origin_url . wp_make_link_relative( $term_url );
	$response = is_wp_error( $url ) ? $url : $fetch( $url );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		++$route_errors;
	}
}

$comment_page_two_url      = $article_comment_id
	? trailingslashit( $origin_url . wp_make_link_relative( get_permalink( $article_comment_id ) ) ) . 'comment-page-2/'
	: '';
$comment_page_two_response = $comment_page_two_url ? $fetch( $comment_page_two_url ) : new WP_Error( 'missing_comment_fixture' );
$comment_page_two_markup   = is_wp_error( $comment_page_two_response ) ? '' : (string) wp_remote_retrieve_body( $comment_page_two_response );
if (
	is_wp_error( $comment_page_two_response ) ||
	200 !== (int) wp_remote_retrieve_response_code( $comment_page_two_response ) ||
	4 !== substr_count( $comment_page_two_markup, 'class="pgds-comment__card"' ) ||
	! preg_match( '/<span[^>]*aria-current="page"[^>]*class="page-numbers current"[^>]*>2<\/span>/', $comment_page_two_markup )
) {
	WP_CLI::warning(
		sprintf(
			'Comment page-two route contract failed: url=%1$s status=%2$d cards=%3$d active=%4$d',
			$comment_page_two_url,
			is_wp_error( $comment_page_two_response ) ? 0 : (int) wp_remote_retrieve_response_code( $comment_page_two_response ),
			substr_count( $comment_page_two_markup, 'class="pgds-comment__card"' ),
			preg_match( '/<span[^>]*aria-current="page"[^>]*class="page-numbers current"[^>]*>2<\/span>/', $comment_page_two_markup )
		)
	);
	++$route_errors;
}
$expect_count( 'representative frontend route errors', 0, $route_errors );

if ( $failures ) {
	WP_CLI::error( sprintf( 'Preview verification failed with %d issue(s).', $failures ) );
}

WP_CLI::success( 'Preview dataset verification passed.' );
