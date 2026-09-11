<?php
/**
 * Article metadata and the single-editor workflow.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Article field definitions.
 *
 * The definitions retain the existing metadata keys and storage types. The `editable`
 * flag controls only the post editor; synchronization jobs can continue writing the
 * system-managed keys directly.
 *
 * @return array
 */
function pgds_meta_fields( $surface = '' ) {
	$surface = pgds_sanitize_editorial_surface( $surface );
	$surface = $surface ? $surface : 'article';
	$fields  = array(
		'_pgds_sapo'           => array(
			'group'    => 'editorial',
			'label'    => 'Sa-pô',
			'help'     => 'Viết phần tóm tắt ngắn xuất hiện cùng bài trên trang chủ và đầu bài viết.',
			'type'     => 'textarea',
			'editable' => true,
		),
		'_pgds_primary_cat'    => array(
			'group'    => 'editorial',
			'label'    => 'Chuyên mục chính',
			'help'     => 'Chỉ hiện các chuyên mục đã được đánh dấu trong mục Chuyên mục của bài viết. Hãy chọn chuyên mục trước nếu danh sách đang trống.',
			'type'     => 'category',
			'editable' => true,
		),
		'_pgds_source'         => array(
			'group'    => 'editorial',
			'label'    => 'Nguồn tin',
			'help'     => 'Ghi tên nguồn nếu bài viết sử dụng nội dung từ đơn vị khác; có thể để trống.',
			'type'     => 'text',
			'editable' => true,
		),
		'_pgds_display_author' => array(
			'group'    => 'editorial',
			'label'    => 'Tên tác giả hiển thị',
			'help'     => 'Chỉ nhập khi tên cần hiển thị khác tên tài khoản đang đăng bài.',
			'type'     => 'text',
			'editable' => true,
		),
		'_pgds_is_featured'    => array(
			'group'    => 'homepage',
			'label'    => 'Tin nổi bật',
			'help'     => 'Bật để bài viết có thể xuất hiện trong khối Tin nổi bật trên trang chủ.',
			'type'     => 'checkbox',
			'editable' => true,
		),
		'_pgds_feature_rank'   => array(
			'group'    => 'homepage',
			'label'    => 'Vị trí Tin nổi bật',
			'help'     => 'Chọn từ 1 đến 4. Vị trí 1 là tin chính; các vị trí 2–4 là tin phụ.',
			'type'     => 'number',
			'editable' => true,
			'min'      => 1,
			'max'      => 4,
			'step'     => 1,
		),
		'_pgds_photo_story'    => array(
			'group'    => 'homepage',
			'label'    => 'Tin ảnh',
			'help'     => 'Bật để bài viết có thể xuất hiện trong khối Tin ảnh.',
			'type'     => 'checkbox',
			'editable' => true,
		),
		'_pgds_is_popular'     => array(
			'group'    => 'homepage',
			'label'    => 'Đọc nhiều',
			'help'     => 'Bật để bài viết có thể xuất hiện trong khối Đọc nhiều ở cột bên.',
			'type'     => 'checkbox',
			'editable' => true,
		),
		'_pgds_popular_rank'   => array(
			'group'    => 'homepage',
			'label'    => 'Vị trí Đọc nhiều',
			'help'     => 'Chọn từ 1 đến 4 để sắp xếp thứ tự hiển thị trong khối Đọc nhiều.',
			'type'     => 'number',
			'editable' => true,
			'min'      => 1,
			'max'      => 4,
			'step'     => 1,
		),
		'_pgds_youtube_id'     => array(
			'group'    => 'video',
			'label'    => 'Video YouTube',
			'help'     => 'Dán đường dẫn YouTube hoặc mã video gồm 11 ký tự. Mỗi bài chỉ dùng một video.',
			'type'     => 'text',
			'editable' => true,
		),
		'_pgds_youtube_title'  => array(
			'group'    => 'video',
			'label'    => 'Tiêu đề YouTube',
			'help'     => 'PGDS tự cập nhật tiêu đề video; người biên tập không cần nhập.',
			'type'     => 'readonly',
			'editable' => false,
		),
		'_pgds_youtube_dur'    => array(
			'group'    => 'video',
			'label'    => 'Thời lượng',
			'help'     => 'PGDS tự cập nhật thời lượng; người biên tập không cần nhập.',
			'type'     => 'duration',
			'editable' => false,
		),
	);

	if ( 'emagazine' === $surface ) {
		$fields['_pgds_source']['label'] = 'Nguồn / ghi công ảnh';
		$fields['_pgds_source']['help']  = 'Ghi nguồn hoặc ghi công ảnh chung; ghi công từng ảnh trong caption của ảnh tương ứng.';
	}

	if ( 'vietnam-buddhism' === $surface ) {
		$fields['_pgds_sapo']['label']                 = 'Summary';
		$fields['_pgds_sapo']['help']                  = 'Write the short summary shown in article cards and at the beginning of the article.';
		$fields['_pgds_primary_cat']['label']          = 'Primary category';
		$fields['_pgds_primary_cat']['help']           = 'Vietnam Buddhism is maintained automatically for this workflow.';
		$fields['_pgds_source']['label']               = 'Source';
		$fields['_pgds_source']['help']                = 'Name the source when the article uses material from another organization; otherwise leave it blank.';
		$fields['_pgds_display_author']['label']       = 'Display author';
		$fields['_pgds_display_author']['help']        = 'Use this only when the public byline differs from the WordPress account name.';
		$fields['_pgds_is_featured']['label']          = 'Featured';
		$fields['_pgds_is_featured']['help']           = 'Allow this article to appear in the Featured section on the home page.';
		$fields['_pgds_feature_rank']['label']         = 'Featured position';
		$fields['_pgds_feature_rank']['help']          = 'Choose a position from 1 to 4 when Featured is enabled.';
		$fields['_pgds_photo_story']['label']          = 'Photo story';
		$fields['_pgds_photo_story']['help']           = 'Allow this article to appear in the Photo Story section.';
		$fields['_pgds_is_popular']['label']           = 'Most read';
		$fields['_pgds_is_popular']['help']            = 'Allow this article to appear in the Most read sidebar.';
		$fields['_pgds_popular_rank']['label']         = 'Most read position';
		$fields['_pgds_popular_rank']['help']          = 'Choose a position from 1 to 4 when Most read is enabled.';
		$fields['_pgds_youtube_id']['label']            = 'YouTube video';
		$fields['_pgds_youtube_id']['help']             = 'Paste a YouTube URL or its 11-character video ID.';
		$fields['_pgds_youtube_title']['label']         = 'YouTube title';
		$fields['_pgds_youtube_title']['help']          = 'PGDS synchronizes the video title automatically.';
		$fields['_pgds_youtube_dur']['label']           = 'Duration';
		$fields['_pgds_youtube_dur']['help']            = 'PGDS synchronizes the video duration automatically.';
	}

	return $fields;
}

/**
 * Meta box group definitions.
 *
 * @return array
 */
function pgds_meta_groups( $surface = '' ) {
	$surface = pgds_sanitize_editorial_surface( $surface );
	$surface = $surface ? $surface : 'article';
	$groups  = array(
		'editorial' => array(
			'label'       => 'Biên tập',
			'description' => 'Bạn nhập các thông tin giúp bài viết hiển thị đúng tên, nguồn và chuyên mục.',
		),
		'homepage'  => array(
			'label'       => 'Điều kiện và vị trí hiển thị trang chủ',
			'description' => 'Bạn chọn nơi bài có thể xuất hiện; các khối trên trang chủ sẽ tự lấy bài phù hợp.',
		),
		'video'     => array(
			'label'       => 'Video',
			'description' => 'Bạn nhập video YouTube; PGDS tự cập nhật thời lượng và trạng thái.',
		),
	);

	if ( 'vietnam-buddhism' === $surface ) {
		$groups['editorial']['label']        = 'Editorial details';
		$groups['editorial']['description']  = 'Add the summary, byline and source used by the public article.';
		$groups['homepage']['label']         = 'Home-page curation';
		$groups['homepage']['description']   = 'Choose where this article may appear on the home page.';
		$groups['video']['label']            = 'Video';
		$groups['video']['description']      = 'YouTube metadata is not used by the Vietnam Buddhism workflow.';
	}

	return $groups;
}

