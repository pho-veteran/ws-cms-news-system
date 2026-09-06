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
function pgds_meta_fields() {
	return array(
		'_pgds_sapo'          => array(
			'group'    => 'editorial',
			'label'    => 'Sa-pô',
			'help'     => 'Viết phần tóm tắt ngắn xuất hiện cùng bài trên trang chủ và đầu bài viết.',
			'type'     => 'textarea',
			'editable' => true,
		),
		'_pgds_primary_cat'   => array(
			'group'    => 'editorial',
			'label'    => 'Chuyên mục chính',
			'help'     => 'Chỉ hiện các chuyên mục đã được đánh dấu trong mục Chuyên mục của bài viết. Hãy chọn chuyên mục trước nếu danh sách đang trống.',
			'type'     => 'category',
			'editable' => true,
		),
		'_pgds_source'        => array(
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
		'_pgds_is_featured'   => array(
			'group'    => 'homepage',
			'label'    => 'Tin nổi bật',
			'help'     => 'Bật để bài viết có thể xuất hiện trong khối Tin nổi bật trên trang chủ.',
			'type'     => 'checkbox',
			'editable' => true,
		),
		'_pgds_feature_rank'  => array(
			'group'    => 'homepage',
			'label'    => 'Vị trí Tin nổi bật',
			'help'     => 'Chọn từ 1 đến 4. Vị trí 1 là tin chính; các vị trí 2–4 là tin phụ.',
			'type'     => 'number',
			'editable' => true,
			'min'      => 1,
			'max'      => 4,
			'step'     => 1,
		),
		'_pgds_photo_story'   => array(
			'group'    => 'homepage',
			'label'    => 'Tin ảnh',
			'help'     => 'Bật để bài viết có thể xuất hiện trong khối Tin ảnh.',
			'type'     => 'checkbox',
			'editable' => true,
		),
		'_pgds_youtube_id'    => array(
			'group'    => 'video',
			'label'    => 'Video YouTube',
			'help'     => 'Dán đường dẫn YouTube hoặc mã video gồm 11 ký tự. Mỗi bài chỉ dùng một video.',
			'type'     => 'text',
			'editable' => true,
		),
		'_pgds_youtube_dur'   => array(
			'group'    => 'video',
			'label'    => 'Thời lượng',
			'help'     => 'PGDS tự cập nhật thời lượng; người biên tập không cần nhập.',
			'type'     => 'duration',
			'editable' => false,
		),
	);
}

/**
 * Meta box group definitions.
 *
 * @return array
 */
function pgds_meta_groups() {
	return array(
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
		'_pgds_source'            => 'string',
		'_pgds_display_author'    => 'string',
	);
	$sanitizers = array(
		'_pgds_sapo' => 'sanitize_textarea_field',
		'string'     => 'sanitize_text_field',
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
	/*
	 * The block editor renders normal-context legacy boxes in the drawer below the
	 * content. Side-context boxes are hidden there, so this context is intentional.
	 */
	add_meta_box(
		'pgds_article_meta',
		__( 'Nội dung và hiển thị PGDS', 'pgds' ),
		'pgds_render_meta_box',
		'post',
		'normal',
		'high'
	);
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
	$term          = get_term( $term_id, 'category' );

	if ( is_wp_error( $term ) || ! $term || ! in_array( $term_id, $assigned_ids, true ) ) {
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
 * @return string
 */
function pgds_article_placement_label( $featured, $photo_story ) {
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

	if ( $featured || $photo_story ) {
		$placement = pgds_article_placement_label( $featured, $photo_story );

		if ( ! has_post_thumbnail( $post_id ) ) {
			$warnings[] = array(
				'code'    => 'pgds_missing_featured_image',
				'message' => sprintf( '%s chưa có ảnh đại diện. Bài vẫn được lưu, nhưng thẻ bài có thể thiếu ảnh.', $placement ),
			);
		}

		if ( '' === trim( (string) get_post_meta( $post_id, '_pgds_sapo', true ) ) ) {
			$warnings[] = array(
				'code'    => 'pgds_missing_sapo',
				'message' => sprintf( '%s chưa có sa-pô. Bài vẫn được lưu, nhưng phần giới thiệu có thể bị trống.', $placement ),
			);
		}
	}

	if ( $featured ) {
		$rank        = (int) get_post_meta( $post_id, '_pgds_feature_rank', true );
		$conflict_id = pgds_find_featured_rank_conflict( $post_id, $rank );

		if ( $conflict_id ) {
			$warning = array(
				'code'        => 'pgds_duplicate_featured_rank',
				'message'     => sprintf( 'Một bài đã xuất bản khác đang dùng vị trí Tin nổi bật %d. Cả hai bài vẫn được giữ nguyên.', $rank ),
				'conflict_id' => $conflict_id,
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
function pgds_meta_feedback_messages() {
	return array(
		'pgds_invalid_featured_rank'   => 'Thiết lập Tin nổi bật chưa được cập nhật. Khi bật Tin nổi bật, hãy chọn vị trí từ 1 đến 4. Giá trị hợp lệ trước đó được giữ nguyên.',
		'pgds_invalid_primary_category' => 'Chuyên mục chính chưa được cập nhật. Hãy chọn một chuyên mục đã được đánh dấu cho bài viết. Giá trị hợp lệ trước đó được giữ nguyên.',
		'pgds_invalid_youtube'           => 'Video YouTube chưa được cập nhật. Hãy dán đúng đường dẫn YouTube hoặc mã video gồm 11 ký tự. Video hợp lệ trước đó được giữ nguyên.',
	);
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
function pgds_format_video_duration( $seconds ) {
	$seconds = absint( $seconds );
	if ( ! $seconds ) {
		return 'Chưa có dữ liệu';
	}

	$hours   = (int) floor( $seconds / HOUR_IN_SECONDS );
	$minutes = (int) floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
	$seconds = $seconds % MINUTE_IN_SECONDS;

	return $hours ? sprintf( '%d:%02d:%02d', $hours, $minutes, $seconds ) : sprintf( '%d:%02d', $minutes, $seconds );
}

/**
 * Get the synchronized-video status label.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function pgds_video_status_label( $post_id ) {
	if ( ! get_post_meta( $post_id, '_pgds_youtube_id', true ) ) {
		return 'Chưa gắn video';
	}
	if ( '1' === get_post_meta( $post_id, '_pgds_video_unavailable', true ) ) {
		return 'Video không còn khả dụng';
	}
	if (
		get_post_meta( $post_id, '_pgds_youtube_dur', true ) ||
		get_post_meta( $post_id, '_pgds_youtube_title', true ) ||
		get_post_meta( $post_id, '_pgds_youtube_poster_id', true )
	) {
		return 'Đã đồng bộ';
	}

	return 'Đang chờ đồng bộ';
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
			printf( ' <a href="%s">%s</a>', esc_url( $warning['edit_url'] ), esc_html__( 'Mở bài đang trùng vị trí', 'pgds' ) );
		}
		echo '</p></div>';
	}
}

function pgds_render_meta_field( $post_id, $key, array $field ) {
	$value = get_post_meta( $post_id, $key, true );
	$id    = esc_attr( $key );

	echo '<div class="pgds-metabox__field">';
	printf( '<label class="pgds-metabox__label" for="%s">%s</label>', $id, esc_html( $field['label'] ) );

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
				esc_html__( 'Bật', 'pgds' )
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

		case 'duration':
			printf( '<output id="%s" class="pgds-metabox__readonly">%s</output>', $id, esc_html( pgds_format_video_duration( $value ) ) );
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

	if ( ! empty( $field['help'] ) ) {
		printf( '<p class="description" id="%s-help">%s</p>', $id, esc_html( $field['help'] ) );
	}
	if ( 'number' === $field['type'] ) {
		echo '<p class="description pgds-metabox__feature-rank-state" aria-live="polite"></p>';
	}
	echo '</div>';
}

/**
 * Render the grouped article meta box.
 *
 * @param WP_Post $post Post.
 */
function pgds_render_meta_box( $post ) {
	wp_nonce_field( 'pgds_meta_save', 'pgds_meta_nonce' );

	$feedback = $GLOBALS['pgds_meta_feedback'] ?? array();
	if ( (int) ( $feedback['post_id'] ?? 0 ) === (int) $post->ID ) {
		pgds_render_meta_feedback( (array) ( $feedback['codes'] ?? array() ), true );
		unset( $GLOBALS['pgds_meta_feedback'] );
	}
	pgds_render_article_warnings( pgds_get_article_warnings( $post->ID ) );

	$fields = pgds_meta_fields();
	echo '<div class="pgds-metabox">';
	foreach ( pgds_meta_groups() as $group_key => $group ) {
		printf( '<fieldset class="pgds-metabox__group pgds-metabox__group--%s">', esc_attr( $group_key ) );
		printf( '<legend>%s</legend>', esc_html( $group['label'] ) );
		printf( '<p class="pgds-metabox__group-help">%s</p>', esc_html( $group['description'] ) );

		foreach ( $fields as $key => $field ) {
			if ( $group_key === $field['group'] ) {
				pgds_render_meta_field( $post->ID, $key, $field );
			}
		}

		if ( 'video' === $group_key ) {
			echo '<div class="pgds-metabox__field">';
			echo '<span class="pgds-metabox__label">Trạng thái</span>';
			printf( '<output class="pgds-metabox__readonly">%s</output>', esc_html( pgds_video_status_label( $post->ID ) ) );
			echo '<p class="description">PGDS tự kiểm tra trạng thái video; người biên tập không cần nhập.</p>';
			echo '</div>';
		}

		echo '</fieldset>';
	}
	echo '</div>';
}

/**
 * Save editor-owned article metadata after WordPress applies final categories.
 *
 * @param int          $post_id    Post ID.
 * @param WP_Post      $post       Post after the save.
 * @param bool         $update     Whether this is an existing post.
 * @param WP_Post|null $post_before Post before the save.
 */
function pgds_save_meta( $post_id, $post, $update, $post_before ) {
	unset( $update, $post_before );

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

	$errors       = array();
	$new_featured = isset( $_POST['_pgds_is_featured'] );
	$rank_input   = isset( $_POST['_pgds_feature_rank'] ) ? wp_unslash( $_POST['_pgds_feature_rank'] ) : '';
	$rank         = pgds_validate_featured_rank( $new_featured, $rank_input );

	if ( is_wp_error( $rank ) ) {
		$errors[] = $rank->get_error_code();
	} else {
		update_post_meta( $post_id, '_pgds_is_featured', $new_featured ? '1' : '' );
		update_post_meta( $post_id, '_pgds_feature_rank', $rank );
	}

	if ( isset( $_POST['_pgds_primary_cat'] ) ) {
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

	if ( isset( $_POST['_pgds_youtube_id'] ) ) {
		$youtube_id = pgds_normalize_youtube_input( wp_unslash( $_POST['_pgds_youtube_id'] ) );
		if ( is_wp_error( $youtube_id ) ) {
			$errors[] = $youtube_id->get_error_code();
		} elseif ( '' === $youtube_id ) {
			delete_post_meta( $post_id, '_pgds_youtube_id' );
		} else {
			update_post_meta( $post_id, '_pgds_youtube_id', $youtube_id );
		}
	}

	$text_fields = array( '_pgds_source', '_pgds_display_author' );
	foreach ( $text_fields as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
	if ( isset( $_POST['_pgds_sapo'] ) ) {
		update_post_meta( $post_id, '_pgds_sapo', sanitize_textarea_field( wp_unslash( $_POST['_pgds_sapo'] ) ) );
	}
	update_post_meta( $post_id, '_pgds_photo_story', isset( $_POST['_pgds_photo_story'] ) ? '1' : '' );

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
 * Validate and normalize PGDS metadata before a REST post write begins.
 *
 * @param stdClass       $prepared_post Prepared post object.
 * @param WP_REST_Request $request       REST request.
 * @return stdClass|WP_Error
 */
function pgds_rest_validate_article_meta( $prepared_post, $request ) {
	$meta_supplied       = $request->has_param( 'meta' );
	$categories_supplied = $request->has_param( 'categories' );

	if ( ! $meta_supplied && ! $categories_supplied ) {
		return $prepared_post;
	}

	$meta = $meta_supplied ? $request->get_param( 'meta' ) : array();
	if ( ! is_array( $meta ) ) {
		return $prepared_post;
	}

	foreach ( pgds_synchronized_meta_keys() as $key ) {
		if ( array_key_exists( $key, $meta ) ) {
			return new WP_Error(
				'pgds_readonly_video_meta',
				'Thời lượng và trạng thái video do PGDS tự cập nhật và không thể sửa tại đây.',
				array( 'status' => 403 )
			);
		}
	}

	$post_id = absint( $request['id'] );
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
				pgds_meta_feedback_messages()[ $rank->get_error_code() ],
				array( 'status' => 400 )
			);
		}
		$meta['_pgds_is_featured']  = $featured;
		$meta['_pgds_feature_rank'] = $rank;
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
				pgds_meta_feedback_messages()[ $category->get_error_code() ],
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
				pgds_meta_feedback_messages()[ $youtube_id->get_error_code() ],
				array( 'status' => 400 )
			);
		}
		$meta['_pgds_youtube_id'] = $youtube_id;
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
