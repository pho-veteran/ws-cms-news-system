<?php
/**
 * Verify the development-only preview article fixtures through WordPress APIs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$failures       = 0;
$preview_prefix = 'preview-2026-';
$manifest_path  = __DIR__ . '/preview-media.json';
$media_directory = __DIR__ . '/media';
$origin_url      = untrailingslashit( (string) getenv( 'PGDS_PREVIEW_ORIGIN_URL' ) );
if ( '' === $origin_url ) {
	$origin_url = untrailingslashit( home_url() );
}

/**
 * Record a failed fixture assertion.
 *
 * @param string $message Failure description.
 * @return void
 */
$fail = static function ( $message ) use ( &$failures ) {
	WP_CLI::warning( sprintf( 'FAIL: %s', $message ) );
	++$failures;
};

/**
 * Build an origin URL for a local preview post.
 *
 * @param int    $post_id Post ID.
 * @param string $origin  Origin base URL.
 * @return string
 */
$preview_url = static function ( $post_id, $origin ) {
	return $origin . wp_make_link_relative( get_permalink( $post_id ) );
};

/**
 * Build an origin URL for a local category archive.
 *
 * @param WP_Term $term   Category term.
 * @param string  $origin Origin base URL.
 * @return string
 */
$category_url = static function ( $term, $origin ) {
	$link = get_term_link( $term );
	return is_wp_error( $link ) ? '' : $origin . wp_make_link_relative( $link );
};

/**
 * Fetch a local preview page without following redirects.
 *
 * @param string $url  Preview URL.
 * @param string $host HTTP Host header.
 * @return array|WP_Error
 */
$fetch_preview = static function ( $url, $host ) {
	return wp_remote_get(
		$url,
		array(
			'headers'     => array( 'Host' => $host ),
			'redirection' => 0,
		)
	);
};

/**
 * Assert an exact count.
 *
 * @param string $label    Assertion label.
 * @param int    $expected Expected count.
 * @param int    $actual   Actual count.
 * @return void
 */
$expect_count = static function ( $label, $expected, $actual ) use ( $fail ) {
	if ( $actual !== $expected ) {
		$fail( sprintf( '%s expected %d, got %d', $label, $expected, $actual ) );
		return;
	}

	WP_CLI::log( sprintf( 'PASS: %s = %d', $label, $actual ) );
};

/**
 * Resolve preview posts with a stable source-ID prefix.
 *
 * @return int[]
 */
$get_preview_ids = static function () use ( $preview_prefix ) {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_pgds_source_id',
				'meta_value'     => $preview_prefix,
				'meta_compare'   => 'LIKE',
			)
		)
	);
};

/**
 * Count preview posts with a matching metadata value.
 *
 * @param int[]  $post_ids Preview post IDs.
 * @param string $key      Metadata key.
 * @param mixed  $value    Expected value, or null for any stored value.
 * @return int
 */
$count_post_meta = static function ( $post_ids, $key, $value = null ) {
	$count = 0;
	foreach ( $post_ids as $post_id ) {
		if ( ! metadata_exists( 'post', $post_id, $key ) ) {
			continue;
		}

		$actual = get_post_meta( $post_id, $key, true );
		if ( null === $value || (string) $actual === (string) $value ) {
			++$count;
		}
	}

	return $count;
};

if ( ! is_readable( $manifest_path ) ) {
	WP_CLI::error( sprintf( 'Preview media manifest is missing: %s', $manifest_path ) );
}

$media_manifest = json_decode( file_get_contents( $manifest_path ), true );
if ( ! is_array( $media_manifest ) ) {
	WP_CLI::error( 'Preview media manifest is invalid JSON.' );
}

$manifest_assets      = (array) ( $media_manifest['assets'] ?? array() );
$featured_assignments = (array) ( $media_manifest['assignments']['featured'] ?? array() );
$inline_assignments   = (array) ( $media_manifest['assignments']['inline_images'] ?? array() );
$gallery_assignments = (array) ( $media_manifest['assignments']['galleries'] ?? array() );
$poster_assignments  = (array) ( $media_manifest['assignments']['video_posters'] ?? array() );