/**
 * Return meta-box groups rendered by one editorial surface.
 *
 * @param string $surface Surface key.
 * @return string[]
 */
function pgds_editorial_surface_groups( $surface ) {
	switch ( $surface ) {
		case 'emagazine':
			return array( 'editorial' );
		case 'video':
			return array( 'editorial', 'video' );
		case 'vietnam-buddhism':
		case 'article':
		default:
			return array( 'editorial', 'homepage' );
	}
}

/**
 * Registered metadata that synchronization owns.
 *
 * @return string[]
 */
function pgds_synchronized_meta_keys() {
	return array(
		'_pgds_youtube_dur',
		'_pgds_youtube_title',
		'_pgds_youtube_poster_id',
		'_pgds_youtube_poster',
		'_pgds_video_unavailable',
	);
}

/**
 * Register typed post metadata for REST reads and writes.
 */
function pgds_register_meta() {
	$types = array(
		'_pgds_sapo'              => 'string',
		'_pgds_primary_cat'       => 'integer',
		'_pgds_youtube_id'        => 'string',
		'_pgds_youtube_dur'       => 'integer',
		'_pgds_youtube_title'     => 'string',
		'_pgds_youtube_poster_id' => 'integer',
		'_pgds_is_featured'       => 'boolean',
		'_pgds_feature_rank'      => 'integer',
		'_pgds_photo_story'       => 'boolean',
		'_pgds_is_popular'        => 'boolean',
		'_pgds_popular_rank'      => 'integer',
		'_pgds_source'            => 'string',
		'_pgds_display_author'    => 'string',
	);
	$sanitizers = array(
		'_pgds_sapo'        => 'sanitize_textarea_field',
		'_pgds_primary_cat' => 'pgds_sanitize_primary_category_meta',
		'string'            => 'sanitize_text_field',
		'integer'    => 'absint',
		'boolean'    => 'rest_sanitize_boolean',
	);

	foreach ( $types as $key => $type ) {
		$sanitize = $sanitizers[ $key ] ?? ( $sanitizers[ $type ] ?? 'sanitize_text_field' );
		register_post_meta(
			'post',
			$key,
			array(
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id, $user_id ) {
					if ( $post_id ) {
						return user_can( $user_id, 'edit_post', $post_id );
					}

					return user_can( $user_id, 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'pgds_register_meta' );

/**
 * Register the article meta box.
 */
function pgds_add_meta_box() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	$surface = pgds_current_editorial_surface( $post_id );
	$title   = 'vietnam-buddhism' === $surface ? 'Editorial details' : 'Nội dung và hiển thị PGDS';

	/*
	 * The block editor renders normal-context legacy boxes in the drawer below the
	 * content. Side-context boxes are hidden there, so this context is intentional.
	 */
	add_meta_box(
		'pgds_article_meta',
		$title,
		'pgds_render_meta_box',
		'post',
		'normal',
		'high'
	);

	if ( ! empty( pgds_editorial_surfaces()[ $surface ]['fixed_primary'] ) ) {
		remove_meta_box( 'categorydiv', 'post', 'side' );
	}
}
add_action( 'add_meta_boxes', 'pgds_add_meta_box' );

/**
 * Validate the Featured flag and rank as one unit.
 *
 * @param bool  $featured Whether Featured is enabled.
 * @param mixed $rank     Submitted rank.
 * @return int|WP_Error Normalized rank or validation error.
 */
function pgds_validate_featured_rank( $featured, $rank ) {
	if ( is_string( $rank ) ) {
		$rank = trim( $rank );
	}

	if ( '' === $rank || null === $rank || 0 === $rank || '0' === $rank ) {
		if ( $featured ) {
			return new WP_Error( 'pgds_invalid_featured_rank' );
		}

		return 0;
	}

	$is_valid = ( is_int( $rank ) && $rank >= 1 && $rank <= 4 ) ||
		( is_string( $rank ) && 1 === preg_match( '/^[1-4]$/', $rank ) );

	if ( ! $is_valid ) {
		return new WP_Error( 'pgds_invalid_featured_rank' );
	}

	return (int) $rank;
}

/**
 * Validate the Most read flag and rank as one unit.
 *
 * @param bool  $popular Whether Most read is enabled.
 * @param mixed $rank    Submitted rank.
 * @return int|WP_Error Normalized rank or validation error.
 */
function pgds_validate_popular_rank( $popular, $rank ) {
	if ( is_string( $rank ) ) {
		$rank = trim( $rank );
	}

	if ( '' === $rank || null === $rank || 0 === $rank || '0' === $rank ) {
		if ( $popular ) {
			return new WP_Error( 'pgds_invalid_popular_rank' );
		}

		return 0;
	}

	$is_valid = ( is_int( $rank ) && $rank >= 1 && $rank <= 4 ) ||
		( is_string( $rank ) && 1 === preg_match( '/^[1-4]$/', $rank ) );

	if ( ! $is_valid ) {
		return new WP_Error( 'pgds_invalid_popular_rank' );
	}

	return (int) $rank;
}

/**
 * Validate that the primary category is assigned to the post.
 *
 * @param mixed $term_id      Submitted term ID.
 * @param array $assigned_ids Final category IDs assigned to the post.
 * @return int|WP_Error Normalized term ID or validation error.
 */
function pgds_validate_primary_category( $term_id, array $assigned_ids ) {
	if ( is_string( $term_id ) ) {
		$term_id = trim( $term_id );
	}

	if ( '' === $term_id || null === $term_id || 0 === $term_id || '0' === $term_id ) {
		return 0;
	}

	$is_integer = is_int( $term_id ) || ( is_string( $term_id ) && 1 === preg_match( '/^[1-9][0-9]*$/', $term_id ) );
	if ( ! $is_integer ) {
		return new WP_Error( 'pgds_invalid_primary_category' );
	}

	$term_id      = (int) $term_id;
	$assigned_ids = array_map( 'intval', $assigned_ids );

	if ( ! pgds_validate_primary_category_id( $term_id, 0, $assigned_ids ) ) {
		return new WP_Error( 'pgds_invalid_primary_category' );
	}

	return $term_id;
}

/**
 * Normalize a supported YouTube URL or ID.
 *
 * @param mixed $raw Submitted value.
 * @return string|WP_Error Canonical ID, an empty string, or validation error.
 */
function pgds_normalize_youtube_input( $raw ) {
	if ( ! is_scalar( $raw ) && null !== $raw ) {
		return new WP_Error( 'pgds_invalid_youtube' );
	}

	$input = trim( (string) $raw );
	if ( '' === $input ) {
		return '';
	}

	if ( 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', $input ) ) {
		return $input;
	}

	$parts = wp_parse_url( $input );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return new WP_Error( 'pgds_invalid_youtube' );
	}

	$scheme = strtolower( $parts['scheme'] );
	$host   = strtolower( rtrim( $parts['host'], '.' ) );
	$path   = isset( $parts['path'] ) ? $parts['path'] : '';

	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return new WP_Error( 'pgds_invalid_youtube' );
	}

	if ( isset( $parts['port'] ) && ! in_array( (int) $parts['port'], array( 80, 443 ), true ) ) {
		return new WP_Error( 'pgds_invalid_youtube' );
	}

	$is_youtu_be  = 'youtu.be' === $host || 'www.youtu.be' === $host;
	$is_youtube   = 'youtube.com' === $host || str_ends_with( $host, '.youtube.com' );
	$is_nocookie  = 'youtube-nocookie.com' === $host || str_ends_with( $host, '.youtube-nocookie.com' );
	$canonical_id = '';

	if ( $is_youtu_be && preg_match( '~^/([A-Za-z0-9_-]{11})/?$~', $path, $matches ) ) {
		$canonical_id = $matches[1];
	} elseif ( ( $is_youtube || $is_nocookie ) && preg_match( '~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~', $path, $matches ) ) {
		$canonical_id = $matches[1];
	} elseif ( $is_youtube && preg_match( '~^/watch/?$~', $path ) && isset( $parts['query'] ) ) {
		$query = array();
		wp_parse_str( $parts['query'], $query );
		if ( isset( $query['v'] ) && is_string( $query['v'] ) && 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', $query['v'] ) ) {
			$canonical_id = $query['v'];
		}
	}

	if ( '' === $canonical_id ) {
		return new WP_Error( 'pgds_invalid_youtube' );
	}

	return $canonical_id;
}

/**
 * Extract a canonical YouTube ID while preserving the importer-facing contract.
 *
 * @param string $input URL or ID.
 * @return string Canonical ID or an empty string.
 */
function pgds_extract_youtube_id( $input ) {
	$result = pgds_normalize_youtube_input( $input );

	return is_wp_error( $result ) ? '' : $result;
}

/**
 * Find one published post using the same Featured rank.
 *
 * @param int $post_id Current post ID.
 * @param int $rank    Featured rank.
 * @return int Conflicting post ID or zero.
 */
function pgds_find_featured_rank_conflict( $post_id, $rank ) {
	if ( $rank < 1 || $rank > 4 ) {
		return 0;
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'post__not_in'           => $post_id ? array( (int) $post_id ) : array(),
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'cache_results'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'   => '_pgds_is_featured',
					'value' => '1',
				),
				array(
					'key'     => '_pgds_feature_rank',
					'value'   => (int) $rank,
					'type'    => 'NUMERIC',
					'compare' => '=',
				),
			),
		)
	);

	return isset( $query->posts[0] ) ? (int) $query->posts[0] : 0;
}

