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

$comment_fixtures = array(
	array( 'preview-2026-tin-phat-su-01', 'Minh An', 'Bài viết có nhiều chi tiết thực tế, đặc biệt là cách phân chia công việc sau sự kiện.', 'vi-01' ),
	array( 'preview-2026-tin-phat-su-01', 'Tuệ Nhiên', 'Phần nói về nhu cầu của người cao tuổi rất hữu ích. Mong chuyên mục tiếp tục theo dõi ở các hoạt động sau.', 'vi-02' ),
	array( 'preview-2026-tin-phat-su-01', 'Hải Đăng', 'Hình ảnh và chú thích giúp tôi hình dung rõ hơn không gian tổ chức.', 'vi-03' ),
	array( 'preview-2026-tin-phat-su-01', 'Thanh Mai', 'Tôi thích cách bài viết phân biệt kết quả trước mắt với giá trị có thể duy trì lâu dài.', 'vi-04' ),
	array( 'preview-2026-tin-phat-su-01', 'Diệu Linh', 'Nếu có thêm thông tin về hoạt động dành cho gia đình trẻ thì tuyến nội dung sẽ càng đầy đủ.', 'vi-05' ),
	array( 'preview-2026-tin-phat-su-01', 'Quang Minh', 'Một bài tổng hợp mạch lạc, có thể dùng làm tài liệu tham khảo cho nhóm tình nguyện.', 'vi-06' ),
	array( 'preview-2026-tin-phat-su-01', 'An Hòa', 'Chi tiết về việc ghi nhận phản hồi sau chương trình là điều nhiều hoạt động cộng đồng còn thiếu.', 'vi-07' ),
	array( 'preview-2026-tin-phat-su-01', 'Nhật Tâm', 'Mong được đọc thêm câu chuyện từ những người trực tiếp tham gia công tác chuẩn bị.', 'vi-08' ),
	array( 'preview-2026-tin-phat-su-01', 'Bảo Châu', 'Nội dung vừa đủ sâu nhưng vẫn dễ theo dõi trên điện thoại.', 'vi-09' ),
	array( 'preview-2026-tin-phat-su-01', 'Thiện Đức', 'Cách tiếp cận bằng những việc nhỏ làm cho tinh thần phụng sự trở nên gần gũi hơn.', 'vi-10' ),
	array( 'preview-2026-tin-phat-su-01', 'Lam Anh', 'Phần danh sách cuối bài giúp người đọc dễ chọn một hành động cụ thể để bắt đầu.', 'vi-11' ),
	array( 'preview-2026-tin-phat-su-01', 'Trúc Lâm', 'Bố cục rõ ràng và phần trích dẫn tạo được nhịp nghỉ hợp lý cho bài dài.', 'vi-12' ),
	array( 'preview-2026-vietnam-buddhism-01', 'Preview reader', 'The article connects heritage preservation with the practical work of a living community.', 'en-01' ),
);

foreach ( $comment_fixtures as $index => $fixture ) {
	$post_id = $preview_post_id( $fixture[0] );
	if ( ! $post_id ) {
		WP_CLI::error( sprintf( 'Missing preview article for comment fixture: %s', $fixture[0] ) );
	}

	$comment_marker = 'pgds-preview-comment-' . $fixture[3];
	$existing       = get_comments(
		array(
			'post_id'    => $post_id,
			'status'     => 'all',
			'meta_key'   => '_pgds_preview_comment_id',
			'meta_value' => $comment_marker,
			'number'     => 1,
		)
	);
	if ( $existing ) {
		continue;
	}

	$comment_date = gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-07 08:00:00 UTC' ) + $index * HOUR_IN_SECONDS );
	$comment_id   = wp_insert_comment(
		array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $fixture[1],
			'comment_author_email' => sprintf( 'preview-comment-%02d@example.test', $index + 1 ),
			'comment_content'      => $fixture[2],
			'comment_approved'     => 1,
			'comment_date'         => $comment_date,
			'comment_date_gmt'     => $comment_date,
		)
	);
	if ( ! $comment_id ) {
		WP_CLI::error( sprintf( 'Could not create preview comment fixture: %s', $comment_marker ) );
	}
	update_comment_meta( $comment_id, '_pgds_preview_comment_id', $comment_marker );
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