WP_CLI::log( '==> Checking preview article identity...' );
$preview_ids   = $get_preview_ids();
$published_ids = array_values(
	array_filter(
		$preview_ids,
		static function ( $post_id ) {
			return 'publish' === get_post_status( $post_id );
		}
	)
);
$source_ids    = array_map(
	static function ( $post_id ) {
		return (string) get_post_meta( $post_id, '_pgds_source_id', true );
	},
	$preview_ids
);
$expect_count( 'preview posts', 40, count( $preview_ids ) );
$expect_count( 'published preview posts', 40, count( $published_ids ) );
$expect_count( 'unique preview source IDs', 40, count( array_unique( $source_ids ) ) );

$preview_by_source = array();
foreach ( $preview_ids as $post_id ) {
	$preview_by_source[ (string) get_post_meta( $post_id, '_pgds_source_id', true ) ] = $post_id;
}

WP_CLI::log( '==> Checking rich article bodies...' );
$short_bodies = 0;
foreach ( $preview_ids as $post_id ) {
	$content    = (string) get_post_field( 'post_content', $post_id );
	$word_count = str_word_count( wp_strip_all_tags( $content ) );
	if (
		$word_count < 300 ||
		substr_count( strtolower( $content ), '<h2' ) < 2 ||
		false === stripos( $content, '<blockquote' ) ||
		false === stripos( $content, '<ul' )
	) {
		++$short_bodies;
	}
}
$expect_count( 'preview posts missing the rich-body contract', 0, $short_bodies );

WP_CLI::log( '==> Checking CMS-managed Media Library assets...' );
$expect_count( 'manifest media assets', 25, count( $manifest_assets ) );
$asset_ids               = array();
$attachment_by_asset     = array();
$invalid_asset_meta      = 0;
$invalid_source_fixtures = 0;
$invalid_image_files     = 0;
$missing_image_sizes     = 0;
$duplicate_asset_keys    = 0;

foreach ( $manifest_assets as $asset ) {
	$asset_id = (string) ( $asset['id'] ?? '' );
	if ( '' === $asset_id || isset( $asset_ids[ $asset_id ] ) ) {
		++$duplicate_asset_keys;
		continue;
	}
	$asset_ids[ $asset_id ] = true;

	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_pgds_preview_asset_id',
			'meta_value'     => $asset_id,
		)
	);
	if ( 1 !== count( $attachments ) ) {
		++$duplicate_asset_keys;
		continue;
	}

	$attachment_id                    = (int) $attachments[0];
	$attachment_by_asset[ $asset_id ] = $attachment_id;
	$expected_license_url             = (string) ( $asset['license_url'] ?? '' );
	$actual_license_url               = (string) get_post_meta( $attachment_id, '_pgds_preview_license_url', true );
	$expected_alt                     = (string) ( $asset['alt'] ?? '' );
	$actual_alt                       = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

	if (
		'asset-' . $asset_id !== get_post_meta( $attachment_id, '_pgds_preview_attachment_key', true ) ||
		(string) ( $asset['source_url'] ?? '' ) !== get_post_meta( $attachment_id, '_pgds_preview_source_url', true ) ||
		(string) ( $asset['author'] ?? '' ) !== get_post_meta( $attachment_id, '_pgds_preview_author', true ) ||
		(string) ( $asset['license'] ?? '' ) !== get_post_meta( $attachment_id, '_pgds_preview_license', true ) ||
		$expected_license_url !== $actual_license_url ||
		$expected_alt !== $actual_alt
	) {
		++$invalid_asset_meta;
	}

	$source_fixture  = trailingslashit( $media_directory ) . sanitize_file_name( (string) ( $asset['fixture'] ?? '' ) );
	$source_checksum = is_readable( $source_fixture ) ? hash_file( 'sha256', $source_fixture ) : false;
	if (
		'jpg' !== strtolower( pathinfo( $source_fixture, PATHINFO_EXTENSION ) ) ||
		(string) ( $asset['sha256'] ?? '' ) !== $source_checksum
	) {
		++$invalid_source_fixtures;
	}

	$file       = (string) get_attached_file( $attachment_id );
	$image_meta = wp_get_attachment_metadata( $attachment_id );
	$upload_dir = wp_get_upload_dir();
	$original   = '';
	if ( is_array( $image_meta ) && ! empty( $image_meta['original_image'] ) && $file ) {
		$original = trailingslashit( dirname( $file ) ) . wp_basename( (string) $image_meta['original_image'] );
	}

	if (
		'image/jpeg' !== get_post_mime_type( $attachment_id ) ||
		! $file ||
		! is_readable( $file ) ||
		'webp' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ||
		'image/webp' !== wp_get_image_mime( $file ) ||
		! is_array( $image_meta ) ||
		empty( $image_meta['original_image'] ) ||
		'jpg' !== strtolower( pathinfo( (string) $image_meta['original_image'], PATHINFO_EXTENSION ) ) ||
		! $original ||
		! is_readable( $original ) ||
		'image/jpeg' !== wp_get_image_mime( $original ) ||
		0 !== strpos( wp_normalize_path( $file ), trailingslashit( wp_normalize_path( (string) $upload_dir['basedir'] ) ) )
	) {
		++$invalid_image_files;
	}

	if ( ! is_array( $image_meta ) || empty( $image_meta['width'] ) || empty( $image_meta['height'] ) || empty( $image_meta['sizes'] ) ) {
		++$missing_image_sizes;
		continue;
	}

	foreach ( $image_meta['sizes'] as $size ) {
		$size_file = ! empty( $size['file'] ) && $file ? trailingslashit( dirname( $file ) ) . wp_basename( (string) $size['file'] ) : '';
		if (
			! $size_file ||
			! is_readable( $size_file ) ||
			'webp' !== strtolower( pathinfo( $size_file, PATHINFO_EXTENSION ) ) ||
			'image/webp' !== (string) ( $size['mime-type'] ?? '' ) ||
			'image/webp' !== wp_get_image_mime( $size_file )
		) {
			++$missing_image_sizes;
			break;
		}
	}
}