/**
 * Describe the placement selected for quality-warning text.
 *
 * @param bool $featured   Whether Featured is enabled.
 * @param bool $photo_story Whether Tin ảnh is enabled.
 * @param bool $english     Whether to use English labels.
 * @return string
 */
function pgds_article_placement_label( $featured, $photo_story, $english = false ) {
	if ( $english ) {
		if ( $featured && $photo_story ) {
			return 'Featured and Photo Story';
		}

		return $featured ? 'Featured' : 'Photo Story';
	}

	if ( $featured && $photo_story ) {
		return 'Tin nổi bật và Tin ảnh';
	}

	return $featured ? 'Tin nổi bật' : 'Tin ảnh';
}

/**
 * Get nonblocking quality and duplicate-rank warnings for a post.
 *
 * @param int $post_id Post ID.
 * @return array[] Warning records.
 */
function pgds_get_article_warnings( $post_id ) {
	$warnings    = array();
	$featured    = '1' === get_post_meta( $post_id, '_pgds_is_featured', true );
	$photo_story = '1' === get_post_meta( $post_id, '_pgds_photo_story', true );
	$english     = 'vietnam-buddhism' === pgds_current_editorial_surface( $post_id );

	if ( $featured || $photo_story ) {
		$placement = pgds_article_placement_label( $featured, $photo_story, $english );

		if ( ! has_post_thumbnail( $post_id ) ) {
			$warnings[] = array(
				'code'    => 'pgds_missing_featured_image',
				'message' => $english
					? sprintf( '%s has no featured image. The post can still be saved, but its card may have no image.', $placement )
					: sprintf( '%s chưa có ảnh đại diện. Bài vẫn được lưu, nhưng thẻ bài có thể thiếu ảnh.', $placement ),
			);
		}

		if ( '' === trim( (string) get_post_meta( $post_id, '_pgds_sapo', true ) ) ) {
			$warnings[] = array(
				'code'    => 'pgds_missing_sapo',
				'message' => $english
					? sprintf( '%s has no summary. The post can still be saved, but its introduction may be empty.', $placement )
					: sprintf( '%s chưa có sa-pô. Bài vẫn được lưu, nhưng phần giới thiệu có thể bị trống.', $placement ),
			);
		}
	}

	if ( $featured ) {
		$rank        = (int) get_post_meta( $post_id, '_pgds_feature_rank', true );
		$conflict_id = pgds_find_featured_rank_conflict( $post_id, $rank );

		if ( $conflict_id ) {
			$warning = array(
				'code'        => 'pgds_duplicate_featured_rank',
				'message'     => $english
					? sprintf( 'Another published post already uses Featured position %d. Both posts were left unchanged.', $rank )
					: sprintf( 'Một bài đã xuất bản khác đang dùng vị trí Tin nổi bật %d. Cả hai bài vẫn được giữ nguyên.', $rank ),
				'conflict_id' => $conflict_id,
				'edit_label'  => $english ? 'Open the post using this position' : 'Mở bài đang trùng vị trí',
			);
			if ( current_user_can( 'edit_post', $conflict_id ) ) {
				$warning['edit_url'] = get_edit_post_link( $conflict_id, 'raw' );
			}
			$warnings[] = $warning;
		}
	}

	return $warnings;
}

/**
 * Map validation codes to editor-facing correction instructions.
 *
 * @return array
 */
function pgds_meta_feedback_messages( $surface = '' ) {
	$messages = array(
		'pgds_invalid_featured_rank'                 => 'Thiết lập Tin nổi bật chưa được cập nhật. Khi bật Tin nổi bật, hãy chọn vị trí từ 1 đến 4. Giá trị hợp lệ trước đó được giữ nguyên.',
		'pgds_invalid_popular_rank'                  => 'Thiết lập Đọc nhiều chưa được cập nhật. Khi bật Đọc nhiều, hãy chọn vị trí từ 1 đến 4. Giá trị hợp lệ trước đó được giữ nguyên.',
		'pgds_invalid_primary_category'              => 'Chuyên mục chính chưa được cập nhật. Hãy chọn một chuyên mục đã được đánh dấu cho bài viết. Giá trị hợp lệ trước đó được giữ nguyên.',
		'pgds_invalid_youtube'                       => 'Video YouTube chưa được cập nhật. Hãy dán đúng đường dẫn YouTube hoặc mã video gồm 11 ký tự. Video hợp lệ trước đó được giữ nguyên.',
		'pgds_invalid_editorial_surface'             => 'Loại nội dung không hợp lệ. Giá trị phân loại trước đó được giữ nguyên.',
		'pgds_article_category_required'             => 'Bài viết phải chọn một chuyên mục tiếng Việt hợp lệ trước khi xuất bản.',
		'pgds_video_requires_youtube'                => 'Video phải có đường dẫn YouTube hoặc mã video hợp lệ trước khi xuất bản.',
		'pgds_surface_change_confirmation_required' => 'Hãy xác nhận trước khi chuyển bài sang một loại nội dung khác.',
		'pgds_missing_editorial_category'            => 'Không tìm thấy chuyên mục bắt buộc. Hãy chạy lại bước khôi phục taxonomy trước khi lưu.',
		'pgds_classification_write_failed'           => 'Không thể cập nhật đồng thời loại nội dung và chuyên mục. Phân loại trước đó đã được giữ nguyên.',
	);

	$surface = pgds_sanitize_editorial_surface( $surface );
	$surface = $surface ? $surface : pgds_requested_editorial_surface();
	if ( 'vietnam-buddhism' === $surface ) {
		$messages['pgds_invalid_featured_rank']                 = 'Featured settings were not updated. Choose a position from 1 to 4 when Featured is enabled.';
		$messages['pgds_invalid_popular_rank']                  = 'Most read settings were not updated. Choose a position from 1 to 4 when Most read is enabled.';
		$messages['pgds_invalid_primary_category']              = 'The primary category was not updated. The previous valid value was preserved.';
		$messages['pgds_invalid_youtube']                       = 'The YouTube video was not updated. Paste a valid YouTube URL or 11-character video ID.';
		$messages['pgds_invalid_editorial_surface']             = 'The selected content type is invalid. The previous classification was preserved.';
		$messages['pgds_article_category_required']             = 'Choose a valid primary category before publishing.';
		$messages['pgds_video_requires_youtube']                = 'A valid YouTube URL or video ID is required before publishing a Video.';
		$messages['pgds_surface_change_confirmation_required'] = 'Confirm the change before moving this post to another content type.';
		$messages['pgds_missing_editorial_category']            = 'The required category is unavailable. Restore the canonical taxonomy before saving.';
		$messages['pgds_classification_write_failed']           = 'The content type and category could not be updated together. The previous classification was preserved.';
	}

	return $messages;
}

/**
 * Record request-local validation feedback.
 *
 * @param int      $post_id Post ID.
 * @param string[] $codes   Validation codes.
 */
function pgds_record_meta_feedback( $post_id, array $codes ) {
	$allowed = array_keys( pgds_meta_feedback_messages() );
	$codes   = array_values( array_unique( array_intersect( $codes, $allowed ) ) );

	if ( ! $codes ) {
		return;
	}

	$GLOBALS['pgds_meta_feedback'] = array(
		'post_id' => (int) $post_id,
		'codes'   => $codes,
	);
}

