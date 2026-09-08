<?php
/**
 * Apply development-only metadata and supporting content to the preview corpus.
 *
 * Runs after article import and Media Library assignment. All relationships use
 * stable preview identifiers so the dataset remains deterministic and editable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$content_path  = __DIR__ . '/preview-content.json';
$teaching_path = __DIR__ . '/preview-teachings.json';
$articles      = json_decode( (string) file_get_contents( $content_path ), true );
$teachings     = json_decode( (string) file_get_contents( $teaching_path ), true );

if ( ! is_array( $articles ) || ! is_array( $teachings ) ) {
	WP_CLI::error( 'Preview article or teaching dataset is invalid.' );
}

/**
 * Resolve an imported preview post by its stable source ID.
 *
 * @param string $source_id Preview source ID.
 * @return int
 */
$preview_post_id = static function ( $source_id ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_pgds_source_id',
			'meta_value'     => $source_id,
		)
	);

	return $query->posts ? (int) $query->posts[0] : 0;
};

/**
 * Resolve one imported preview Media Library asset.
 *
 * @param string $asset_id Preview asset ID.
 * @return int
 */
$preview_attachment_id = static function ( $asset_id ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_pgds_preview_asset_id',
			'meta_value'     => $asset_id,
		)
	);

	return $query->posts ? (int) $query->posts[0] : 0;
};

$video_metadata = array(
	'A20Lx3sv8hs' => array(
		'duration' => 187,
		'title'    => 'Đại hội Phật giáo tại Hà Nội',
	),
	'X3iS2L0Y_xw' => array(
		'duration' => 473,
		'title'    => 'Lắng nghe để thấu hiểu',
	),
	'2ZSW6vPbEjo' => array(
		'duration' => 1000,
		'title'    => 'Mười lăm phút thiền chánh niệm hơi thở',
	),
	'fTcmiAD_GwQ' => array(
		'duration' => 276,
		'title'    => 'Chùa Thanh Lương bên biển Phú Yên',
	),
);

foreach ( $articles as $article ) {
	$source_id = (string) ( $article['source_id'] ?? '' );
	$post_id   = $preview_post_id( $source_id );
	if ( ! $post_id ) {
		WP_CLI::error( sprintf( 'Missing imported preview article: %s', $source_id ) );
	}

	$primary_slug = (string) ( $article['primary_cat'] ?? '' );
	update_post_meta(
		$post_id,
		'_pgds_display_author',
		'vietnam-buddhism' === $primary_slug ? 'PGDS preview editorial team' : 'Ban biên tập dữ liệu preview PGDS'
	);

	$youtube_id = (string) get_post_meta( $post_id, '_pgds_youtube_id', true );
	if ( $youtube_id && isset( $video_metadata[ $youtube_id ] ) ) {
		update_post_meta( $post_id, '_pgds_youtube_dur', $video_metadata[ $youtube_id ]['duration'] );
		update_post_meta( $post_id, '_pgds_youtube_title', $video_metadata[ $youtube_id ]['title'] );
		delete_post_meta( $post_id, '_pgds_video_unavailable' );
	}
}

$featured_ranks = array(
	'preview-2026-tin-phat-su-01'    => 1,
	'preview-2026-phat-tich-01'      => 2,
	'preview-2026-song-an-lanh-01'   => 3,
	'preview-2026-tot-doi-dep-dao-01' => 4,
);
foreach ( $featured_ranks as $source_id => $rank ) {
	$post_id = $preview_post_id( $source_id );
	if ( $post_id ) {
		update_post_meta( $post_id, '_pgds_is_featured', '1' );
		update_post_meta( $post_id, '_pgds_feature_rank', $rank );
	}
}

foreach (
	array(
		'preview-2026-tin-phat-su-02',
		'preview-2026-song-an-lanh-02',
		'preview-2026-am-thuc-chay-02',
		'preview-2026-loi-song-xanh-02',
		'preview-2026-phat-tich-02',
		'preview-2026-tot-doi-dep-dao-02',
	) as $source_id
) {
	$post_id = $preview_post_id( $source_id );
	if ( $post_id ) {
		update_post_meta( $post_id, '_pgds_photo_story', '1' );
	}
}

$english_detail_id = $preview_post_id( 'preview-2026-vietnam-buddhism-01' );
if ( $english_detail_id ) {
	$comment_marker = 'pgds-preview-comment-vietnam-buddhism';
	$comments       = get_comments(
		array(
			'post_id'    => $english_detail_id,
			'source'     => 'comment',
			'source__not_in' => array( 'pingback', 'trackback' ),
			'suppress_filters' => false,
			'meta_key'   => '_pgds_preview_comment_id',
			'meta_value' => $comment_marker,
			'number'     => 1,
		)
	);
	if ( ! $comments ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $english_detail_id,
				'comment_author'       => 'Preview reader',
				'comment_author_email' => 'preview-reader@example.test',
				'comment_content'      => 'This CMS-managed comment verifies the English reader presentation.',
				'comment_approved'     => 1,
			)
		);
		if ( $comment_id ) {
			update_comment_meta( $comment_id, '_pgds_preview_comment_id', $comment_marker );
		}
	}
}

foreach ( $teachings as $index => $teaching ) {
	$fixture_id = sanitize_key( (string) ( $teaching['id'] ?? '' ) );
	$existing   = get_posts(
		array(
			'post_type'      => 'pgds_teaching',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_pgds_preview_teaching_id',
			'meta_value'     => $fixture_id,
		)
	);
	$postarr = array(
		'ID'           => $existing ? (int) $existing[0] : 0,
		'post_type'    => 'pgds_teaching',
		'post_status'  => 'publish',
		'post_title'   => wp_strip_all_tags( (string) ( $teaching['title'] ?? '' ) ),
		'post_name'    => sanitize_title( (string) ( $teaching['slug'] ?? '' ) ),
		'post_excerpt' => sanitize_textarea_field( (string) ( $teaching['excerpt'] ?? '' ) ),
		'post_content' => wp_kses_post( (string) ( $teaching['body_html'] ?? '' ) ),
		'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-07 01:00:00 UTC' ) - (int) $index * DAY_IN_SECONDS ),
	);
	$teaching_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $teaching_id ) ) {
		WP_CLI::error( $teaching_id->get_error_message() );
	}

	update_post_meta( $teaching_id, '_pgds_preview_teaching_id', $fixture_id );
	$attachment_id = $preview_attachment_id( (string) ( $teaching['asset_id'] ?? '' ) );
	if ( ! $attachment_id ) {
		WP_CLI::error( sprintf( 'Missing teaching image asset: %s', (string) ( $teaching['asset_id'] ?? '' ) ) );
	}
	set_post_thumbnail( $teaching_id, $attachment_id );
}

WP_CLI::success( sprintf( 'Preview metadata complete for %d articles and %d teachings.', count( $articles ), count( $teachings ) ) );