$expect_count( 'unique manifest media asset IDs', 25, count( $asset_ids ) );
$expect_count( 'imported Media Library assets', 25, count( $attachment_by_asset ) );
$expect_count( 'missing or duplicate Media Library asset keys', 0, $duplicate_asset_keys );
$expect_count( 'Media Library assets with invalid provenance metadata', 0, $invalid_asset_meta );
$expect_count( 'source JPEG fixtures with invalid checksums', 0, $invalid_source_fixtures );
$expect_count( 'Media Library assets outside the JPEG-to-WebP contract', 0, $invalid_image_files );
$expect_count( 'Media Library assets without valid WebP variants', 0, $missing_image_sizes );


WP_CLI::log( '==> Checking editable featured-image assignments...' );
$expect_count( 'manifest featured-image assignments', 40, count( $featured_assignments ) );
$assignment_errors = 0;
$assigned_images   = 0;
foreach ( $featured_assignments as $assignment ) {
	$source_id = (string) ( $assignment['source_id'] ?? '' );
	$asset_id  = (string) ( $assignment['asset_id'] ?? '' );
	$post_id   = $preview_by_source[ $source_id ] ?? 0;
	if ( ! $post_id ) {
		++$assignment_errors;
		continue;
	}

	$actual_thumbnail = (int) get_post_thumbnail_id( $post_id );
	if ( '' === $asset_id ) {
		if ( 0 !== $actual_thumbnail ) {
			++$assignment_errors;
		}
		continue;
	}

	++$assigned_images;
	$expected_thumbnail = (int) ( $attachment_by_asset[ $asset_id ] ?? 0 );
	if ( ! $expected_thumbnail || $actual_thumbnail !== $expected_thumbnail ) {
		++$assignment_errors;
	}
}
$expect_count( 'CMS featured-image assignments', 39, $assigned_images );
$expect_count( 'featured-image assignment mismatches', 0, $assignment_errors );