/**
 * Build a scoped transient key for validation feedback.
 *
 * @param int    $user_id User ID.
 * @param int    $post_id Post ID.
 * @param string $token   Opaque token.
 * @return string
 */
function pgds_meta_feedback_transient_key( $user_id, $post_id, $token ) {
	return sprintf( 'pgds_meta_feedback_%d_%d_%s', $user_id, $post_id, $token );
}

/**
 * Store validation feedback for one post-save redirect.
 *
 * @param int      $post_id Post ID.
 * @param string[] $codes   Validation codes.
 * @return string Opaque token or an empty string.
 */
function pgds_store_meta_feedback( $post_id, array $codes ) {
	$user_id = get_current_user_id();
	$allowed = array_keys( pgds_meta_feedback_messages() );
	$codes   = array_values( array_unique( array_intersect( $codes, $allowed ) ) );

	if ( ! $user_id || ! $post_id || ! $codes ) {
		return '';
	}

	$token = wp_generate_password( 20, false, false );
	$key   = pgds_meta_feedback_transient_key( $user_id, $post_id, $token );
	set_transient(
		$key,
		array(
			'user_id' => $user_id,
			'post_id' => (int) $post_id,
			'codes'   => $codes,
		),
		5 * MINUTE_IN_SECONDS
	);

	return $token;
}

/**
 * Consume validation feedback for the current user and post.
 *
 * @param int    $post_id Post ID.
 * @param string $token   Opaque token.
 * @return string[] Validation codes.
 */
function pgds_consume_meta_feedback( $post_id, $token ) {
	if ( 1 !== preg_match( '/^[A-Za-z0-9]{20}$/', $token ) ) {
		return array();
	}

	$user_id = get_current_user_id();
	$key     = pgds_meta_feedback_transient_key( $user_id, $post_id, $token );
	$data    = get_transient( $key );
	delete_transient( $key );

	if ( ! is_array( $data ) || $user_id !== (int) ( $data['user_id'] ?? 0 ) || $post_id !== (int) ( $data['post_id'] ?? 0 ) ) {
		return array();
	}

	$allowed = array_keys( pgds_meta_feedback_messages() );

	return array_values( array_unique( array_intersect( (array) ( $data['codes'] ?? array() ), $allowed ) ) );
}

/**
 * Append an opaque validation-feedback token to the matching post redirect.
 *
 * @param string $location Redirect URL.
 * @param int    $post_id  Post ID.
 * @return string
 */
function pgds_redirect_post_location( $location, $post_id ) {
	$feedback = $GLOBALS['pgds_meta_feedback'] ?? array();
	if ( (int) ( $feedback['post_id'] ?? 0 ) !== (int) $post_id ) {
		return $location;
	}

	unset( $GLOBALS['pgds_meta_feedback'] );
	$token = pgds_store_meta_feedback( $post_id, (array) ( $feedback['codes'] ?? array() ) );

	return $token ? add_query_arg( 'pgds_meta_feedback', rawurlencode( $token ), $location ) : $location;
}
add_filter( 'redirect_post_location', 'pgds_redirect_post_location', 10, 2 );

/**
 * Render validation messages as standard WordPress notices.
 *
 * @param string[] $codes  Validation codes.
 * @param bool     $inline Whether the notices are inside the meta box.
 */
function pgds_render_meta_feedback( array $codes, $inline = false ) {
	$messages = pgds_meta_feedback_messages();
	$class    = $inline ? 'notice notice-error inline pgds-meta-feedback' : 'notice notice-error is-dismissible pgds-meta-feedback';

	foreach ( array_unique( $codes ) as $code ) {
		if ( ! isset( $messages[ $code ] ) ) {
			continue;
		}

		printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), esc_html( $messages[ $code ] ) );
	}
}

/**
 * Make redirected validation feedback available to the current editor screen.
 *
 * Classic editor screens render the feedback through admin_notices. Block-editor
 * screens render it inside the PGDS compatibility meta box.
 */
function pgds_prepare_meta_feedback() {
	if ( ! isset( $_GET['pgds_meta_feedback'], $_GET['post'] ) ) {
		return;
	}

	$post_id = absint( $_GET['post'] );
	$token   = sanitize_text_field( wp_unslash( $_GET['pgds_meta_feedback'] ) );
	$post     = get_post( $post_id );

	if ( ! $post || 'post' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$codes = pgds_consume_meta_feedback( $post_id, $token );
	if ( ! $codes ) {
		return;
	}

	$GLOBALS['pgds_meta_feedback'] = array(
		'post_id' => $post_id,
		'codes'   => $codes,
	);
}
add_action( 'admin_init', 'pgds_prepare_meta_feedback' );

/**
 * Display prepared validation feedback after a classic post save.
 */
function pgds_admin_meta_notices() {
	$feedback = $GLOBALS['pgds_meta_feedback'] ?? array();
	$post_id  = (int) ( $feedback['post_id'] ?? 0 );

	if ( ! $post_id ) {
		return;
	}

	$screen = get_current_screen();
	if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
		return;
	}

	pgds_render_meta_feedback( (array) ( $feedback['codes'] ?? array() ) );
	unset( $GLOBALS['pgds_meta_feedback'] );
}
add_action( 'admin_notices', 'pgds_admin_meta_notices' );

/**
 * Format synchronized video duration for editors.
 *
 * @param mixed $seconds Stored duration in seconds.
 * @return string
 */
function pgds_format_video_duration( $seconds, $english = false ) {
	$seconds = absint( $seconds );
	if ( ! $seconds ) {
		return $english ? 'No data yet' : 'Chưa có dữ liệu';
	}

	$hours   = (int) floor( $seconds / HOUR_IN_SECONDS );
	$minutes = (int) floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
	$seconds = $seconds % MINUTE_IN_SECONDS;

	return $hours ? sprintf( '%d:%02d:%02d', $hours, $minutes, $seconds ) : sprintf( '%d:%02d', $minutes, $seconds );
}

/**
 * Get the synchronized-video status label.
 *
 * @param int  $post_id Post ID.
 * @param bool $english Whether to use English labels.
 * @return string
 */
function pgds_video_status_label( $post_id, $english = false ) {
	if ( ! get_post_meta( $post_id, '_pgds_youtube_id', true ) ) {
		return $english ? 'No video attached' : 'Chưa gắn video';
	}
	if ( '1' === get_post_meta( $post_id, '_pgds_video_unavailable', true ) ) {
		return $english ? 'Video is unavailable' : 'Video không còn khả dụng';
	}
	if (
		get_post_meta( $post_id, '_pgds_youtube_dur', true ) ||
		get_post_meta( $post_id, '_pgds_youtube_title', true ) ||
		get_post_meta( $post_id, '_pgds_youtube_poster_id', true )
	) {
		return $english ? 'Synchronized' : 'Đã đồng bộ';
	}

	return $english ? 'Waiting for synchronization' : 'Đang chờ đồng bộ';
}

/**
 * Render nonblocking article warnings.
 *
 * @param array[] $warnings Warning records.
 */
function pgds_render_article_warnings( array $warnings ) {
	foreach ( $warnings as $warning ) {
		echo '<div class="notice notice-warning inline"><p>';
		echo esc_html( $warning['message'] ?? '' );
		if ( ! empty( $warning['edit_url'] ) ) {
			printf( ' <a href="%s">%s</a>', esc_url( $warning['edit_url'] ), esc_html( $warning['edit_label'] ?? 'Mở bài đang trùng vị trí' ) );
		}
		echo '</p></div>';
	}
}

