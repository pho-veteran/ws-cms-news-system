<?php
/**
 * Development-only preview article metadata.
 *
 * Run inside the local wpcli container after importing preview-content.json and assigning
 * its licensed photo fixtures through WordPress. This script adds only metadata owned by
 * those articles; site configuration and homepage curation remain under normal CMS control.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

$article_id = $preview_post_id( 'preview-2026-0001' );
if ( $article_id ) {
	update_post_meta( $article_id, '_pgds_display_author', 'Ban Biên tập Phật giáo và Đời sống' );
	$caption_attachment_id = (int) get_post_thumbnail_id( $article_id );
	if ( $caption_attachment_id ) {
		wp_update_post(
			array(
				'ID'           => $caption_attachment_id,
				'post_excerpt' => 'Không gian Đại lễ Phật đản trong bộ dữ liệu preview phát triển nội bộ.',
			)
		);
	}
}

$video_meta = array(
	'preview-2026-0008' => array(
		'duration' => 187,
		'title'    => 'Hà Nội: Đại hội Phật giáo huyện Đông Anh lần thứ IX thành công tốt đẹp',
	),
	'preview-2026-0012' => array(
		'duration' => 473,
		'title'    => 'Lắng nghe để thấu hiểu: Phật dạy nghệ thuật giao tiếp chân thành',
	),
	'preview-2026-0019' => array(
		'duration' => 1000,
		'title'    => '15 phút thiền hằng ngày - Chánh niệm hơi thở',
	),
	'preview-2026-0039' => array(
		'duration' => 276,
		'title'    => 'Chùa Thanh Lương Phú Yên - Ngôi chùa ven biển độc đáo và bình yên',
	),
);

foreach ( $video_meta as $source_id => $meta ) {
	$post_id = $preview_post_id( $source_id );
	if ( ! $post_id ) {
		WP_CLI::warning( sprintf( 'Missing preview video: %s', $source_id ) );
		continue;
	}

	update_post_meta( $post_id, '_pgds_youtube_dur', $meta['duration'] );
	update_post_meta( $post_id, '_pgds_youtube_title', $meta['title'] );
	delete_post_meta( $post_id, '_pgds_video_unavailable' );
}

$unavailable_id = $preview_post_id( 'preview-2026-0025' );
if ( $unavailable_id ) {
	update_post_meta( $unavailable_id, '_pgds_youtube_dur', 0 );
	update_post_meta( $unavailable_id, '_pgds_youtube_title', 'Video đã lưu trữ' );
	update_post_meta( $unavailable_id, '_pgds_video_unavailable', '1' );
	delete_post_meta( $unavailable_id, '_pgds_youtube_poster_id' );
	delete_post_meta( $unavailable_id, '_pgds_youtube_poster' );
}

WP_CLI::success( 'Development preview article metadata complete.' );