WP_CLI::log( '==> Checking editable inline-image assignments...' );
$expect_count( 'manifest inline-image assignments', 7, count( $inline_assignments ) );
$inline_errors = 0;
foreach ( $inline_assignments as $assignment ) {
	$source_id     = (string) ( $assignment['source_id'] ?? '' );
	$asset_id      = (string) ( $assignment['asset_id'] ?? '' );
	$post_id       = $preview_by_source[ $source_id ] ?? 0;
	$attachment_id = (int) ( $attachment_by_asset[ $asset_id ] ?? 0 );
	if ( ! $post_id || ! $attachment_id ) {
		++$inline_errors;
		continue;
	}

	$content    = (string) get_post_field( 'post_content', $post_id );
	$stored_id  = (int) get_post_meta( $post_id, '_pgds_preview_inline_image_id', true );
	$size       = sanitize_key( (string) ( $assignment['size'] ?? 'large' ) );
	$image_url  = wp_get_attachment_image_url( $attachment_id, $size );
	$block_ids  = array();
	foreach ( parse_blocks( $content ) as $block ) {
		if ( 'core/image' === (string) ( $block['blockName'] ?? '' ) && isset( $block['attrs']['id'] ) ) {
			$block_ids[] = (int) $block['attrs']['id'];
		}
	}
	if (
		$stored_id !== $attachment_id ||
		! $image_url ||
		false === strpos( $content, 'wp-image-' . $attachment_id ) ||
		false === strpos( $content, 'pgds-preview-inline-image' ) ||
		false === strpos( $content, $image_url ) ||
		false === strpos( $content, '<!-- wp:image ' ) ||
		false === strpos( $content, '<!-- /wp:image -->' ) ||
		! in_array( $attachment_id, $block_ids, true ) ||
		false !== strpos( $content, 'pgds-preview-inline-image-->' )
	) {
		++$inline_errors;
	}
}
$expect_count( 'inline-image assignment mismatches', 0, $inline_errors );

$abstract_body_markers = 0;
foreach ( $preview_ids as $post_id ) {
	$content = (string) get_post_field( 'post_content', $post_id );
	if (
		false !== strpos( $content, 'pgds-preview-figure__art' ) ||
		false !== strpos( $content, 'Minh họa trừu tượng' ) ||
		false !== strpos( $content, 'pgds-preview-inline-image-->' )
	) {
		++$abstract_body_markers;
	}
}
$expect_count( 'legacy abstract or unresolved inline-image markers', 0, $abstract_body_markers );

$no_image_post = $preview_by_source['preview-2026-0026'] ?? 0;
if (
	! $no_image_post ||
	has_post_thumbnail( $no_image_post ) ||
	metadata_exists( 'post', $no_image_post, '_pgds_preview_inline_image_id' ) ||
	preg_match( '/<(?:img|figure)\\b/i', (string) get_post_field( 'post_content', $no_image_post ) )
) {
	$fail( 'intentional no-image fixture contains CMS-managed or inline media' );
} else {
	WP_CLI::log( 'PASS: intentional no-image fixture contains no article imagery' );
}

WP_CLI::log( '==> Checking editable gallery assignments...' );
$expect_count( 'manifest gallery assignments', 1, count( $gallery_assignments ) );
$gallery_errors = 0;
foreach ( $gallery_assignments as $gallery ) {
	$source_id = (string) ( $gallery['source_id'] ?? '' );
	$post_id   = $preview_by_source[ $source_id ] ?? 0;
	if ( ! $post_id ) {
		++$gallery_errors;
		continue;
	}

	$expected_ids = array();
	foreach ( (array) ( $gallery['asset_ids'] ?? array() ) as $asset_id ) {
		if ( empty( $attachment_by_asset[ $asset_id ] ) ) {
			++$gallery_errors;
			continue 2;
		}
		$expected_ids[] = (int) $attachment_by_asset[ $asset_id ];
	}

	$content = (string) get_post_field( 'post_content', $post_id );
	if ( ! preg_match( '/\[gallery\s+ids="([0-9,]+)"[^\]]*\]/', $content, $gallery_match ) ) {
		++$gallery_errors;
		continue;
	}
	$actual_ids = array_map( 'intval', explode( ',', $gallery_match[1] ) );
	$stored_ids = array_map( 'intval', explode( ',', (string) get_post_meta( $post_id, '_pgds_preview_gallery_ids', true ) ) );
	if ( $expected_ids !== $actual_ids || $expected_ids !== $stored_ids || str_word_count( wp_strip_all_tags( $content ) ) < 600 ) {
		++$gallery_errors;
	}
}
$expect_count( 'gallery assignment mismatches', 0, $gallery_errors );