function pgds_render_meta_field( $post_id, $key, array $field, $surface = 'article' ) {
	$value    = get_post_meta( $post_id, $key, true );
	$id       = esc_attr( $key );
	$editable = ! empty( $field['editable'] );

	echo '<div class="pgds-metabox__field">';
	if ( $editable ) {
		printf( '<label class="pgds-metabox__label" for="%s">%s</label>', $id, esc_html( $field['label'] ) );
	} else {
		printf( '<span class="pgds-metabox__label" id="%s-label">%s</span>', $id, esc_html( $field['label'] ) );
	}

	if ( ! $editable ) {
		$readonly_value = 'duration' === $field['type'] ? pgds_format_video_duration( $value, 'vietnam-buddhism' === $surface ) : (string) $value;
		if ( '' === $readonly_value ) {
			$readonly_value = 'vietnam-buddhism' === $surface ? 'No data yet' : 'Chưa có dữ liệu';
		}
		printf( '<output id="%s-value" class="pgds-metabox__readonly" aria-labelledby="%s-label">%s</output>', $id, $id, esc_html( $readonly_value ) );
	} else {
		switch ( $field['type'] ) {
			case 'textarea':
				printf(
					'<textarea class="widefat" id="%s" name="%s" rows="3">%s</textarea>',
					$id,
					$id,
					esc_textarea( (string) $value )
				);
				break;

			case 'checkbox':
				printf(
					'<label class="pgds-metabox__choice"><input type="checkbox" id="%s" name="%s" value="1" %s> <span>%s</span></label>',
					$id,
					$id,
					checked( $value, '1', false ),
					esc_html( 'vietnam-buddhism' === $surface ? 'Enable' : 'Bật' )
				);
				break;

			case 'number':
				printf(
					'<input class="small-text" type="number" id="%s" name="%s" value="%s" min="%d" max="%d" step="%d" aria-describedby="%s-help">',
					$id,
					$id,
					esc_attr( (string) $value ),
					(int) $field['min'],
					(int) $field['max'],
					(int) $field['step'],
					$id
				);
				break;

			case 'category':
				$assigned_ids = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
				$selected     = (int) $value;
				$allowed_ids  = is_array( $assigned_ids ) ? array_map( 'intval', $assigned_ids ) : array();
				$categories   = get_categories(
					array(
						'hide_empty' => 0,
						'include'    => $allowed_ids,
					)
				);

				printf( '<select class="widefat" id="%s" name="%s" aria-describedby="%s-help">', $id, $id, $id );
				echo '<option value="0">— Chọn chuyên mục chính —</option>';
				foreach ( $categories as $category ) {
					printf(
						'<option value="%d"%s>%s</option>',
						(int) $category->term_id,
						selected( $selected, (int) $category->term_id, false ),
						esc_html( $category->name )
					);
				}
				echo '</select>';
				break;

			default:
				printf(
					'<input class="widefat" type="text" id="%s" name="%s" value="%s" aria-describedby="%s-help">',
					$id,
					$id,
					esc_attr( (string) $value ),
					$id
				);
		}
	}

	if ( ! empty( $field['help'] ) ) {
		printf( '<p class="description" id="%s-help">%s</p>', $id, esc_html( $field['help'] ) );
	}
	if ( $editable && 'number' === $field['type'] ) {
		echo '<p class="description pgds-metabox__feature-rank-state" aria-live="polite"></p>';
	}
	echo '</div>';
}

/**
 * Render the shared content-type and primary-category workflow control.
 *
 * @param WP_Post $post    Post being edited.
 * @param string  $surface Active surface.
 */
function pgds_render_classification_control( $post, $surface ) {
	$definitions   = pgds_editorial_surfaces();
	$classification = pgds_get_editorial_classification( $post->ID );
	$english        = 'vietnam-buddhism' === $surface;
	$article_slug   = $classification['valid'] && 'article' === $classification['surface'] ? $classification['primary_slug'] : '';
	$confirm_id     = 'pgds_surface_change_confirm';

	echo '<div class="pgds-metabox__classification" data-pgds="classification">';
	printf(
		'<label class="pgds-metabox__label" for="pgds_surface">%s</label>',
		esc_html( $english ? 'Content type' : 'Loại nội dung' )
	);
	echo '<select class="widefat" id="pgds_surface" name="pgds_surface" data-pgds="surface-select">';
	foreach ( $definitions as $key => $definition ) {
		$fixed_term = ! empty( $definition['fixed_primary'] ) ? pgds_category_term( $definition['fixed_primary'] ) : null;
		$label      = $english && 'article' === $key ? 'Article' : $definition['label'];
		printf(
			'<option value="%s" data-term-id="%d"%s>%s</option>',
			esc_attr( $key ),
			$fixed_term instanceof WP_Term ? (int) $fixed_term->term_id : 0,
			selected( $surface, $key, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
	printf(
		'<p class="description">%s</p>',
		esc_html( $english ? 'Changing the content type also updates the primary category.' : 'Khi chuyển loại nội dung, chuyên mục chính cũng được cập nhật đồng thời.' )
	);

	echo '<div class="pgds-metabox__article-category" data-pgds="article-category">';
	printf(
		'<label class="pgds-metabox__label" for="pgds_article_primary_slug">%s</label>',
		esc_html( $english ? 'Vietnamese Article category' : 'Chuyên mục bài viết' )
	);
	echo '<select class="widefat" id="pgds_article_primary_slug" name="pgds_article_primary_slug" aria-describedby="pgds_article_primary_slug-help">';
	printf( '<option value="">%s</option>', esc_html( $english ? '— Choose a category —' : '— Chọn chuyên mục —' ) );
	foreach ( $definitions['article']['primary_slugs'] as $slug ) {
		$term = pgds_category_term( $slug );
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		printf(
			'<option value="%s" data-term-id="%d"%s>%s</option>',
			esc_attr( $slug ),
			(int) $term->term_id,
			selected( $article_slug, $slug, false ),
			esc_html( $term->name )
		);
	}
	echo '</select>';
	printf(
		'<p class="description" id="pgds_article_primary_slug-help">%s</p>',
		esc_html( $english ? 'Required before publishing a Vietnamese Article.' : 'Bắt buộc trước khi xuất bản Bài viết.' )
	);
	echo '</div>';

	printf(
		'<div class="pgds-metabox__surface-confirm" data-pgds="surface-confirm" hidden><label class="pgds-metabox__choice" for="%1$s"><input type="checkbox" id="%1$s" name="%1$s" value="1"> <span>%2$s</span></label></div>',
		esc_attr( $confirm_id ),
		esc_html( $english ? 'I confirm this post should move to another content type.' : 'Tôi xác nhận chuyển bài sang loại nội dung khác.' )
	);

	printf( '<input type="hidden" data-pgds="original-surface" value="%s">', esc_attr( $classification['valid'] ? $classification['surface'] : '' ) );
	printf( '<input type="hidden" data-pgds="original-primary" value="%d">', (int) $classification['primary_id'] );
	if ( ! $classification['valid'] ) {
		printf(
			'<div class="notice notice-warning inline"><p>%s</p></div>',
			esc_html( $english ? 'This post needs a valid primary category before it can leave the Article review queue.' : 'Bài này đang cần phân loại. Hãy chọn loại nội dung và chuyên mục chính phù hợp.' )
		);
	}
	echo '</div>';
}

/**
 * Render the grouped article meta box.
 *
 * @param WP_Post $post Post.
 */
function pgds_render_meta_box( $post ) {
	$surface        = pgds_current_editorial_surface( $post->ID );
	$visible_groups = pgds_editorial_surface_groups( $surface );
	$render_groups  = array_values( array_unique( array_merge( $visible_groups, array( 'video' ) ) ) );
	$english        = 'vietnam-buddhism' === $surface;

	wp_nonce_field( 'pgds_meta_save', 'pgds_meta_nonce' );
	foreach ( $visible_groups as $group_key ) {
		printf(
			'<input type="hidden" name="pgds_meta_groups[]" value="%s"%s>',
			esc_attr( $group_key ),
			'video' === $group_key ? ' data-pgds="dynamic-video-marker"' : ''
		);
	}

	$feedback = $GLOBALS['pgds_meta_feedback'] ?? array();
	if ( (int) ( $feedback['post_id'] ?? 0 ) === (int) $post->ID ) {
		pgds_render_meta_feedback( (array) ( $feedback['codes'] ?? array() ), true );
		unset( $GLOBALS['pgds_meta_feedback'] );
	}
	pgds_render_article_warnings( pgds_get_article_warnings( $post->ID ) );

	$fields = pgds_meta_fields( $surface );
	echo '<div class="pgds-metabox">';
	printf(
		'<p class="pgds-metabox__return"><a href="%s">&larr; %s</a></p>',
		esc_url( pgds_editorial_list_url( $surface ) ),
		esc_html( $english ? 'Back to Vietnam Buddhism' : 'Quay lại danh sách ' . pgds_editorial_surfaces()[ $surface ]['label'] )
	);
	pgds_render_classification_control( $post, $surface );
	if ( 'emagazine' === $surface ) {
		echo '<div class="notice notice-info inline"><p>';
		echo esc_html__( 'Mở trình chèn Gutenberg, chọn Patterns → PGDS E-magazine để thêm tiêu đề chương, ảnh rộng/toàn chiều rộng, cặp ảnh, caption và trích dẫn.', 'pgds' );
		echo '</p></div>';
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		$checks       = array(
			'sapo'    => array( 'Sa-pô', '' !== trim( (string) get_post_meta( $post->ID, '_pgds_sapo', true ) ) ),
			'cover'   => array( 'Ảnh bìa', (bool) $thumbnail_id ),
			'caption' => array( 'Chú thích ảnh bìa', $thumbnail_id && '' !== trim( (string) wp_get_attachment_caption( $thumbnail_id ) ) ),
			'author'  => array( 'Tác giả hiển thị', '' !== trim( (string) get_post_meta( $post->ID, '_pgds_display_author', true ) ) ),
			'credit'  => array( 'Nguồn / ghi công ảnh', '' !== trim( (string) get_post_meta( $post->ID, '_pgds_source', true ) ) ),
			'chapter' => array( 'Ít nhất một tiêu đề chương', false !== strpos( (string) $post->post_content, 'pgds-emagazine-chapter' ) ),
		);
		echo '<div class="pgds-metabox__emagazine-checklist" data-pgds="emagazine-checklist"><strong>Checklist E-magazine</strong><p>Các mục này chỉ là cảnh báo biên tập, không chặn lưu hoặc xuất bản.</p><ul>';
		foreach ( $checks as $key => $check ) {
			printf(
				'<li class="%1$s" data-pgds-check="%2$s"><span aria-hidden="true">%3$s</span> %4$s</li>',
				$check[1] ? 'is-complete' : 'is-missing',
				esc_attr( $key ),
				$check[1] ? '✓' : '○',
				esc_html( $check[0] )
			);
		}
		echo '</ul></div>';
	}
	foreach ( pgds_meta_groups( $surface ) as $group_key => $group ) {
		if ( ! in_array( $group_key, $render_groups, true ) ) {
			continue;
		}
		$collapsed    = ! empty( $group['collapsed'] );
		$group_hidden = ! in_array( $group_key, $visible_groups, true );
		printf(
			'<fieldset class="pgds-metabox__group pgds-metabox__group--%s%s" data-pgds-group="%s"%s>',
			esc_attr( $group_key ),
			$collapsed ? ' is-collapsed' : '',
			esc_attr( $group_key ),
			$group_hidden ? ' hidden' : ''
		);
		if ( $collapsed ) {
			printf( '<legend><button class="pgds-metabox__group-toggle" type="button" aria-expanded="false" aria-controls="pgds-meta-group-%s">%s</button></legend>', esc_attr( $group_key ), esc_html( $group['label'] ) );
			printf( '<div id="pgds-meta-group-%s" class="pgds-metabox__group-content" hidden>', esc_attr( $group_key ) );
		} else {
			printf( '<legend>%s</legend>', esc_html( $group['label'] ) );
			echo '<div class="pgds-metabox__group-content">';
		}
		printf( '<p class="pgds-metabox__group-help">%s</p>', esc_html( $group['description'] ) );

		foreach ( $fields as $key => $field ) {
			if ( '_pgds_primary_cat' !== $key && $group_key === $field['group'] ) {
				pgds_render_meta_field( $post->ID, $key, $field, $surface );
			}
		}

		if ( 'video' === $group_key ) {
			echo '<div class="pgds-metabox__field">';
			printf( '<span class="pgds-metabox__label">%s</span>', esc_html( $english ? 'Status' : 'Trạng thái' ) );
			printf( '<output class="pgds-metabox__readonly">%s</output>', esc_html( pgds_video_status_label( $post->ID, $english ) ) );
			printf(
				'<p class="description">%s</p>',
				esc_html( $english ? 'PGDS checks the video status automatically; editors do not need to enter it.' : 'PGDS tự kiểm tra trạng thái video; người biên tập không cần nhập.' )
			);
			echo '</div>';
		}

		echo '</div>';
		echo '</fieldset>';
	}
	echo '</div>';
}

/**
 * Validate the stored classification and required publish metadata.
 *
 * @param int $post_id Post ID.
 * @return true|WP_Error
 */
function pgds_validate_editorial_publish_post( $post_id ) {
	$classification = pgds_get_editorial_classification( $post_id );
	if ( ! $classification['valid'] ) {
		return new WP_Error( 'pgds_article_category_required' );
	}

	if ( 'video' === $classification['surface'] ) {
		$youtube_id = pgds_normalize_youtube_input( get_post_meta( $post_id, '_pgds_youtube_id', true ) );
		if ( is_wp_error( $youtube_id ) || ! $youtube_id ) {
			return new WP_Error( 'pgds_video_requires_youtube' );
		}
	}

	return true;
}

/**
 * Save editor-owned article metadata after WordPress applies final categories.
 *
 * @param int          $post_id     Post ID.
 * @param WP_Post      $post        Post after the save.
 * @param bool         $update      Whether this is an existing post.
 * @param WP_Post|null $post_before Post before the save.
 */
function pgds_save_meta( $post_id, $post, $update, $post_before ) {
	unset( $update );

	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
		return;
	}
	if ( ! isset( $_POST['pgds_meta_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['pgds_meta_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'pgds_meta_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$feedback = $GLOBALS['pgds_meta_feedback'] ?? array();
	if ( (int) ( $feedback['post_id'] ?? 0 ) === (int) $post_id ) {
		unset( $GLOBALS['pgds_meta_feedback'] );
	}

	$submitted_groups = isset( $_POST['pgds_meta_groups'] ) ? (array) wp_unslash( $_POST['pgds_meta_groups'] ) : array();
	$submitted_groups = array_map( 'sanitize_key', $submitted_groups );
	$errors           = array();
	$requested_surface = isset( $_POST['pgds_surface'] ) ? pgds_sanitize_editorial_surface( wp_unslash( $_POST['pgds_surface'] ) ) : '';

	if ( in_array( 'editorial', $submitted_groups, true ) && isset( $_POST['pgds_surface'] ) ) {
		$classification = pgds_get_editorial_classification( $post_id );
		$confirmed      = isset( $_POST['pgds_surface_change_confirm'] );
		$article_slug   = isset( $_POST['pgds_article_primary_slug'] ) ? sanitize_title( wp_unslash( $_POST['pgds_article_primary_slug'] ) ) : '';
		$can_change     = true;

		if ( ! $requested_surface ) {
			$errors[]  = 'pgds_invalid_editorial_surface';
			$can_change = false;
		} elseif ( $classification['valid'] && $classification['surface'] !== $requested_surface && ! $confirmed ) {
			$errors[]  = 'pgds_surface_change_confirmation_required';
			$can_change = false;
		}

		if ( $can_change && 'publish' === $post->post_status && 'video' === $requested_surface ) {
			$youtube_raw = isset( $_POST['_pgds_youtube_id'] )
				? wp_unslash( $_POST['_pgds_youtube_id'] )
				: get_post_meta( $post_id, '_pgds_youtube_id', true );
			$youtube_id = pgds_normalize_youtube_input( $youtube_raw );
			if ( is_wp_error( $youtube_id ) || ! $youtube_id ) {
				$errors[]  = 'pgds_video_requires_youtube';
				$can_change = false;
			}
		}

		if ( $can_change ) {
			$result = pgds_apply_editorial_classification( $post_id, $requested_surface, $article_slug );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_code();
			}
		}
	} elseif ( in_array( 'editorial', $submitted_groups, true ) && isset( $_POST['_pgds_primary_cat'] ) ) {
		// Preserve the pre-surface classic form contract for compatible integrations.
		$assigned_ids = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
		$category     = pgds_validate_primary_category(
			wp_unslash( $_POST['_pgds_primary_cat'] ),
			is_array( $assigned_ids ) ? $assigned_ids : array()
		);
		if ( is_wp_error( $category ) ) {
			$errors[] = $category->get_error_code();
		} else {
			update_post_meta( $post_id, '_pgds_primary_cat', $category );
		}
	}

	if ( in_array( 'homepage', $submitted_groups, true ) ) {
		$new_featured = isset( $_POST['_pgds_is_featured'] );
		$rank_input   = isset( $_POST['_pgds_feature_rank'] ) ? wp_unslash( $_POST['_pgds_feature_rank'] ) : '';
		$rank         = pgds_validate_featured_rank( $new_featured, $rank_input );

		if ( is_wp_error( $rank ) ) {
			$errors[] = $rank->get_error_code();
		} else {
			update_post_meta( $post_id, '_pgds_is_featured', $new_featured ? '1' : '' );
			update_post_meta( $post_id, '_pgds_feature_rank', $rank );
			update_post_meta( $post_id, '_pgds_photo_story', isset( $_POST['_pgds_photo_story'] ) ? '1' : '' );
		}

		$new_popular  = isset( $_POST['_pgds_is_popular'] );
		$popular_rank = pgds_validate_popular_rank( $new_popular, isset( $_POST['_pgds_popular_rank'] ) ? wp_unslash( $_POST['_pgds_popular_rank'] ) : '' );

		if ( is_wp_error( $popular_rank ) ) {
			$errors[] = $popular_rank->get_error_code();
		} else {
			update_post_meta( $post_id, '_pgds_is_popular', $new_popular ? '1' : '' );
			update_post_meta( $post_id, '_pgds_popular_rank', $popular_rank );
		}
	}

	if ( in_array( 'video', $submitted_groups, true ) && isset( $_POST['_pgds_youtube_id'] ) ) {
		$youtube_id = pgds_normalize_youtube_input( wp_unslash( $_POST['_pgds_youtube_id'] ) );
		if ( is_wp_error( $youtube_id ) ) {
			$errors[] = $youtube_id->get_error_code();
		} elseif ( '' === $youtube_id ) {
			$classification = pgds_get_editorial_classification( $post_id );
			if ( 'publish' === $post->post_status && $classification['valid'] && 'video' === $classification['surface'] ) {
				$errors[] = 'pgds_video_requires_youtube';
			} else {
				delete_post_meta( $post_id, '_pgds_youtube_id' );
			}
		} else {
			update_post_meta( $post_id, '_pgds_youtube_id', $youtube_id );
		}
	}

	if ( in_array( 'editorial', $submitted_groups, true ) ) {
		$text_fields = array( '_pgds_source', '_pgds_display_author' );
		foreach ( $text_fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
		if ( isset( $_POST['_pgds_sapo'] ) ) {
			update_post_meta( $post_id, '_pgds_sapo', sanitize_textarea_field( wp_unslash( $_POST['_pgds_sapo'] ) ) );
		}
	}

	$was_published = $post_before instanceof WP_Post && 'publish' === $post_before->post_status;
	if ( 'publish' === $post->post_status && ! $was_published ) {
		$publish_validation = pgds_validate_editorial_publish_post( $post_id );
		if ( is_wp_error( $publish_validation ) ) {
			$errors[] = $publish_validation->get_error_code();
			remove_action( 'wp_after_insert_post', 'pgds_save_meta', 10 );
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);
			add_action( 'wp_after_insert_post', 'pgds_save_meta', 10, 4 );
		}
	}

	pgds_record_meta_feedback( $post_id, $errors );
}
add_action( 'wp_after_insert_post', 'pgds_save_meta', 10, 4 );

/**
 * Get an existing value or a default for REST preflight validation.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Metadata key.
 * @param mixed  $default Default for a new post.
 * @return mixed
 */
function pgds_rest_existing_meta( $post_id, $key, $default ) {
	return $post_id ? get_post_meta( $post_id, $key, true ) : $default;
}

/**
 * Check whether a REST-supplied synchronization value is only an unchanged echo.
 *
 * Gutenberg includes registered metadata in ordinary post saves even when an
 * editor did not touch it. Those no-op values are safe to discard; a different
 * value remains an attempted write and must still be rejected.
 *
 * @param int    $post_id  Post ID, or zero while creating a post.
 * @param string $key      Synchronization-owned metadata key.
 * @param mixed  $supplied REST-supplied value.
 * @return bool
 */
function pgds_rest_synchronized_meta_is_unchanged( $post_id, $key, $supplied ) {
	$integer_keys = array( '_pgds_youtube_dur', '_pgds_youtube_poster_id' );
	$boolean_keys = array( '_pgds_video_unavailable' );
	$default      = in_array( $key, $integer_keys, true ) ? 0 : '';
	$existing     = pgds_rest_existing_meta( $post_id, $key, $default );

	if ( in_array( $key, $integer_keys, true ) ) {
		return absint( $supplied ) === absint( $existing );
	}

	if ( in_array( $key, $boolean_keys, true ) ) {
		return rest_sanitize_boolean( $supplied ) === rest_sanitize_boolean( $existing );
	}

	return (string) $supplied === (string) $existing;
}

/**
 * Validate and normalize PGDS metadata before a REST post write begins.
 *
 * @param stdClass       $prepared_post Prepared post object.
 * @param WP_REST_Request $request       REST request.
 * @return stdClass|WP_Error
 */
function pgds_rest_validate_article_meta( $prepared_post, $request ) {
	$meta_supplied       = $request->has_param( 'meta' );
	$categories_supplied = $request->has_param( 'categories' );
	$meta = $meta_supplied ? $request->get_param( 'meta' ) : array();
	if ( ! is_array( $meta ) ) {
		return $prepared_post;
	}
	$post_id      = absint( $request['id'] );
	$rest_surface = $post_id ? pgds_get_editorial_classification( $post_id )['surface'] : 'article';

	foreach ( pgds_synchronized_meta_keys() as $key ) {
		if ( array_key_exists( $key, $meta ) ) {
			if ( ! pgds_rest_synchronized_meta_is_unchanged( $post_id, $key, $meta[ $key ] ) ) {
				return new WP_Error(
					'pgds_readonly_video_meta',
					'Thời lượng và trạng thái video do PGDS tự cập nhật và không thể sửa tại đây.',
					array( 'status' => 403 )
				);
			}

			unset( $meta[ $key ] );
		}
	}

	if ( array_key_exists( '_pgds_is_featured', $meta ) || array_key_exists( '_pgds_feature_rank', $meta ) ) {
		$featured = array_key_exists( '_pgds_is_featured', $meta )
			? rest_sanitize_boolean( $meta['_pgds_is_featured'] )
			: rest_sanitize_boolean( pgds_rest_existing_meta( $post_id, '_pgds_is_featured', false ) );
		$rank_raw = array_key_exists( '_pgds_feature_rank', $meta )
			? $meta['_pgds_feature_rank']
			: pgds_rest_existing_meta( $post_id, '_pgds_feature_rank', 0 );
		$rank = pgds_validate_featured_rank( $featured, $rank_raw );

		if ( is_wp_error( $rank ) ) {
			return new WP_Error(
				$rank->get_error_code(),
				pgds_meta_feedback_messages( $rest_surface )[ $rank->get_error_code() ],
				array( 'status' => 400 )
			);
		}
		$meta['_pgds_is_featured']  = $featured;
		$meta['_pgds_feature_rank'] = $rank;
	}

	if ( array_key_exists( '_pgds_is_popular', $meta ) || array_key_exists( '_pgds_popular_rank', $meta ) ) {
		$popular = array_key_exists( '_pgds_is_popular', $meta )
			? rest_sanitize_boolean( $meta['_pgds_is_popular'] )
			: rest_sanitize_boolean( pgds_rest_existing_meta( $post_id, '_pgds_is_popular', false ) );
		$popular_rank_raw = array_key_exists( '_pgds_popular_rank', $meta )
			? $meta['_pgds_popular_rank']
			: pgds_rest_existing_meta( $post_id, '_pgds_popular_rank', 0 );
		$popular_rank = pgds_validate_popular_rank( $popular, $popular_rank_raw );

		if ( is_wp_error( $popular_rank ) ) {
			return new WP_Error(
				$popular_rank->get_error_code(),
				pgds_meta_feedback_messages( $rest_surface )[ $popular_rank->get_error_code() ],
				array( 'status' => 400 )
			);
		}
		$meta['_pgds_is_popular']  = $popular;
		$meta['_pgds_popular_rank'] = $popular_rank;
	}

	if ( $categories_supplied ) {
		$assigned_ids = array_values( array_filter( array_map( 'absint', (array) $request->get_param( 'categories' ) ) ) );
	} else {
		$assigned_ids = $post_id ? wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) ) : array();
	}

	if ( array_key_exists( '_pgds_primary_cat', $meta ) || $categories_supplied ) {
		$category_raw = array_key_exists( '_pgds_primary_cat', $meta )
			? $meta['_pgds_primary_cat']
			: pgds_rest_existing_meta( $post_id, '_pgds_primary_cat', 0 );
		$category = pgds_validate_primary_category( $category_raw, is_array( $assigned_ids ) ? $assigned_ids : array() );
		if ( is_wp_error( $category ) ) {
			return new WP_Error(
				$category->get_error_code(),
				pgds_meta_feedback_messages( $rest_surface )[ $category->get_error_code() ],
				array( 'status' => 400 )
			);
		}
		if ( array_key_exists( '_pgds_primary_cat', $meta ) ) {
			$meta['_pgds_primary_cat'] = $category;
		}
	}

	if ( array_key_exists( '_pgds_youtube_id', $meta ) ) {
		$youtube_id = pgds_normalize_youtube_input( $meta['_pgds_youtube_id'] );
		if ( is_wp_error( $youtube_id ) ) {
			return new WP_Error(
				$youtube_id->get_error_code(),
				pgds_meta_feedback_messages( $rest_surface )[ $youtube_id->get_error_code() ],
				array( 'status' => 400 )
			);
		}
		$meta['_pgds_youtube_id'] = $youtube_id;
	}

	$existing_post   = $post_id ? get_post( $post_id ) : null;
	$current_status  = $existing_post instanceof WP_Post ? $existing_post->post_status : '';
	$requested_status = $request->has_param( 'status' ) ? sanitize_key( (string) $request->get_param( 'status' ) ) : '';
	$final_status    = $requested_status ? $requested_status : $current_status;
	$workflow_change = $categories_supplied || array_key_exists( '_pgds_primary_cat', $meta ) || array_key_exists( '_pgds_youtube_id', $meta );
	$validate_publish = 'publish' === $final_status && ( ! $post_id || 'publish' !== $current_status || $workflow_change );

	if ( $validate_publish ) {
		$primary_id = array_key_exists( '_pgds_primary_cat', $meta )
			? absint( $meta['_pgds_primary_cat'] )
			: absint( pgds_rest_existing_meta( $post_id, '_pgds_primary_cat', 0 ) );
		$classification = pgds_classify_editorial_values( $primary_id, is_array( $assigned_ids ) ? $assigned_ids : array() );

		if ( ! $classification['valid'] ) {
			return new WP_Error(
				'pgds_article_category_required',
				pgds_meta_feedback_messages( $rest_surface )['pgds_article_category_required'],
				array( 'status' => 400 )
			);
		}

		if ( 'video' === $classification['surface'] ) {
			$youtube_raw = array_key_exists( '_pgds_youtube_id', $meta )
				? $meta['_pgds_youtube_id']
				: pgds_rest_existing_meta( $post_id, '_pgds_youtube_id', '' );
			$youtube_id = pgds_normalize_youtube_input( $youtube_raw );
			if ( is_wp_error( $youtube_id ) || ! $youtube_id ) {
				return new WP_Error(
					'pgds_video_requires_youtube',
					pgds_meta_feedback_messages( 'video' )['pgds_video_requires_youtube'],
					array( 'status' => 400 )
				);
			}
		}
	}

	if ( $meta_supplied ) {
		$request->set_param( 'meta', $meta );
	}

	return $prepared_post;
}

/**
 * Delete the canonical YouTube metadata row after an intentional REST clear.
 *
 * WordPress's REST meta controller stores an empty string for a registered string
 * key. The Posts-list Video filter uses metadata existence, so retaining that row
 * would make a cleared article appear to have a video.
 *
 * @param WP_Post         $post     Inserted or updated post.
 * @param WP_REST_Request $request  REST request.
 * @param bool            $creating Whether this is a new post.
 * @return void
 */
function pgds_rest_clear_empty_youtube_meta( $post, $request, $creating ) {
	unset( $creating );

	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || ! $request->has_param( 'meta' ) ) {
		return;
	}

	$meta = $request->get_param( 'meta' );
	if ( ! is_array( $meta ) || ! array_key_exists( '_pgds_youtube_id', $meta ) || '' !== $meta['_pgds_youtube_id'] ) {
		return;
	}

	delete_post_meta( $post->ID, '_pgds_youtube_id' );
}

add_action( 'rest_after_insert_post', 'pgds_rest_clear_empty_youtube_meta', 10, 3 );
add_filter( 'rest_pre_insert_post', 'pgds_rest_validate_article_meta', 10, 2 );

/**
 * Register YouTube metadata for Lời Phật dạy (teaching link cards).
 */
function pgds_register_teaching_meta() {
	register_post_meta(
		'pgds_teaching',
		'_pgds_youtube_id',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => static function ( $allowed, $meta_key, $post_id, $user_id ) {
				unset( $allowed, $meta_key );
				if ( $post_id ) {
					return user_can( $user_id, 'edit_post', $post_id );
				}

				return user_can( $user_id, 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'pgds_register_teaching_meta' );

/**
 * Meta box for a Lời Phật dạy YouTube link card.
 */
function pgds_add_teaching_meta_box() {
	add_meta_box(
		'pgds_teaching_youtube',
		__( 'Liên kết YouTube', 'pgds' ),
		'pgds_render_teaching_meta_box',
		'pgds_teaching',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'pgds_add_teaching_meta_box' );

/**
 * Render the teaching YouTube meta box.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function pgds_render_teaching_meta_box( $post ) {
	wp_nonce_field( 'pgds_teaching_meta_save', 'pgds_teaching_meta_nonce' );
	$value = get_post_meta( $post->ID, '_pgds_youtube_id', true );
	?>
	<p>
		<label for="pgds_teaching_youtube_id"><strong><?php esc_html_e( 'Video YouTube', 'pgds' ); ?></strong></label>
		<input type="url"
			id="pgds_teaching_youtube_id"
			name="_pgds_youtube_id"
			value="<?php echo esc_attr( $value ); ?>"
			class="widefat"
			placeholder="https://www.youtube.com/watch?v=..."
			pattern="https?://.*|[A-Za-z0-9_-]{11}" />
	</p>
	<p class="description">
		<?php esc_html_e( 'Dán đường dẫn YouTube hoặc mã video 11 ký tự. Người đọc bấm tiêu đề sẽ mở video này trên YouTube. Không có link thì trang chi tiết sẽ chuyển về trang chủ.', 'pgds' ); ?>
	</p>
	<?php
}

/**
 * Save the teaching YouTube link.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function pgds_save_teaching_meta( $post_id ) {
	if ( ! isset( $_POST['pgds_teaching_meta_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['pgds_teaching_meta_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'pgds_teaching_meta_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['_pgds_youtube_id'] ) ) {
		return;
	}

	$youtube_id = pgds_normalize_youtube_input( wp_unslash( $_POST['_pgds_youtube_id'] ) );
	if ( is_wp_error( $youtube_id ) ) {
		return;
	}

	if ( '' === $youtube_id ) {
		delete_post_meta( $post_id, '_pgds_youtube_id' );
		return;
	}

	update_post_meta( $post_id, '_pgds_youtube_id', $youtube_id );
}
add_action( 'save_post_pgds_teaching', 'pgds_save_teaching_meta' );

/**
 * Admin list columns for Lời Phật dạy.
 *
 * @param array $columns Columns.
 * @return array
 */
function pgds_teaching_admin_columns( $columns ) {
	$columns['pgds_teaching_youtube'] = __( 'YouTube', 'pgds' );
	return $columns;
}
add_filter( 'manage_pgds_teaching_posts_columns', 'pgds_teaching_admin_columns' );

/**
 * Render the YouTube column on the teaching list table.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function pgds_teaching_admin_column_content( $column, $post_id ) {
	if ( 'pgds_teaching_youtube' !== $column ) {
		return;
	}

	$watch = pgds_youtube_watch_url( get_post_meta( $post_id, '_pgds_youtube_id', true ) );
	if ( ! $watch ) {
		echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Chưa có link YouTube', 'pgds' ) . '</span>';
		return;
	}

	printf(
		'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
		esc_url( $watch ),
		esc_html__( 'Xem video', 'pgds' )
	);
}
add_action( 'manage_pgds_teaching_posts_custom_column', 'pgds_teaching_admin_column_content', 10, 2 );