WP_CLI::log( '==> Checking frozen video states...' );
$home_parts  = wp_parse_url( home_url() );
$host_header = (string) ( $home_parts['host'] ?? '' );
if ( isset( $home_parts['port'] ) ) {
	$host_header .= ':' . (int) $home_parts['port'];
}

$video_fixtures = array(
	'preview-2026-0008' => array( 'id' => 'A20Lx3sv8hs', 'duration' => 187 ),
	'preview-2026-0012' => array( 'id' => 'X3iS2L0Y_xw', 'duration' => 473 ),
	'preview-2026-0019' => array( 'id' => '2ZSW6vPbEjo', 'duration' => 1000 ),
	'preview-2026-0039' => array( 'id' => 'fTcmiAD_GwQ', 'duration' => 276 ),
);
$poster_by_source = array();
$poster_errors    = 0;
foreach ( $poster_assignments as $assignment ) {
	$source_id     = (string) ( $assignment['source_id'] ?? '' );
	$asset_id      = (string) ( $assignment['asset_id'] ?? '' );
	$attachment_id = (int) ( $attachment_by_asset[ $asset_id ] ?? 0 );
	if ( ! isset( $video_fixtures[ $source_id ] ) || ! $attachment_id ) {
		++$poster_errors;
		continue;
	}
	$poster_by_source[ $source_id ] = $attachment_id;
}

$expect_count( 'manifest video-poster assignments', 4, count( $poster_assignments ) );
foreach ( $video_fixtures as $source_id => $video_fixture ) {
	$post_id       = (int) ( $preview_by_source[ $source_id ] ?? 0 );
	$attachment_id = (int) ( $poster_by_source[ $source_id ] ?? 0 );
	if (
		! $post_id ||
		! $attachment_id ||
		(string) $video_fixture['id'] !== get_post_meta( $post_id, '_pgds_youtube_id', true ) ||
		(int) $video_fixture['duration'] !== (int) get_post_meta( $post_id, '_pgds_youtube_dur', true ) ||
		$attachment_id !== (int) get_post_meta( $post_id, '_pgds_youtube_poster_id', true ) ||
		$attachment_id === (int) get_post_thumbnail_id( $post_id ) ||
		(string) wp_get_attachment_image_url( $attachment_id, 'pgds-lead' ) !== get_post_meta( $post_id, '_pgds_youtube_poster', true ) ||
		'1' === get_post_meta( $post_id, '_pgds_video_unavailable', true )
	) {
		++$poster_errors;
	}
}
$expect_count( 'video fixture metadata or poster mismatches', 0, $poster_errors );

$available_video = $preview_by_source['preview-2026-0012'] ?? 0;
if ( ! $available_video || '1' === get_post_meta( $available_video, '_pgds_video_unavailable', true ) ) {
	$fail( 'available video fixture is missing or marked unavailable' );
} else {
	$available_url      = $preview_url( $available_video, $origin_url );
	$available_response = $fetch_preview( $available_url, $host_header );
	$available_markup   = is_wp_error( $available_response ) ? '' : (string) wp_remote_retrieve_body( $available_response );
	if (
		is_wp_error( $available_response ) ||
		200 !== (int) wp_remote_retrieve_response_code( $available_response ) ||
		false === strpos( $available_markup, 'data-pgds="youtube-facade"' ) ||
		false !== strpos( $available_markup, '<iframe' ) ||
		false !== strpos( $available_markup, 'youtube.com/embed' ) ||
		false !== strpos( $available_markup, 'youtube-nocookie.com/embed' )
	) {
		$fail( 'available video route did not render as a network-lazy facade' );
	} else {
		WP_CLI::log( 'PASS: available video route renders a network-lazy facade' );
	}
}

$unavailable_video = $preview_by_source['preview-2026-0025'] ?? 0;
if ( ! $unavailable_video || '1' !== get_post_meta( $unavailable_video, '_pgds_video_unavailable', true ) ) {
	$fail( 'unavailable video fixture is missing its unavailable marker' );
} else {
	$unavailable_url      = $preview_url( $unavailable_video, $origin_url );
	$unavailable_response = $fetch_preview( $unavailable_url, $host_header );
	$unavailable_markup   = is_wp_error( $unavailable_response ) ? '' : (string) wp_remote_retrieve_body( $unavailable_response );
	if (
		is_wp_error( $unavailable_response ) ||
		200 !== (int) wp_remote_retrieve_response_code( $unavailable_response ) ||
		false !== strpos( $unavailable_markup, 'data-pgds="youtube-facade"' ) ||
		false !== strpos( $unavailable_markup, '<iframe' ) ||
		false !== strpos( $unavailable_markup, 'youtube.com/embed' ) ||
		false !== strpos( $unavailable_markup, 'youtube-nocookie.com/embed' )
	) {
		$fail( 'unavailable video route exposed an unavailable remote embed' );
	} else {
		WP_CLI::log( 'PASS: unavailable video route hides its remote embed' );
	}
}

WP_CLI::log( '==> Checking Vietnam Buddhism English reader presentation...' );
$english_detail = $preview_by_source['preview-2026-0026'] ?? 0;
$article_detail = $preview_by_source['preview-2026-0001'] ?? 0;
if ( ! $english_detail || ! $article_detail ) {
	$fail( 'English or standard Article preview fixture is missing' );
} else {
	$english_category = get_category_by_slug( 'vietnam-buddhism' );
	$article_category = get_category_by_slug( 'tin-phat-su' );
	$english_url      = $preview_url( $english_detail, $origin_url );
	$english_response = $fetch_preview( $english_url, $host_header );
	$english_markup   = is_wp_error( $english_response ) ? '' : (string) wp_remote_retrieve_body( $english_response );
	$english_required = array(
		'<html lang="en">',
		'Skip to content',
		'aria-label="Sections"',
		'>Home</a>',
		'>Vietnam Buddhism</a>',
		'>Search news</label>',
		'aria-label="Breadcrumb"',
		'Source: PGDS preview fixture',
		'Comments',
		'Post comment',
		'placeholder="Write your comment…"',
		'aria-label="Sidebar"',
		'>Most read<',
		'>Perpetual Calendar<',
		'>Gregorian Calendar<',
		'>Lunar Calendar<',
		'>Related articles<',
		'>Contact<',
		'All rights reserved.',
		'Bài viết không ảnh để kiểm tra khung dự phòng',
		'Fixture này chủ động không có thumbnail để kiểm tra tỷ lệ khung, khả năng đọc và bố cục khi ảnh bị thiếu.',
		'Ban biên tập kiểm thử preview',
		'Độc giả preview',
		'Nội dung bình luận do CMS quản lý phải được giữ nguyên tiếng Việt.',
	);
	$english_forbidden = array(
		'Bỏ qua tới nội dung',
		'aria-label="Chuyên mục"',
		'>Trang chủ</a>',
		'aria-label="Tìm kiếm tin tức"',
		'aria-label="Đường dẫn trang"',
		'id="pgds-comments-title">
			Bình luận',
		'>Gửi bình luận<',
		'>Đọc nhiều<',
		'>Lịch Vạn Niên<',
		'>Cùng chuyên mục<',
		'>Liên hệ<',
		'Bản quyền thuộc về toà soạn.',
	);

	if ( is_wp_error( $english_response ) || 200 !== (int) wp_remote_retrieve_response_code( $english_response ) ) {
		$fail( 'Vietnam Buddhism detail could not be fetched' );
	} else {
		foreach ( $english_required as $required ) {
			if ( false === strpos( $english_markup, $required ) ) {
				$fail( sprintf( 'Vietnam Buddhism detail is missing expected output: %s', $required ) );
			}
		}
		foreach ( $english_forbidden as $forbidden ) {
			if ( false !== strpos( $english_markup, $forbidden ) ) {
				$fail( sprintf( 'Vietnam Buddhism detail still contains theme-owned Vietnamese UI: %s', $forbidden ) );
			}
		}
		if ( ! array_filter( array( 'Just now', 'minute ago', 'minutes ago', 'hour ago', 'hours ago', 'day ago', 'days ago', 'month ago', 'months ago', 'year ago', 'years ago' ), static fn( $marker ) => false !== strpos( $english_markup, $marker ) ) ) {
			$fail( 'Vietnam Buddhism detail is missing English relative publication time' );
		}
	}

	if ( ! $english_category instanceof WP_Term || ! $article_category instanceof WP_Term ) {
		$fail( 'English or standard category fixture is missing' );
	} else {
		$english_category_url      = $category_url( $english_category, $origin_url );
		$english_category_response = $fetch_preview( $english_category_url, $host_header );
		$english_category_markup   = is_wp_error( $english_category_response ) ? '' : (string) wp_remote_retrieve_body( $english_category_response );
		$category_required         = array(
			'<html lang="en">',
			'aria-label="Sections"',
			'>Home</a>',
			'aria-label="Subsections"',
			'<h1 class="pgds-category__title">Vietnam Buddhism</h1>',
			'aria-label="Featured articles"',
			'>Search news</label>',
			'aria-label="Sidebar"',
			'>Most read<',
			'>Perpetual Calendar<',
			'>Contact<',
			'All rights reserved.',
			'Bài viết không ảnh để kiểm tra khung dự phòng',
			'Chuyện cây bồ đề trong sân trường',
			'Người trẻ kể chuyện quê hương bằng podcast',
		);
		$category_forbidden = array(
			'aria-label="Chuyên mục"',
			'>Trang chủ</a>',
			'aria-label="Chuyên mục con"',
			'aria-label="Bài viết nổi bật"',
			'aria-label="Tìm kiếm tin tức"',
			'>Đọc nhiều<',
			'>Lịch Vạn Niên<',
			'>Liên hệ<',
			'Bản quyền thuộc về toà soạn.',
		);

		if ( is_wp_error( $english_category_response ) || 200 !== (int) wp_remote_retrieve_response_code( $english_category_response ) ) {
			$fail( 'Vietnam Buddhism category archive could not be fetched' );
		} else {
			foreach ( $category_required as $required ) {
				if ( false === strpos( $english_category_markup, $required ) ) {
					$fail( sprintf( 'Vietnam Buddhism category archive is missing expected output: %s', $required ) );
				}
			}
			foreach ( $category_forbidden as $forbidden ) {
				if ( false !== strpos( $english_category_markup, $forbidden ) ) {
					$fail( sprintf( 'Vietnam Buddhism category archive still contains theme-owned Vietnamese UI: %s', $forbidden ) );
				}
			}
			if ( ! array_filter( array( 'Just now', 'minute ago', 'minutes ago', 'hour ago', 'hours ago', 'day ago', 'days ago', 'month ago', 'months ago', 'year ago', 'years ago' ), static fn( $marker ) => false !== strpos( $english_category_markup, $marker ) ) ) {
				$fail( 'Vietnam Buddhism category archive is missing English relative publication time' );
			}
		}

		$article_category_url      = $category_url( $article_category, $origin_url );
		$article_category_response = $fetch_preview( $article_category_url, $host_header );
		$article_category_markup   = is_wp_error( $article_category_response ) ? '' : (string) wp_remote_retrieve_body( $article_category_response );
		if (
			is_wp_error( $article_category_response ) ||
			200 !== (int) wp_remote_retrieve_response_code( $article_category_response ) ||
			false === strpos( $article_category_markup, 'aria-label="Chuyên mục con"' ) ||
			false === strpos( $article_category_markup, '>Tìm kiếm tin tức</label>' ) ||
			false !== strpos( $article_category_markup, '<html lang="en">' ) ||
			false !== strpos( $article_category_markup, 'aria-label="Subsections"' ) ||
			false !== strpos( $article_category_markup, '>Search news</label>' )
		) {
			$fail( 'English reader presentation leaked into another category archive' );
		}
	}

	$article_url      = $preview_url( $article_detail, $origin_url );
	$article_response = $fetch_preview( $article_url, $host_header );
	$article_markup   = is_wp_error( $article_response ) ? '' : (string) wp_remote_retrieve_body( $article_response );
	if (
		is_wp_error( $article_response ) ||
		200 !== (int) wp_remote_retrieve_response_code( $article_response ) ||
		false === strpos( $article_markup, '>Trang chủ</a>' ) ||
		false === strpos( $article_markup, 'aria-label="Chuyên mục"' ) ||
		false === strpos( $article_markup, '>Tìm kiếm tin tức</label>' ) ||
		false !== strpos( $article_markup, '<html lang="en">' ) ||
		false !== strpos( $article_markup, 'aria-label="Sections"' ) ||
		false !== strpos( $article_markup, '>Search news</label>' )
	) {
		$fail( 'English reader presentation leaked into a standard Article detail' );
	}

	$comment_endpoint = $origin_url . '/wp-comments-post.php';
	$comment_response = wp_remote_post(
		$comment_endpoint,
		array(
			'body'        => array(
				'comment_post_ID' => $english_detail,
				'author'          => 'Preview verifier',
				'email'           => 'preview-verifier@example.test',
				'comment'         => '',
			),
			'headers'     => array( 'Host' => $host_header ),
			'redirection' => 0,
		)
	);
	$comment_markup = is_wp_error( $comment_response ) ? '' : (string) wp_remote_retrieve_body( $comment_response );
	if (
		is_wp_error( $comment_response ) ||
		false === strpos( $comment_markup, 'Please type your comment text.' )
	) {
		$fail( 'Native comment validation did not return an English error for the Vietnam Buddhism detail' );
	}
}

WP_CLI::log( '==> Checking homepage metadata ownership...' );
// Homepage promotion metadata is owned by canonical setup or CMS curation, never
// declared by the preview package. It may legitimately target preview articles once
// those are the local article corpus, so verify the article source records rather than
// the resulting CMS state.
$preview_declares_homepage_metadata = 0;
$preview_records = json_decode( (string) file_get_contents( __DIR__ . '/preview-content.json' ), true );
foreach ( (array) $preview_records as $preview_record ) {
	foreach ( array( '_pgds_is_featured', '_pgds_feature_rank', '_pgds_photo_story' ) as $meta_key ) {
		if ( array_key_exists( $meta_key, (array) $preview_record ) ) {
			++$preview_declares_homepage_metadata;
		}
	}
}
$expect_count( 'preview-declared homepage metadata values', 0, $preview_declares_homepage_metadata );

WP_CLI::log( '==> Checking frozen article video data...' );
$expect_count( 'frozen video durations', 5, $count_post_meta( $preview_ids, '_pgds_youtube_dur' ) );
$expect_count( 'CMS-managed frozen video poster IDs', 4, $count_post_meta( $preview_ids, '_pgds_youtube_poster_id' ) );
$expect_count( 'CMS-managed frozen video poster URLs', 4, $count_post_meta( $preview_ids, '_pgds_youtube_poster' ) );
$expect_count( 'unavailable video fixtures', 1, $count_post_meta( $preview_ids, '_pgds_video_unavailable', '1' ) );

WP_CLI::log( '==> Checking category coverage...' );
foreach ( array( 'tin-phat-su', 'song-an-lanh', 'phat-tich', 'media', 'tot-doi-dep-dao', 'vietnam-buddhism' ) as $slug ) {
	$category = get_category_by_slug( $slug );
	if ( ! $category ) {
		$fail( sprintf( 'category %s is missing', $slug ) );
		continue;
	}

	$count = count(
		get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'category'       => (int) $category->term_id,
				'post__in'       => $published_ids,
			)
		)
	);
	if ( $count < 3 ) {
		$fail( sprintf( 'category %s has fewer than 3 preview posts (%d)', $slug, $count ) );
	} else {
		WP_CLI::log( sprintf( 'PASS: category %s = %d preview posts', $slug, $count ) );
	}
}

if ( $failures > 0 ) {
	WP_CLI::error( sprintf( 'Preview verification failed with %d issue(s).', $failures ) );
}

WP_CLI::success( 'Preview article fixture contract passed.' );
