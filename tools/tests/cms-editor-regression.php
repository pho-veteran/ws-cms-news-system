<?php
/**
 * Exercise the PGDS article-editor contract through a booted WordPress instance.
 *
 * Run with tools/tests/cms-editor-regression.sh after the local setup script. All
 * records created here use a unique prefix and are permanently deleted on exit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $pgds_cms_editor_failures, $pgds_cms_editor_posts, $pgds_cms_editor_terms, $pgds_cms_editor_users, $pgds_cms_editor_user_id;

$pgds_cms_editor_failures = 0;
$pgds_cms_editor_posts    = array();
$pgds_cms_editor_terms    = array();
$pgds_cms_editor_users    = array();
$pgds_cms_editor_user_id  = get_current_user_id();

/**
 * Report one assertion result.
 *
 * @param bool   $condition Assertion result.
 * @param string $message   Assertion description.
 * @return void
 */
function pgds_cms_editor_assert( $condition, $message ) {
	global $pgds_cms_editor_failures;

	if ( $condition ) {
		WP_CLI::log( sprintf( 'PASS: %s', $message ) );
		return;
	}

	++$pgds_cms_editor_failures;
	WP_CLI::warning( sprintf( 'FAIL: %s', $message ) );
}

/**
 * Assert one stored meta value without depending on WordPress's string storage format.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param mixed  $value   Expected value.
 * @param string $label   Assertion description.
 * @return void
 */
function pgds_cms_editor_assert_meta( $post_id, $key, $value, $label ) {
	$actual = get_post_meta( $post_id, $key, true );
	pgds_cms_editor_assert( (string) $value === (string) $actual, $label );
}

/**
 * Submit editor form values to the installed classic-save callback.
 *
 * @param int         $post_id    Post ID.
 * @param array       $fields     Submitted form values.
 * @param string|null $nonce      Nonce value, null to omit, or "valid" to generate one.
 * @param WP_Post|null $post       Post context override.
 * @param array|null   $groups     Rendered PGDS groups, null to omit the marker.
 * @return void
 */
function pgds_cms_editor_submit( $post_id, array $fields, $nonce = 'valid', $post = null, $groups = array( 'editorial', 'homepage', 'video' ) ) {
	$previous_post = $_POST;
	$_POST          = $fields;
	if ( null !== $groups ) {
		$_POST['pgds_meta_groups'] = $groups;
	}
	if ( 'valid' === $nonce ) {
		$_POST['pgds_meta_nonce'] = wp_create_nonce( 'pgds_meta_save' );
	} elseif ( null !== $nonce ) {
		$_POST['pgds_meta_nonce'] = $nonce;
	}

	try {
		$post = $post instanceof WP_Post ? $post : get_post( $post_id );
		pgds_save_meta( $post_id, $post, true, $post );
	} finally {
		$_POST = $previous_post;
	}
}

/**
 * Dispatch an authenticated REST request through WordPress.
 *
 * @param string $method HTTP method.
 * @param string $route  REST route.
 * @param array  $body   JSON body.
 * @return WP_REST_Response
 */
function pgds_cms_editor_rest_request( $method, $route, array $body ) {
	$request = new WP_REST_Request( $method, $route );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( $body ) );

	return rest_do_request( $request );
}

/**
 * Find post IDs produced by an admin-list query after applying one PGDS filter.
 *
 * @param string $filter Filter slug.
 * @return int[]
 */
function pgds_cms_editor_filter_post_ids( $filter ) {
	global $pagenow, $wp_the_query;

	$previous_pagenow   = $pagenow;
	$previous_get       = $_GET;
	$previous_wp_query  = $GLOBALS['wp_query'] ?? null;
	$previous_the_query = $wp_the_query;
	$pagenow            = 'edit.php';
	$_GET                = array( 'pgds_filter' => $filter );
	set_current_screen( 'edit-post' );

	try {
		$query = new WP_Query();
		$query->init();
		$query->set( 'post_type', 'post' );
		$query->set( 'post_status', 'any' );
		$query->set( 'posts_per_page', -1 );
		$query->set( 'fields', 'ids' );
		$wp_the_query          = $query;
		$GLOBALS['wp_query']   = $query;
		pgds_admin_filter_apply( $query );
		$query->get_posts();

		return array_map( 'intval', $query->posts );
	} finally {
		$pagenow             = $previous_pagenow;
		$_GET                = $previous_get;
		$wp_the_query        = $previous_the_query;
		$GLOBALS['wp_query'] = $previous_wp_query;
	}
}

/**
 * Find post IDs returned by one editorial-surface SQL predicate.
 *
 * @param string $surface        Surface key.
 * @param string $classification Optional classification filter.
 * @return int[]
 */
function pgds_cms_editor_surface_post_ids( $surface, $classification = '' ) {
	$query = new WP_Query();
	$query->init();
	$query->set( 'post_type', 'post' );
	$query->set( 'post_status', 'any' );
	$query->set( 'posts_per_page', -1 );
	$query->set( 'fields', 'ids' );
	$query->set( 'pgds_surface', $surface );
	if ( $classification ) {
		$query->set( 'pgds_classification', $classification );
	}
	$query->get_posts();

	return array_map( 'intval', $query->posts );
}

/**
 * Assert a REST error code and HTTP status.
 *
 * @param WP_REST_Response $response      REST response.
 * @param string           $expected_code Expected error code.
 * @param int              $expected_status Expected HTTP status.
 * @param string           $label         Assertion description.
 * @return void
 */
function pgds_cms_editor_assert_rest_error( $response, $expected_code, $expected_status, $label ) {
	$data = $response->get_data();
	pgds_cms_editor_assert(
		$expected_status === $response->get_status() &&
		$expected_code === ( $data['code'] ?? '' ),
		$label
	);
}

/**
 * Render the standard article template for one fixture post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function pgds_cms_editor_render_article( $post_id ) {
	global $post;

	$previous_post = $post;
	$post          = get_post( $post_id );
	setup_postdata( $post );

	try {
		ob_start();
		get_template_part( 'template-parts/content-single-article' );
		return ob_get_clean();
	} finally {
		wp_reset_postdata();
		$post = $previous_post;
	}
}

/**
 * Render the E-magazine template for one fixture post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function pgds_cms_editor_render_emagazine( $post_id ) {
	global $post;

	$previous_post = $post;
	$post          = get_post( $post_id );
	setup_postdata( $post );

	try {
		ob_start();
		get_template_part( 'template-parts/content-single-emagazine' );
		return ob_get_clean();
	} finally {
		wp_reset_postdata();
		$post = $previous_post;
	}
}

function pgds_cms_editor_cleanup() {
	global $pgds_cms_editor_posts, $pgds_cms_editor_users, $pgds_cms_editor_user_id;

	foreach ( array_reverse( $pgds_cms_editor_posts ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}
	foreach ( array_reverse( $pgds_cms_editor_users ) as $user_id ) {
		wp_delete_user( $user_id );
	}
	wp_set_current_user( $pgds_cms_editor_user_id );
}

try {
	$required_functions = array(
		'pgds_meta_fields',
		'pgds_meta_groups',
		'pgds_synchronized_meta_keys',
		'pgds_validate_featured_rank',
		'pgds_validate_primary_category',
		'pgds_normalize_youtube_input',
		'pgds_get_article_warnings',
		'pgds_save_meta',
		'pgds_remove_menu_management',
		'pgds_editorial_surfaces',
		'pgds_get_editorial_classification',
		'pgds_apply_editorial_classification',
		'pgds_admin_surface_where',
		'pgds_auto_approve_reader_comment',
		'pgds_comments_per_page',
		'pgds_enable_comment_pagination',
		'pgds_filter_comments_per_page',
		'pgds_filter_default_comments_page',
		'pgds_comment_page_count',
		'pgds_comment_card',
		'pgds_rest_synchronized_meta_is_unchanged',
	);
	foreach ( $required_functions as $function ) {
		pgds_cms_editor_assert( function_exists( $function ), sprintf( '%s is available to the article editor', $function ) );
	}
	if ( array_filter( $required_functions, static function ( $function ) {
		return ! function_exists( $function );
	} ) ) {
		throw new RuntimeException( 'The current theme does not expose the approved article-editor API.' );
	}

	$logo_path = PGDS_DIR . '/assets/images/pgds-logo.png';
	pgds_cms_editor_assert( defined( 'PGDS_LOGO_URI' ), 'theme exposes a static logo URL' );
	pgds_cms_editor_assert( is_readable( $logo_path ), 'static logo asset exists in the theme' );
	pgds_cms_editor_assert(
		is_readable( $logo_path ) && 'cd0412ca0008111f7677eede6a5e4cce94200df1677c10ac949b8cb0283c39a1' === hash_file( 'sha256', $logo_path ),
		'static logo matches the expected landing-page artwork'
	);
	pgds_cms_editor_assert( ! current_theme_supports( 'custom-logo' ), 'site logo is not backed by WordPress custom-logo data' );
	$header_source = (string) file_get_contents( PGDS_DIR . '/header.php' );
	pgds_cms_editor_assert(
		false !== strpos( $header_source, 'PGDS_LOGO_URI' ) &&
		false === strpos( $header_source, 'the_custom_logo' ) &&
		false === strpos( $header_source, 'has_custom_logo' ),
		'header renders the static logo without a Media Library fallback'
	);

	$administrators = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ids',
		)
	);
	if ( empty( $administrators ) ) {
		throw new RuntimeException( 'The regression suite requires one administrator account.' );
	}
	wp_set_current_user( (int) $administrators[0] );

	$token        = strtolower( str_replace( '-', '', wp_generate_uuid4() ) );
	$valid_term   = pgds_category_term( 'tin-phat-su' );
	$invalid_term = pgds_category_term( 'video' );
	if ( ! $valid_term instanceof WP_Term || ! $invalid_term instanceof WP_Term ) {
		throw new RuntimeException( 'The regression suite requires the canonical categories.' );
	}
		$valid_category   = array( 'term_id' => (int) $valid_term->term_id );
		$invalid_category = array( 'term_id' => (int) $invalid_term->term_id );

		$related_matching_ids = array();
		for ( $index = 1; $index <= 4; ++$index ) {
			$related_post_id = wp_insert_post(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'post_title'     => sprintf( 'PGDS related matching %d %s', $index, $token ),
					'post_content'   => 'Temporary related-post regression fixture.',
					'comment_status' => 'closed',
				),
				true
			);
			if ( is_wp_error( $related_post_id ) ) {
				throw new RuntimeException( 'The regression suite could not create its matching related fixture.' );
			}
			$pgds_cms_editor_posts[] = (int) $related_post_id;
			$related_matching_ids[]  = (int) $related_post_id;
			wp_set_post_categories( $related_post_id, array( (int) $valid_term->term_id ) );
			update_post_meta( $related_post_id, '_pgds_primary_cat', (int) $valid_term->term_id );
		}

		$related_mismatch_id = wp_insert_post(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post_title'     => 'PGDS related mismatched primary ' . $token,
				'post_content'   => 'Temporary related-post regression fixture.',
				'comment_status' => 'closed',
			),
			true
		);
		if ( is_wp_error( $related_mismatch_id ) ) {
			throw new RuntimeException( 'The regression suite could not create its mismatched related fixture.' );
		}
		$pgds_cms_editor_posts[] = (int) $related_mismatch_id;
		wp_set_post_categories( $related_mismatch_id, array( (int) $valid_term->term_id, (int) $invalid_term->term_id ) );
		update_post_meta( $related_mismatch_id, '_pgds_primary_cat', (int) $invalid_term->term_id );

		$related_current_id = wp_insert_post(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post_author'    => 0,
				'post_title'     => 'PGDS related current ' . $token,
				'post_content'   => 'Temporary related-post regression fixture.',
				'comment_status' => 'closed',
			),
			true
		);
		if ( is_wp_error( $related_current_id ) ) {
			throw new RuntimeException( 'The regression suite could not create its related current fixture.' );
		}
		$pgds_cms_editor_posts[] = (int) $related_current_id;
		wp_set_post_categories( $related_current_id, array( (int) $valid_term->term_id ) );
		update_post_meta( $related_current_id, '_pgds_primary_cat', (int) $valid_term->term_id );
		delete_post_meta( $related_current_id, '_pgds_display_author' );

		$related_markup = pgds_cms_editor_render_article( $related_current_id );
		preg_match(
			'/<section\b[^>]*\baria-labelledby="pgds-related-title"[^>]*>.*?<\/section>/s',
			$related_markup,
			$related_section_match
		);
		$related_section = $related_section_match[0] ?? '';
		preg_match_all(
			'/<article\b[^>]*\bclass="[^"]*\bpgds-card\b[^"]*"[^>]*>/s',
			$related_section,
			$related_cards
		);
		$related_card_count = count( $related_cards[0] );

		pgds_cms_editor_assert( '' !== $related_section, 'article render includes a related-articles section when matches exist' );
		pgds_cms_editor_assert( 3 === $related_card_count, 'related articles render exactly three cards when four primary-category matches exist' );
		pgds_cms_editor_assert( false === strpos( $related_section, esc_url( get_permalink( $related_current_id ) ) ), 'related articles exclude the current article' );
		pgds_cms_editor_assert( false === strpos( $related_section, esc_url( get_permalink( $related_mismatch_id ) ) ), 'related articles exclude posts with a different primary category' );
		foreach ( $related_matching_ids as $related_post_id ) {
			$matching_permalink  = esc_url( get_permalink( $related_post_id ) );
			$matching_card_count = substr_count( $related_section, $matching_permalink );
			pgds_cms_editor_assert( $matching_card_count <= 2, 'related cards contain each permitted fixture at most once' );
		}
		pgds_cms_editor_assert( false === strpos( $related_markup, 'pgds-article__author' ), 'article render omits an empty display-author paragraph' );
		pgds_cms_editor_assert( false === strpos( $related_markup, 'id="comments"' ), 'closed article without comments omits an empty comments section' );
		pgds_cms_editor_assert(
			1 === preg_match( '/<time\b[^>]*\bpublish-time\b[^>]*>\s*' . preg_quote( get_the_date( 'Y-m-d H:i:s', $related_current_id ), '/' ) . '\s*<\/time>/', $related_markup ),
			'article detail renders an absolute publication timestamp'
		);


		$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => 'PGDS CMS editor regression ' . $token,
			'post_content' => 'Temporary editor-regression fixture.',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		throw new RuntimeException( 'The regression suite could not create its draft post.' );
	}
	$pgds_cms_editor_posts[] = (int) $post_id;
	wp_set_post_categories( $post_id, array( (int) $valid_category['term_id'] ) );

	pgds_cms_editor_assert( 8 === pgds_comments_per_page(), 'reader comments use the theme pagination size' );
	pgds_cms_editor_assert( true === (bool) get_option( 'page_comments' ), 'WordPress canonical comment pagination is enabled' );
	pgds_cms_editor_assert( 8 === (int) get_option( 'comments_per_page' ), 'WordPress comment links use the theme pagination size' );
	pgds_cms_editor_assert( 'oldest' === get_option( 'default_comments_page' ), 'core page numbering starts with the first newest-sorted comment slice' );
	pgds_cms_editor_assert(
		'spam' === pgds_auto_approve_reader_comment( 'spam', array( 'comment_type' => 'comment' ) ),
		'comment auto-approval preserves an explicit spam decision'
	);
	wp_set_current_user( 0 );
	$comment_id = wp_new_comment(
		array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => 'Regression reader',
			'comment_author_email' => sprintf( 'reader-%s@example.test', $token ),
			'comment_author_url'   => '',
			'comment_author_IP'    => '127.0.0.1',
			'comment_content'      => 'A unique reader comment for automatic approval ' . $token,
			'comment_type'         => 'comment',
		),
		true
	);
	wp_set_current_user( (int) $administrators[0] );
	pgds_cms_editor_assert( ! is_wp_error( $comment_id ) && $comment_id > 0, 'reader can submit a comment' );
	if ( ! is_wp_error( $comment_id ) && $comment_id > 0 ) {
		$comment = get_comment( $comment_id );
		pgds_cms_editor_assert( $comment instanceof WP_Comment && '1' === (string) $comment->comment_approved, 'new reader comment is approved automatically' );
		pgds_cms_editor_assert( current_user_can( 'edit_comment', $comment_id ), 'administrator can manage the comment in CMS' );
		pgds_cms_editor_assert( wp_delete_comment( $comment_id, true ), 'administrator can delete the comment through WordPress' );
		pgds_cms_editor_assert( null === get_comment( $comment_id ), 'deleted comment no longer exists' );
	}

	$groups          = pgds_meta_groups();
	$fields          = pgds_meta_fields();
	$expected_groups = array(
		'editorial' => array( '_pgds_sapo', '_pgds_primary_cat', '_pgds_source', '_pgds_display_author' ),
		'homepage'  => array( '_pgds_is_featured', '_pgds_feature_rank', '_pgds_photo_story', '_pgds_is_popular', '_pgds_popular_rank' ),
		'video'     => array( '_pgds_youtube_id', '_pgds_youtube_title', '_pgds_youtube_dur' ),
	);
	foreach ( $expected_groups as $group => $keys ) {
		pgds_cms_editor_assert( isset( $groups[ $group ]['label'], $groups[ $group ]['description'] ), sprintf( '%s field group has editor guidance', $group ) );
		foreach ( $keys as $key ) {
			pgds_cms_editor_assert( isset( $fields[ $key ] ) && $group === $fields[ $key ]['group'], sprintf( '%s belongs to the %s group', $key, $group ) );
		}
	}
	pgds_cms_editor_assert( empty( $groups['homepage']['collapsed'] ), 'Homepage curation is expanded by default so the Most read fields are visible immediately' );
	foreach ( array( '_pgds_youtube_title', '_pgds_youtube_dur' ) as $key ) {
		pgds_cms_editor_assert( isset( $fields[ $key ]['editable'] ) && ! $fields[ $key ]['editable'], sprintf( '%s is centrally marked read-only', $key ) );
		pgds_cms_editor_assert( in_array( $key, pgds_synchronized_meta_keys(), true ), sprintf( '%s remains synchronization-owned', $key ) );
	}
		pgds_cms_editor_assert( isset( $fields['_pgds_youtube_id'] ) && 'Video YouTube' === $fields['_pgds_youtube_id']['label'], 'YouTube editor field uses its final label' );
		pgds_cms_editor_assert( isset( $fields['_pgds_feature_rank'] ) && 'Vị trí Tin nổi bật' === $fields['_pgds_feature_rank']['label'], 'featured-rank field uses its final label' );
		pgds_cms_editor_assert(
			false !== strpos( $fields['_pgds_primary_cat']['help'], 'Chỉ hiện các chuyên mục đã được đánh dấu' ),
			'primary-category help explains that it only lists assigned categories'
		);

	$registered_meta = get_registered_meta_keys( 'post', 'post' );
	foreach ( array_keys( $fields ) as $key ) {
		pgds_cms_editor_assert( isset( $registered_meta[ $key ] ), sprintf( '%s remains registered for existing post metadata', $key ) );
	}

	update_post_meta( $post_id, '_pgds_youtube_dur', 367 );
	update_post_meta( $post_id, '_pgds_youtube_title', 'Legacy synchronized title' );
	update_post_meta( $post_id, '_pgds_video_unavailable', '1' );

	$valid_values = array(
		'_pgds_sapo'           => "A complete regression sapo.\nIt keeps its second line.",
		'_pgds_primary_cat'    => (string) $valid_category['term_id'],
		'_pgds_source'         => 'Regression source',
		'_pgds_display_author' => 'Regression author',
		'_pgds_is_featured'    => '1',
		'_pgds_feature_rank'   => '2',
		'_pgds_photo_story'    => '1',
		'_pgds_is_popular'     => '1',
		'_pgds_popular_rank'   => '3',
		'_pgds_youtube_id'     => 'https://www.youtube.com/watch?v=M7lc1UVf-VE',
	);
	$submitted_values                         = $valid_values;
	$submitted_values['_pgds_youtube_dur']    = '9999';
	$submitted_values['_pgds_youtube_title']  = 'Attempted editor overwrite';
	$submitted_values['_pgds_video_unavailable'] = '';
	pgds_cms_editor_submit( $post_id, $submitted_values );

	pgds_cms_editor_assert_meta( $post_id, '_pgds_sapo', $valid_values['_pgds_sapo'], 'valid sapo saves without migration' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_primary_cat', $valid_category['term_id'], 'assigned primary category saves' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_source', $valid_values['_pgds_source'], 'source saves' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_display_author', $valid_values['_pgds_display_author'], 'display author saves' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_is_featured', '1', 'featured flag saves' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_feature_rank', '2', 'featured rank saves within bounds' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_photo_story', '1', 'photo-story flag saves' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_is_popular', '1', 'popular flag saves' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_popular_rank', '3', 'popular rank saves within bounds' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_youtube_id', 'M7lc1UVf-VE', 'YouTube watch URL normalizes to its canonical ID' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_youtube_dur', '367', 'editor input cannot overwrite synchronized video duration' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_youtube_title', 'Legacy synchronized title', 'editor input cannot overwrite synchronized video title' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_video_unavailable', '1', 'editor input cannot overwrite synchronized video status' );

	update_post_meta( $post_id, '_pgds_source_id', 'regression-source-id' );
	update_post_meta( $post_id, '_pgds_youtube_poster_id', '901' );
	update_post_meta( $post_id, '_pgds_youtube_poster', 'https://example.test/poster.jpg' );
	$preserved_meta = array(
		'_pgds_source_id'          => 'regression-source-id',
		'_pgds_is_featured'        => '1',
		'_pgds_feature_rank'       => '2',
		'_pgds_photo_story'        => '1',
		'_pgds_youtube_id'         => 'M7lc1UVf-VE',
		'_pgds_youtube_dur'        => '367',
		'_pgds_youtube_title'      => 'Legacy synchronized title',
		'_pgds_youtube_poster_id'  => '901',
		'_pgds_youtube_poster'     => 'https://example.test/poster.jpg',
		'_pgds_video_unavailable'  => '1',
	);
	pgds_cms_editor_submit(
		$post_id,
		array( '_pgds_source' => 'Editorial-only source' ),
		'valid',
		null,
		array( 'editorial' )
	);
	foreach ( $preserved_meta as $key => $value ) {
		pgds_cms_editor_assert_meta( $post_id, $key, $value, sprintf( 'editorial-only save preserves %s', $key ) );
	}
	pgds_cms_editor_assert_meta( $post_id, '_pgds_source', 'Editorial-only source', 'editorial-only save updates its submitted field' );

	pgds_cms_editor_submit(
		$post_id,
		array( '_pgds_feature_rank' => '' ),
		'valid',
		null,
		array( 'homepage' )
	);
	pgds_cms_editor_assert_meta( $post_id, '_pgds_is_featured', '', 'explicit Homepage submission can clear an unchecked Featured flag' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_feature_rank', '0', 'explicit Homepage submission can clear Featured rank' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_photo_story', '', 'explicit Homepage submission can clear an unchecked photo-story flag' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_is_popular', '', 'explicit Homepage submission can clear an unchecked Most read flag' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_popular_rank', '0', 'explicit Homepage submission can clear Most read rank' );

	pgds_cms_editor_submit( $post_id, array( '_pgds_source' => 'No marker source' ), 'valid', null, null );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_source', 'Editorial-only source', 'a request without group markers preserves editor metadata' );

	foreach ( array( '1', '2', '3', '4' ) as $valid_rank ) {
		$rank_values                         = $valid_values;
		$rank_values['_pgds_feature_rank'] = $valid_rank;
		pgds_cms_editor_submit( $post_id, $rank_values );
		pgds_cms_editor_assert_meta( $post_id, '_pgds_feature_rank', $valid_rank, sprintf( 'exact featured rank %s saves', $valid_rank ) );
	}

	$rank_failures = array(
		''       => 'missing featured rank',
		'word'   => 'nonnumeric featured rank',
		'2.5'    => 'fractional featured rank',
		'-1'     => 'rank below one',
		'0'      => 'rank zero',
		'5'      => 'rank above four',
		'999999' => 'far out-of-bounds rank',
	);
	foreach ( $rank_failures as $rank_input => $rank_label ) {
		$rank_values                       = $valid_values;
		$rank_values['_pgds_feature_rank'] = $rank_input;
		pgds_cms_editor_submit( $post_id, $rank_values );
		pgds_cms_editor_assert_meta( $post_id, '_pgds_is_featured', '1', $rank_label . ' preserves the prior featured flag' );
		pgds_cms_editor_assert_meta( $post_id, '_pgds_feature_rank', '4', $rank_label . ' preserves the prior valid rank' );
	}

	$independent_values                         = $valid_values;
	$independent_values['_pgds_feature_rank'] = 'invalid';
	$independent_values['_pgds_source']       = 'Updated alongside rejected rank';
	pgds_cms_editor_submit( $post_id, $independent_values );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_feature_rank', '4', 'invalid rank still preserves the prior valid rank when another field changes' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_source', $independent_values['_pgds_source'], 'independent valid field saves when the Featured pair is rejected' );

	$not_featured_values = $valid_values;
	unset( $not_featured_values['_pgds_is_featured'] );
	$not_featured_values['_pgds_feature_rank'] = '';
	pgds_cms_editor_submit( $post_id, $not_featured_values );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_is_featured', '', 'disabling Featured accepts a blank rank' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_feature_rank', '0', 'disabling Featured stores the blank rank as zero' );

	$valid_values['_pgds_feature_rank'] = '2';
	pgds_cms_editor_submit( $post_id, $valid_values );

	foreach ( array( '1', '2', '3', '4' ) as $valid_rank ) {
		$popular_values                       = $valid_values;
		$popular_values['_pgds_popular_rank'] = $valid_rank;
		pgds_cms_editor_submit( $post_id, $popular_values );
		pgds_cms_editor_assert_meta( $post_id, '_pgds_popular_rank', $valid_rank, sprintf( 'exact Most read rank %s saves', $valid_rank ) );
	}

	$popular_rank_failures = array(
		'word'   => 'nonnumeric Most read rank',
		'2.5'    => 'fractional Most read rank',
		'-1'     => 'Most read rank below one',
		'0'      => 'Most read rank zero',
		'5'      => 'Most read rank above four',
	);
	foreach ( $popular_rank_failures as $rank_input => $rank_label ) {
		$popular_values                       = $valid_values;
		$popular_values['_pgds_popular_rank'] = $rank_input;
		pgds_cms_editor_submit( $post_id, $popular_values );
		pgds_cms_editor_assert_meta( $post_id, '_pgds_is_popular', '1', $rank_label . ' preserves the prior Most read flag' );
		pgds_cms_editor_assert_meta( $post_id, '_pgds_popular_rank', '4', $rank_label . ' preserves the prior valid Most read rank' );
	}

	$not_popular_values = $valid_values;
	unset( $not_popular_values['_pgds_is_popular'] );
	$not_popular_values['_pgds_popular_rank'] = '';
	pgds_cms_editor_submit( $post_id, $not_popular_values );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_is_popular', '', 'disabling Most read accepts a blank rank' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_popular_rank', '0', 'disabling Most read stores the blank rank as zero' );

	$invalid_primary_category = $valid_values;
	$invalid_primary_category['_pgds_primary_cat'] = (string) $invalid_category['term_id'];
	pgds_cms_editor_submit( $post_id, $invalid_primary_category );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_primary_cat', $valid_category['term_id'], 'unassigned primary category preserves the prior valid category' );

	$missing_primary_category = $valid_values;
	$missing_primary_category['_pgds_primary_cat'] = (string) ( PHP_INT_MAX - 10 );
	pgds_cms_editor_submit( $post_id, $missing_primary_category );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_primary_cat', $valid_category['term_id'], 'nonexistent primary category preserves the prior valid category' );

	$invalid_youtube_inputs = array(
		'https://example.test/watch?v=M7lc1UVf-VE'          => 'spoofed YouTube host',
		'https://youtube.com.example.test/watch?v=M7lc1UVf-VE' => 'YouTube lookalike host',
		'https://www.youtube.com/watch?v=short'              => 'short YouTube ID',
		'https://youtu.be/M7lc1UVf-VE/trailing'              => 'trailing short-link path',
		'https://www.youtube.com/embed/M7lc1UVf-VE/trailing' => 'trailing embed path',
		'ftp://www.youtube.com/watch?v=M7lc1UVf-VE'          => 'unsupported YouTube scheme',
		'https://user@www.youtube.com/watch?v=M7lc1UVf-VE'   => 'credential-bearing YouTube URL',
		'https://www.youtube.com:444/watch?v=M7lc1UVf-VE'    => 'unsupported YouTube port',
	);
	foreach ( $invalid_youtube_inputs as $input => $input_label ) {
		$invalid_youtube                      = $valid_values;
		$invalid_youtube['_pgds_youtube_id'] = $input;
		pgds_cms_editor_submit( $post_id, $invalid_youtube );
		pgds_cms_editor_assert_meta( $post_id, '_pgds_youtube_id', 'M7lc1UVf-VE', $input_label . ' preserves the prior canonical ID' );
	}

	$valid_youtube_inputs = array(
		'dQw4w9WgXcQ'                                      => 'bare ID',
		'https://youtu.be/dQw4w9WgXcQ'                     => 'short URL',
		'https://www.youtube.com/embed/dQw4w9WgXcQ'         => 'embed URL',
		'https://www.youtube.com/shorts/dQw4w9WgXcQ'        => 'Shorts URL',
		'https://www.youtube.com/live/dQw4w9WgXcQ'          => 'live URL',
		'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ' => 'privacy-enhanced embed URL',
	);
	foreach ( $valid_youtube_inputs as $input => $input_label ) {
		$valid_youtube                      = $valid_values;
		$valid_youtube['_pgds_youtube_id'] = $input;
		pgds_cms_editor_submit( $post_id, $valid_youtube );
		pgds_cms_editor_assert_meta( $post_id, '_pgds_youtube_id', 'dQw4w9WgXcQ', $input_label . ' normalizes to the canonical ID' );
	}

	$clear_youtube                      = $valid_values;
	$clear_youtube['_pgds_youtube_id'] = '';
	pgds_cms_editor_submit( $post_id, $clear_youtube );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_youtube_id', '', 'blank YouTube input intentionally clears the canonical ID' );
	pgds_cms_editor_assert( ! metadata_exists( 'post', $post_id, '_pgds_youtube_id' ), 'blank classic YouTube input removes the empty metadata row' );

	pgds_cms_editor_submit( $post_id, $valid_values );

	$guard_source = get_post_meta( $post_id, '_pgds_source', true );
	$guard_values = $valid_values;
	$guard_values['_pgds_source'] = 'Missing nonce overwrite';
	pgds_cms_editor_submit( $post_id, $guard_values, null );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_source', $guard_source, 'missing nonce prevents a classic metadata write' );

	$guard_values['_pgds_source'] = 'Invalid nonce overwrite';
	pgds_cms_editor_submit( $post_id, $guard_values, 'invalid-nonce' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_source', $guard_source, 'invalid nonce prevents a classic metadata write' );

	$revision_id = wp_save_post_revision( $post_id );
	if ( is_wp_error( $revision_id ) || ! $revision_id ) {
		throw new RuntimeException( 'The regression suite could not create its revision fixture.' );
	}
	$pgds_cms_editor_posts[] = (int) $revision_id;
	$guard_values['_pgds_source'] = 'Revision overwrite';
	pgds_cms_editor_submit( $revision_id, $guard_values );
	pgds_cms_editor_assert_meta( $revision_id, '_pgds_source', '', 'revision saves do not receive editor metadata' );

	$autosave_data              = (array) get_post( $post_id );
	$autosave_data['post_name'] = $post_id . '-autosave-v1';
	$autosave_id                = _wp_put_post_revision( $autosave_data, true );
	if ( is_wp_error( $autosave_id ) || ! $autosave_id ) {
		throw new RuntimeException( 'The regression suite could not create its autosave fixture.' );
	}
	$pgds_cms_editor_posts[] = (int) $autosave_id;
	$guard_values['_pgds_source'] = 'Autosave overwrite';
	pgds_cms_editor_submit( $autosave_id, $guard_values );
	pgds_cms_editor_assert_meta( $autosave_id, '_pgds_source', '', 'autosaves do not receive editor metadata' );

	$wrong_type_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'draft',
			'post_title'  => 'PGDS CMS editor wrong type ' . $token,
		),
		true
	);
	if ( is_wp_error( $wrong_type_id ) ) {
		throw new RuntimeException( 'The regression suite could not create its wrong-type fixture.' );
	}
	$pgds_cms_editor_posts[] = (int) $wrong_type_id;
	$guard_values['_pgds_source'] = 'Wrong type overwrite';
	pgds_cms_editor_submit( $wrong_type_id, $guard_values );
	pgds_cms_editor_assert_meta( $wrong_type_id, '_pgds_source', '', 'non-post saves do not receive article metadata' );

	$subscriber_id = wp_insert_user(
		array(
			'user_login' => 'pgds-cms-editor-' . $token,
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'pgds-cms-editor-' . $token . '@example.test',
			'role'       => 'subscriber',
		)
	);
	if ( is_wp_error( $subscriber_id ) ) {
		throw new RuntimeException( 'The regression suite could not create its unauthorized user.' );
	}
	$pgds_cms_editor_users[] = (int) $subscriber_id;
	wp_set_current_user( (int) $subscriber_id );
	$unauthorized_values = $valid_values;
	$unauthorized_values['_pgds_source'] = 'Unauthorized overwrite';
	pgds_cms_editor_submit( $post_id, $unauthorized_values );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_source', $valid_values['_pgds_source'], 'a user without edit_post cannot change editor metadata' );
	wp_set_current_user( (int) $administrators[0] );

	$feedback_token = pgds_store_meta_feedback(
		$post_id,
		array( 'pgds_invalid_youtube', 'not-an-allowed-code' )
	);
	pgds_cms_editor_assert( 1 === preg_match( '/^[A-Za-z0-9]{20}$/', $feedback_token ), 'validation feedback uses an opaque token' );
	pgds_cms_editor_assert(
		array( 'pgds_invalid_youtube' ) === pgds_consume_meta_feedback( $post_id, $feedback_token ),
		'validation feedback returns only allowlisted codes to the matching user and post'
	);
	pgds_cms_editor_assert( array() === pgds_consume_meta_feedback( $post_id, $feedback_token ), 'validation feedback is single-use' );

	$scoped_feedback_token = pgds_store_meta_feedback( $post_id, array( 'pgds_invalid_primary_category' ) );
	wp_set_current_user( (int) $subscriber_id );
	pgds_cms_editor_assert( array() === pgds_consume_meta_feedback( $post_id, $scoped_feedback_token ), 'another user cannot consume scoped validation feedback' );
	wp_set_current_user( (int) $administrators[0] );
	pgds_cms_editor_assert(
		array( 'pgds_invalid_primary_category' ) === pgds_consume_meta_feedback( $post_id, $scoped_feedback_token ),
		'a mismatched-user lookup leaves scoped validation feedback available to its owner'
	);

	$GLOBALS['pgds_meta_feedback'] = array(
		'post_id' => (int) $post_id,
		'codes'   => array( 'pgds_invalid_featured_rank' ),
	);
	$other_redirect = pgds_redirect_post_location( 'post.php?post=999&action=edit', 999 );
	pgds_cms_editor_assert( false === strpos( $other_redirect, 'pgds_meta_feedback=' ), 'validation feedback is not attached to another post redirect' );
	$matching_redirect = pgds_redirect_post_location( 'post.php?post=' . $post_id . '&action=edit', $post_id );
	parse_str( (string) wp_parse_url( $matching_redirect, PHP_URL_QUERY ), $redirect_query );
	pgds_cms_editor_assert( isset( $redirect_query['pgds_meta_feedback'] ), 'matching post redirect receives a validation-feedback token' );
	$redirect_feedback_token = $redirect_query['pgds_meta_feedback'] ?? '';
	$previous_get            = $_GET;
	$_GET                    = array(
		'post'               => (string) $post_id,
		'pgds_meta_feedback' => $redirect_feedback_token,
	);
	pgds_prepare_meta_feedback();
	$_GET = $previous_get;
	pgds_cms_editor_assert(
		array( 'pgds_invalid_featured_rank' ) === ( $GLOBALS['pgds_meta_feedback']['codes'] ?? array() ),
		'redirect feedback is prepared for the matching editor screen'
	);
	pgds_cms_editor_assert(
		array() === pgds_consume_meta_feedback( $post_id, $redirect_feedback_token ),
		'prepared redirect feedback remains single-use'
	);
	ob_start();
	pgds_render_meta_box( get_post( $post_id ) );
	$redirect_metabox_markup = ob_get_clean();
	pgds_cms_editor_assert(
		false !== strpos( $redirect_metabox_markup, 'Thiết lập Tin nổi bật chưa được cập nhật.' ),
		'prepared redirect feedback renders inside the compatibility meta box'
	);
	pgds_cms_editor_assert(
			! isset( $GLOBALS['pgds_meta_feedback'] ),
			'compatibility meta-box feedback is request-local and rendered once'
		);
		pgds_cms_editor_assert(
			false !== strpos( $redirect_metabox_markup, 'aria-describedby="pgds_article_primary_slug-help"' ),
			'primary-category control is associated with its guidance'
		);
		pgds_cms_editor_assert(
			false !== strpos( $redirect_metabox_markup, 'aria-live="polite"' ),
			'featured-rank state is announced when the Featured choice changes'
		);

	$rest_post_id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'draft',
			'post_title'    => 'PGDS REST original ' . $token,
			'post_content'  => 'Original REST content.',
			'post_category' => array( (int) $valid_category['term_id'] ),
		),
		true
	);
	if ( is_wp_error( $rest_post_id ) ) {
		throw new RuntimeException( 'The regression suite could not create its REST fixture.' );
	}
	$pgds_cms_editor_posts[] = (int) $rest_post_id;
	update_post_meta( $rest_post_id, '_pgds_sapo', 'Original REST sapo' );
	update_post_meta( $rest_post_id, '_pgds_primary_cat', (int) $valid_category['term_id'] );
	update_post_meta( $rest_post_id, '_pgds_is_featured', '1' );
	update_post_meta( $rest_post_id, '_pgds_feature_rank', '2' );
	update_post_meta( $rest_post_id, '_pgds_youtube_id', 'M7lc1UVf-VE' );
	update_post_meta( $rest_post_id, '_pgds_youtube_dur', 367 );
	update_post_meta( $rest_post_id, '_pgds_youtube_title', 'Synchronized REST title' );
	update_post_meta( $rest_post_id, '_pgds_youtube_poster_id', 71 );

	$registered_sapo = $registered_meta['_pgds_sapo'];
	pgds_cms_editor_assert(
		is_callable( $registered_sapo['auth_callback'] ?? null ) &&
		! call_user_func( $registered_sapo['auth_callback'], false, '_pgds_sapo', $rest_post_id, $subscriber_id, 'post' ) &&
		call_user_func( $registered_sapo['auth_callback'], false, '_pgds_sapo', $rest_post_id, (int) $administrators[0], 'post' ),
		'registered editor metadata authorization checks the specific post object'
	);

	$rest_success = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts/' . $rest_post_id,
		array(
			'title'      => 'PGDS REST valid ' . $token,
			'categories' => array( (int) $invalid_category['term_id'] ),
			'meta'       => array(
				'_pgds_sapo'           => 'REST valid sapo',
				'_pgds_primary_cat'    => (int) $invalid_category['term_id'],
				'_pgds_is_featured'    => true,
				'_pgds_feature_rank'   => 3,
				'_pgds_youtube_id'     => 'https://youtu.be/dQw4w9WgXcQ',
			),
		)
	);
	pgds_cms_editor_assert( 200 === $rest_success->get_status(), 'REST accepts valid editor metadata' );
	pgds_cms_editor_assert( 'PGDS REST valid ' . $token === get_post( $rest_post_id )->post_title, 'valid REST update writes the title' );
	pgds_cms_editor_assert(
		array( (int) $invalid_category['term_id'] ) === array_map( 'intval', wp_get_post_categories( $rest_post_id ) ),
		'REST validates and writes a category newly assigned in the same request'
	);
	pgds_cms_editor_assert_meta( $rest_post_id, '_pgds_primary_cat', $invalid_category['term_id'], 'REST stores the newly assigned primary category' );
	pgds_cms_editor_assert_meta( $rest_post_id, '_pgds_feature_rank', '3', 'REST stores a valid Featured rank' );
	pgds_cms_editor_assert_meta( $rest_post_id, '_pgds_youtube_id', 'dQw4w9WgXcQ', 'REST canonicalizes a valid YouTube URL before storing it' );

	$rest_previous_title      = get_post( $rest_post_id )->post_title;
	$rest_previous_categories = array_map( 'intval', wp_get_post_categories( $rest_post_id ) );
	$rest_previous_sapo       = get_post_meta( $rest_post_id, '_pgds_sapo', true );
	$rest_previous_rank       = get_post_meta( $rest_post_id, '_pgds_feature_rank', true );
	$rest_invalid_rank = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts/' . $rest_post_id,
		array(
			'title'      => 'PGDS REST must not partially write ' . $token,
			'categories' => array( (int) $valid_category['term_id'] ),
			'meta'       => array(
				'_pgds_sapo'         => 'REST must not partially write',
				'_pgds_is_featured'  => true,
				'_pgds_feature_rank' => 5,
			),
		)
	);
	pgds_cms_editor_assert_rest_error( $rest_invalid_rank, 'pgds_invalid_featured_rank', 400, 'REST rejects an invalid Featured rank before writing' );
	pgds_cms_editor_assert( $rest_previous_title === get_post( $rest_post_id )->post_title, 'invalid REST request preserves the title atomically' );
	pgds_cms_editor_assert( $rest_previous_categories === array_map( 'intval', wp_get_post_categories( $rest_post_id ) ), 'invalid REST request preserves categories atomically' );
	pgds_cms_editor_assert_meta( $rest_post_id, '_pgds_sapo', $rest_previous_sapo, 'invalid REST request preserves unrelated metadata atomically' );
	pgds_cms_editor_assert_meta( $rest_post_id, '_pgds_feature_rank', $rest_previous_rank, 'invalid REST request preserves the prior Featured rank' );

	$rest_invalid_category = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts/' . $rest_post_id,
		array(
			'categories' => array( (int) $invalid_category['term_id'] ),
			'meta'       => array( '_pgds_primary_cat' => (int) $valid_category['term_id'] ),
		)
	);
	pgds_cms_editor_assert_rest_error( $rest_invalid_category, 'pgds_invalid_primary_category', 400, 'REST rejects a primary category outside final request categories' );
	pgds_cms_editor_assert_meta( $rest_post_id, '_pgds_primary_cat', $invalid_category['term_id'], 'invalid REST category preserves the prior primary category' );

	$rest_nonexistent_category = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts/' . $rest_post_id,
		array( 'meta' => array( '_pgds_primary_cat' => PHP_INT_MAX - 10 ) )
	);
	pgds_cms_editor_assert_rest_error( $rest_nonexistent_category, 'pgds_invalid_primary_category', 400, 'REST rejects a nonexistent primary category' );

	$rest_categories_only = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts/' . $rest_post_id,
		array( 'categories' => array( (int) $valid_category['term_id'] ) )
	);
	pgds_cms_editor_assert_rest_error( $rest_categories_only, 'pgds_invalid_primary_category', 400, 'REST validates the stored primary category against final categories even when meta is omitted' );
	pgds_cms_editor_assert(
		array( (int) $invalid_category['term_id'] ) === array_map( 'intval', wp_get_post_categories( $rest_post_id ) ),
		'invalid REST categories-only request preserves final categories'
	);

	$rest_invalid_youtube = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts/' . $rest_post_id,
		array( 'meta' => array( '_pgds_youtube_id' => 'https://youtube.com.example.test/watch?v=M7lc1UVf-VE' ) )
	);
	pgds_cms_editor_assert_rest_error( $rest_invalid_youtube, 'pgds_invalid_youtube', 400, 'REST rejects a spoofed YouTube URL' );
	pgds_cms_editor_assert_meta( $rest_post_id, '_pgds_youtube_id', 'dQw4w9WgXcQ', 'invalid REST YouTube input preserves the prior canonical ID' );

	$rest_clear_youtube = pgds_cms_editor_rest_request(
			'POST',
			'/wp/v2/posts/' . $rest_post_id,
			array( 'meta' => array( '_pgds_youtube_id' => '' ) )
		);
		pgds_cms_editor_assert( 200 === $rest_clear_youtube->get_status(), 'REST accepts an intentional blank YouTube value' );
		pgds_cms_editor_assert_meta( $rest_post_id, '_pgds_youtube_id', '', 'REST blank YouTube input clears the canonical ID' );
		pgds_cms_editor_assert(
			! metadata_exists( 'post', $rest_post_id, '_pgds_youtube_id' ),
			'REST blank YouTube input removes the empty metadata row'
		);

	$readonly_keys = array( '_pgds_youtube_dur', '_pgds_youtube_title', '_pgds_youtube_poster_id' );
	$readonly_echo = array();
	foreach ( $readonly_keys as $readonly_key ) {
		$readonly_echo[ $readonly_key ] = get_post_meta( $rest_post_id, $readonly_key, true );
	}
	$rest_readonly_echo = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts/' . $rest_post_id,
		array(
			'title' => 'PGDS REST readonly echo ' . $token,
			'meta'  => $readonly_echo,
		)
	);
	pgds_cms_editor_assert( 200 === $rest_readonly_echo->get_status(), 'REST allows Gutenberg to echo unchanged synchronization-owned metadata' );
	pgds_cms_editor_assert( 'PGDS REST readonly echo ' . $token === get_post( $rest_post_id )->post_title, 'unchanged read-only metadata does not block an ordinary post update' );
	foreach ( $readonly_echo as $readonly_key => $readonly_value ) {
		pgds_cms_editor_assert_meta( $rest_post_id, $readonly_key, $readonly_value, sprintf( 'REST no-op echo preserves synchronization-owned %s', $readonly_key ) );
	}

	foreach ( $readonly_keys as $readonly_key ) {
		$readonly_before = get_post_meta( $rest_post_id, $readonly_key, true );
		$rest_readonly = pgds_cms_editor_rest_request(
			'POST',
			'/wp/v2/posts/' . $rest_post_id,
			array(
				'title' => 'PGDS REST readonly must not write ' . $token,
				'meta'  => array( $readonly_key => '99' ),
			)
		);
		pgds_cms_editor_assert_rest_error( $rest_readonly, 'pgds_readonly_video_meta', 403, sprintf( 'REST rejects writes to synchronization-owned %s', $readonly_key ) );
		pgds_cms_editor_assert_meta( $rest_post_id, $readonly_key, $readonly_before, sprintf( 'REST preserves synchronization-owned %s', $readonly_key ) );
		pgds_cms_editor_assert( 'PGDS REST readonly echo ' . $token === get_post( $rest_post_id )->post_title, sprintf( 'REST %s rejection preserves the title', $readonly_key ) );
	}

	wp_set_current_user( (int) $subscriber_id );
	$rest_unauthorized = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts/' . $rest_post_id,
		array( 'meta' => array( '_pgds_sapo' => 'Unauthorized REST overwrite' ) )
	);
	pgds_cms_editor_assert_rest_error( $rest_unauthorized, 'rest_cannot_edit', 403, 'REST enforces object-level authorization before metadata writes' );
	pgds_cms_editor_assert_meta( $rest_post_id, '_pgds_sapo', $rest_previous_sapo, 'unauthorized REST request preserves editor metadata' );
	wp_set_current_user( (int) $administrators[0] );

	$rest_create = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts',
		array(
			'status'     => 'draft',
			'title'      => 'PGDS REST create ' . $token,
			'categories' => array( (int) $valid_category['term_id'] ),
			'meta'       => array(
				'_pgds_primary_cat'  => (int) $valid_category['term_id'],
				'_pgds_is_featured'  => true,
				'_pgds_feature_rank' => 1,
			),
		)
	);
	$rest_create_data = $rest_create->get_data();
	pgds_cms_editor_assert( 201 === $rest_create->get_status(), 'REST creates a post with valid same-request categories and metadata' );
	if ( isset( $rest_create_data['id'] ) ) {
		$rest_create_id             = (int) $rest_create_data['id'];
		$pgds_cms_editor_posts[] = $rest_create_id;
		pgds_cms_editor_assert_meta( $rest_create_id, '_pgds_primary_cat', $valid_category['term_id'], 'REST create stores the same-request primary category' );
		pgds_cms_editor_assert_meta( $rest_create_id, '_pgds_feature_rank', '1', 'REST create stores the exact Featured rank' );
	}

	$rest_invalid_create = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts',
		array(
			'status' => 'draft',
			'title'  => 'PGDS REST rejected create ' . $token,
			'meta'   => array(
				'_pgds_is_featured'  => true,
				'_pgds_feature_rank' => 9,
			),
		)
	);
	pgds_cms_editor_assert_rest_error( $rest_invalid_create, 'pgds_invalid_featured_rank', 400, 'REST rejects an invalid create before inserting a post' );
	$rejected_create = get_page_by_title( 'PGDS REST rejected create ' . $token, OBJECT, 'post' );
	pgds_cms_editor_assert( ! $rejected_create, 'invalid REST create does not partially insert a post' );

	$conflict_post_id = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'PGDS CMS editor conflict ' . $token,
		),
		true
	);
	$warning_post_id = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => 'PGDS CMS editor warnings ' . $token,
		),
		true
	);
	if ( is_wp_error( $conflict_post_id ) || is_wp_error( $warning_post_id ) ) {
		throw new RuntimeException( 'The regression suite could not create its warning fixtures.' );
	}
	$pgds_cms_editor_posts[] = (int) $conflict_post_id;
	$pgds_cms_editor_posts[] = (int) $warning_post_id;
	update_post_meta( $conflict_post_id, '_pgds_is_featured', '1' );
	update_post_meta( $conflict_post_id, '_pgds_feature_rank', '1' );
	update_post_meta( $warning_post_id, '_pgds_is_featured', '1' );
	update_post_meta( $warning_post_id, '_pgds_feature_rank', '1' );
	update_post_meta( $warning_post_id, '_pgds_photo_story', '1' );
	delete_post_meta( $warning_post_id, '_pgds_sapo' );

	$warnings      = pgds_get_article_warnings( $warning_post_id );
	$warning_codes = wp_list_pluck( $warnings, 'code' );
	pgds_cms_editor_assert( in_array( 'pgds_missing_featured_image', $warning_codes, true ), 'featured or photo-story article without an image has a nonblocking warning' );
	pgds_cms_editor_assert( in_array( 'pgds_missing_sapo', $warning_codes, true ), 'featured or photo-story article without a sapo has a nonblocking warning' );
	pgds_cms_editor_assert( in_array( 'pgds_duplicate_featured_rank', $warning_codes, true ), 'duplicate published featured rank has a nonblocking warning' );
	$duplicate_warning = array();
	foreach ( $warnings as $warning ) {
		if ( 'pgds_duplicate_featured_rank' === $warning['code'] ) {
			$duplicate_warning = $warning;
			break;
		}
	}
	pgds_cms_editor_assert( ! empty( $duplicate_warning['edit_url'] ), 'duplicate featured-rank warning includes an editor link for an authorized user' );
	pgds_cms_editor_assert_meta( $conflict_post_id, '_pgds_is_featured', '1', 'warning lookup does not alter the conflicting post Featured flag' );
	pgds_cms_editor_assert_meta( $conflict_post_id, '_pgds_feature_rank', '1', 'warning lookup does not reassign the conflicting post rank' );

	wp_set_current_user( (int) $subscriber_id );
	$restricted_warnings = pgds_get_article_warnings( $warning_post_id );
	$restricted_duplicate = array();
	foreach ( $restricted_warnings as $warning ) {
		if ( 'pgds_duplicate_featured_rank' === $warning['code'] ) {
			$restricted_duplicate = $warning;
			break;
		}
	}
	pgds_cms_editor_assert(
		isset( $restricted_duplicate['conflict_id'] ) && empty( $restricted_duplicate['edit_url'] ),
		'duplicate-rank warning omits the edit link for a user who cannot edit the conflict'
	);
	wp_set_current_user( (int) $administrators[0] );

	$columns = pgds_admin_columns( array( 'cb' => '<input>', 'title' => 'Title', 'date' => 'Date' ) );
	pgds_cms_editor_assert( isset( $columns['pgds_flags'] ) && 'PGDS' === $columns['pgds_flags'], 'Posts list retains the PGDS metadata column' );
	$sortable_columns = apply_filters( 'manage_edit-post_sortable_columns', array( 'title' => 'title' ) );
	pgds_cms_editor_assert( ! isset( $sortable_columns['pgds_flags'] ), 'Posts-list PGDS column does not advertise unsupported sorting' );
	pgds_cms_editor_assert(
		array_keys( $columns ) === array( 'cb', 'title', 'pgds_flags', 'date' ),
		'Posts-list PGDS column remains beside the title'
	);
	ob_start();
	pgds_admin_column_content( 'pgds_flags', $post_id );
	$column_markup = html_entity_decode( ob_get_clean(), ENT_QUOTES, 'UTF-8' );
	pgds_cms_editor_assert(
		false !== strpos( $column_markup, 'Nổi bật (#2)' ) &&
		false !== strpos( $column_markup, 'Tin ảnh' ) &&
		false !== strpos( $column_markup, 'Video' ),
		'Posts-list PGDS column displays Featured, Tin ảnh, and Video metadata'
	);

	foreach (
		array(
			'featured' => $post_id,
			'photo'    => $post_id,
			'video'    => $post_id,
		) as $filter => $expected_post_id
	) {
		pgds_cms_editor_assert(
			in_array( (int) $expected_post_id, pgds_cms_editor_filter_post_ids( $filter ), true ),
			sprintf( 'Posts-list %s filter still finds matching metadata', $filter )
		);
	}

	$cleared_video_values = $valid_values;
	$cleared_video_values['_pgds_youtube_id'] = '';
	pgds_cms_editor_submit( $post_id, $cleared_video_values );
	pgds_cms_editor_assert(
		! in_array( (int) $post_id, pgds_cms_editor_filter_post_ids( 'video' ), true ),
		'Posts-list video filter excludes an intentionally cleared video'
	);

	ob_start();
	pgds_render_meta_box( get_post( $post_id ) );
	$metabox_markup = ob_get_clean();
	foreach ( $groups as $group ) {
		pgds_cms_editor_assert( false !== strpos( $metabox_markup, $group['label'] ), sprintf( 'editor meta box renders the %s group', $group['label'] ) );
	}
	pgds_cms_editor_assert( false !== strpos( $metabox_markup, 'min="1"' ) && false !== strpos( $metabox_markup, 'max="4"' ) && false !== strpos( $metabox_markup, 'step="1"' ), 'featured-rank input provides 1–4 progressive guidance' );
	pgds_cms_editor_assert( false !== strpos( $metabox_markup, 'class="pgds-metabox__group pgds-metabox__group--homepage" data-pgds-group="homepage"' ), 'Homepage curation renders expanded without a collapse toggle' );
	foreach ( array( 'editorial', 'homepage' ) as $group_key ) {
		pgds_cms_editor_assert( false !== strpos( $metabox_markup, 'name="pgds_meta_groups[]" value="' . $group_key . '"' ), sprintf( '%s group emits an explicit save marker', $group_key ) );
	}
	pgds_cms_editor_assert( false === strpos( $metabox_markup, 'name="pgds_meta_groups[]" value="video"' ), 'hidden Video group does not emit a save marker in Article' );
	pgds_cms_editor_assert( 3 === substr_count( $metabox_markup, '<output' ), 'video title, duration, and synchronization status render as read-only outputs' );
	pgds_cms_editor_assert( 0 === preg_match( '/<output[^>]+name=/', $metabox_markup ), 'synchronization-owned outputs have no writable form name' );
	pgds_cms_editor_assert( false === strpos( $metabox_markup, 'name="_pgds_youtube_title"' ) && false === strpos( $metabox_markup, 'name="_pgds_youtube_dur"' ), 'displayed synchronization-owned fields do not render writable controls' );

	$surface_definitions = pgds_editorial_surfaces();
	pgds_cms_editor_assert(
		array( 'article', 'emagazine', 'video', 'vietnam-buddhism' ) === array_keys( $surface_definitions ),
		'editorial registry exposes exactly four workflows'
	);
	pgds_cms_editor_assert(
		array( 'tin-phat-su', 'song-an-lanh', 'am-thuc-chay', 'loi-song-xanh', 'phat-tich', 'tot-doi-dep-dao' ) === $surface_definitions['article']['primary_slugs'],
		'Article registry contains only the approved Vietnamese branches'
	);
	pgds_cms_editor_assert( '' === pgds_editorial_surface_from_slug( 'media' ), 'Media parent category never defines an editorial surface' );

	$surface_terms = array();
	foreach ( array( 'tin-phat-su', 'phat-tich', 'emagazine', 'video', 'vietnam-buddhism', 'media' ) as $surface_slug ) {
		$surface_term = pgds_category_term( $surface_slug );
		if ( ! $surface_term instanceof WP_Term ) {
			throw new RuntimeException( 'The regression suite requires every editorial surface category.' );
		}
		$surface_terms[ $surface_slug ] = (int) $surface_term->term_id;
	}

	$surface_fixture_ids = array();
	foreach (
		array(
			'article'            => 'tin-phat-su',
			'emagazine'          => 'emagazine',
			'video'              => 'video',
			'vietnam-buddhism'   => 'vietnam-buddhism',
		) as $fixture_surface => $fixture_slug
	) {
		$fixture_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'draft',
				'post_title'  => sprintf( 'PGDS %s surface %s', $fixture_surface, $token ),
			),
			true
		);
		if ( is_wp_error( $fixture_id ) ) {
			throw new RuntimeException( 'The regression suite could not create a surface fixture.' );
		}
		$fixture_id                 = (int) $fixture_id;
		$pgds_cms_editor_posts[]    = $fixture_id;
		$surface_fixture_ids[ $fixture_surface ] = $fixture_id;
		wp_set_post_categories( $fixture_id, array( $surface_terms[ $fixture_slug ], $surface_terms['phat-tich'] ) );
		update_post_meta( $fixture_id, '_pgds_primary_cat', $surface_terms[ $fixture_slug ] );
	}
	update_post_meta( $surface_fixture_ids['video'], '_pgds_video_unavailable', '1' );
	pgds_cms_editor_assert( 'Just now' === pgds_reader_time_ago( $surface_fixture_ids['vietnam-buddhism'] ), 'Vietnam Buddhism card time remains English outside its dedicated route' );
	pgds_cms_editor_assert( 'Vừa xong' === pgds_reader_time_ago( $surface_fixture_ids['article'] ), 'Vietnamese Article card time remains Vietnamese' );

	$missing_primary_id = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => 'PGDS missing primary ' . $token,
		),
		true
	);
	$media_primary_id = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => 'PGDS media primary ' . $token,
		),
		true
	);
	$stale_video_id = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => 'PGDS stale video primary ' . $token,
		),
		true
	);
	if ( is_wp_error( $missing_primary_id ) || is_wp_error( $media_primary_id ) || is_wp_error( $stale_video_id ) ) {
		throw new RuntimeException( 'The regression suite could not create legacy classification fixtures.' );
	}
	foreach ( array( $missing_primary_id, $media_primary_id, $stale_video_id ) as $legacy_id ) {
		$pgds_cms_editor_posts[] = (int) $legacy_id;
	}
	wp_set_post_categories( $missing_primary_id, array( $surface_terms['tin-phat-su'] ) );
	wp_set_post_categories( $media_primary_id, array( $surface_terms['media'] ) );
	update_post_meta( $media_primary_id, '_pgds_primary_cat', $surface_terms['media'] );
	wp_set_post_categories( $stale_video_id, array( $surface_terms['tin-phat-su'] ) );
	pgds_category_migration_context( true );
	update_post_meta( $stale_video_id, '_pgds_primary_cat', $surface_terms['video'] );
	pgds_category_migration_context( false );

	foreach ( $surface_fixture_ids as $fixture_surface => $fixture_id ) {
		$classification = pgds_get_editorial_classification( $fixture_id );
		pgds_cms_editor_assert( $classification['valid'] && $fixture_surface === $classification['surface'], sprintf( '%s fixture has one valid editorial surface', $fixture_surface ) );
		pgds_cms_editor_assert( in_array( $fixture_id, pgds_cms_editor_surface_post_ids( $fixture_surface ), true ), sprintf( '%s list includes its valid fixture', $fixture_surface ) );
		foreach ( array_diff( array_keys( $surface_definitions ), array( $fixture_surface ) ) as $other_surface ) {
			pgds_cms_editor_assert( ! in_array( $fixture_id, pgds_cms_editor_surface_post_ids( $other_surface ), true ), sprintf( '%s fixture stays out of %s list', $fixture_surface, $other_surface ) );
		}
	}
	pgds_cms_editor_assert( in_array( $surface_fixture_ids['video'], pgds_cms_editor_surface_post_ids( 'video' ), true ), 'unavailable Video remains in the Video workflow' );

	$minimal_emagazine_id = $surface_fixture_ids['emagazine'];
	wp_update_post( array( 'ID' => $minimal_emagazine_id, 'comment_status' => 'closed' ) );
	delete_post_thumbnail( $minimal_emagazine_id );
	delete_post_meta( $minimal_emagazine_id, '_pgds_sapo' );
	delete_post_meta( $minimal_emagazine_id, '_pgds_display_author' );
	delete_post_meta( $minimal_emagazine_id, '_pgds_source' );
	$empty_emagazine_queries = static function ( $query ) {
		if ( 'post' === $query->get( 'post_type' ) && 4 === (int) $query->get( 'posts_per_page' ) ) {
			$query->set( 'post__in', array( 0 ) );
		}
	};
	add_action( 'pre_get_posts', $empty_emagazine_queries, PHP_INT_MAX );
	$minimal_emagazine_markup = pgds_cms_editor_render_emagazine( $minimal_emagazine_id );
	remove_action( 'pre_get_posts', $empty_emagazine_queries, PHP_INT_MAX );
	pgds_cms_editor_assert( false !== strpos( $minimal_emagazine_markup, 'class="pgds-emagazine"' ), 'minimal E-magazine renders its dedicated layout' );
	pgds_cms_editor_assert( false === strpos( $minimal_emagazine_markup, 'pgds-emagazine__cover' ), 'E-magazine without a cover omits the cover container' );
	pgds_cms_editor_assert( false === strpos( $minimal_emagazine_markup, 'id="comments"' ), 'E-magazine without comments omits the comments container' );
	pgds_cms_editor_assert( false === strpos( $minimal_emagazine_markup, 'pgds-emagazine__more' ), 'E-magazine without recommendations omits the related container' );

	$vietnam_buddhism_id = $surface_fixture_ids['vietnam-buddhism'];
	update_post_meta( $vietnam_buddhism_id, '_pgds_is_featured', '1' );
	$vietnam_buddhism_warnings = pgds_get_article_warnings( $vietnam_buddhism_id );
	pgds_cms_editor_assert(
		! empty( $vietnam_buddhism_warnings ) && false !== strpos( $vietnam_buddhism_warnings[0]['message'], 'featured image' ),
		'Vietnam Buddhism validation guidance is rendered in English'
	);
	ob_start();
	pgds_render_meta_box( get_post( $vietnam_buddhism_id ) );
	$vietnam_buddhism_markup = ob_get_clean();
	foreach ( array( 'Content type', '>Article</option>', 'Editorial details', 'Home-page curation', 'Back to Vietnam Buddhism' ) as $english_copy ) {
		pgds_cms_editor_assert( false !== strpos( $vietnam_buddhism_markup, $english_copy ), sprintf( 'Vietnam Buddhism form includes English copy: %s', wp_strip_all_tags( $english_copy ) ) );
	}
	foreach ( array( 'Loại nội dung', 'Biên tập', 'Tin nổi bật', 'Quay lại danh sách', 'Trạng thái', 'Chưa có dữ liệu' ) as $vietnamese_copy ) {
		pgds_cms_editor_assert( false === strpos( $vietnam_buddhism_markup, $vietnamese_copy ), sprintf( 'Vietnam Buddhism form omits theme-added Vietnamese copy: %s', $vietnamese_copy ) );
	}
	pgds_cms_editor_assert(
		'Featured settings were not updated. Choose a position from 1 to 4 when Featured is enabled.' === pgds_meta_feedback_messages( 'vietnam-buddhism' )['pgds_invalid_featured_rank'],
		'Vietnam Buddhism save feedback is rendered in English'
	);
	update_post_meta( $vietnam_buddhism_id, '_pgds_is_featured', '' );

	foreach ( array( $missing_primary_id, $media_primary_id, $stale_video_id ) as $legacy_id ) {
		$legacy_classification = pgds_get_editorial_classification( $legacy_id );
		pgds_cms_editor_assert( ! $legacy_classification['valid'] && 'article' === $legacy_classification['surface'], 'legacy classification falls back to Article' );
		pgds_cms_editor_assert( in_array( (int) $legacy_id, pgds_cms_editor_surface_post_ids( 'article', 'needs-review' ), true ), 'Article review filter finds a legacy classification' );
	}

	$surface_url_previous_get     = $_GET;
	$surface_url_previous_pagenow = $GLOBALS['pagenow'] ?? null;
	$GLOBALS['pagenow']           = 'edit.php';
	$_GET                         = array( 'pgds_surface' => 'video' );
	$video_list_url               = pgds_editorial_admin_url( admin_url( 'edit.php' ), 'edit.php', null );
	$page_list_url                = pgds_editorial_admin_url( admin_url( 'edit.php?post_type=page' ), 'edit.php?post_type=page', null );
	$surface_views                = pgds_editorial_surface_views(
		array(
			'all' => '<a href="edit.php?post_type=post">All <span class="count">(999)</span></a>',
		)
	);
	$surface_row_actions          = pgds_editorial_post_row_actions(
		array(
			'trash' => '<a href="post.php?post=' . $surface_fixture_ids['video'] . '&action=trash">Trash</a>',
		),
		get_post( $surface_fixture_ids['video'] )
	);
	pgds_cms_editor_assert( false !== strpos( $video_list_url, 'post_type=post' ) && false !== strpos( $video_list_url, 'pgds_surface=video' ), 'native Posts-list links preserve the current surface' );
	pgds_cms_editor_assert( false === strpos( $page_list_url, 'pgds_surface=' ), 'surface URL preservation does not affect Pages links' );
	pgds_cms_editor_assert( false !== strpos( $surface_views['all'], 'pgds_surface=video' ) && false === strpos( $surface_views['all'], '(999)' ), 'native status views preserve their surface and display its count' );
	pgds_cms_editor_assert( false !== strpos( $surface_row_actions['trash'], 'pgds_surface=video' ), 'row trash actions preserve their return surface' );

	$GLOBALS['pagenow'] = 'post.php';
	$_GET                = array( 'post' => $surface_fixture_ids['video'] );
	$inferred_list_url   = pgds_editorial_admin_url( admin_url( 'edit.php' ), 'edit.php', null );
	$preview_url         = pgds_editorial_preview_post_link( home_url( '/?p=' . $surface_fixture_ids['video'] . '&preview=true' ), get_post( $surface_fixture_ids['video'] ) );
	pgds_cms_editor_assert( false !== strpos( $inferred_list_url, 'pgds_surface=video' ), 'editor links infer their list surface when the URL has no explicit context' );
	pgds_cms_editor_assert( false !== strpos( $preview_url, 'pgds_surface=video' ), 'preview links preserve the inferred editorial surface' );

	$_GET                = $surface_url_previous_get;
	$GLOBALS['pagenow']  = $surface_url_previous_pagenow;
	pgds_cms_editor_assert( pgds_editorial_view_count( 'video', 'draft' ) >= 1, 'native Video view counts use the Video surface predicate' );
	pgds_cms_editor_assert( pgds_editorial_view_count( 'article', 'pgds_needs_classification' ) >= 3, 'Article review view count uses the classification predicate' );

	$composed_query = new WP_Query();
	$composed_query->init();
	$existing_meta_query = array(
		'relation' => 'OR',
		array( 'key' => '_pgds_source', 'value' => 'one' ),
		array( 'key' => '_pgds_source', 'value' => 'two' ),
	);
	$composed_query->set( 'meta_query', $existing_meta_query );
	$previous_get       = $_GET;
	$previous_pagenow   = $GLOBALS['pagenow'] ?? null;
	$previous_wp_query  = $GLOBALS['wp_query'] ?? null;
	$previous_the_query = $GLOBALS['wp_the_query'] ?? null;
	$GLOBALS['pagenow'] = 'edit.php';
	$_GET                = array( 'pgds_filter' => 'featured' );
	$GLOBALS['wp_the_query'] = $composed_query;
	$GLOBALS['wp_query'] = $composed_query;
	set_current_screen( 'edit-post' );
	pgds_admin_filter_apply( $composed_query );
	$_GET                = $previous_get;
	$GLOBALS['pagenow']  = $previous_pagenow;
	$GLOBALS['wp_the_query'] = $previous_the_query;
	$GLOBALS['wp_query'] = $previous_wp_query;
	$composed_meta_query = $composed_query->get( 'meta_query' );
	pgds_cms_editor_assert(
		'AND' === ( $composed_meta_query['relation'] ?? '' ) && $existing_meta_query === ( $composed_meta_query[0] ?? array() ),
		'PGDS list filters compose with an existing nested meta query'
	);

	$switch_post_id = $surface_fixture_ids['article'];
	update_post_meta( $switch_post_id, '_pgds_source_id', 'surface-switch-source' );
	update_post_meta( $switch_post_id, '_pgds_youtube_dur', '321' );
	$switch_result = pgds_apply_editorial_classification( $switch_post_id, 'emagazine' );
	pgds_cms_editor_assert( ! is_wp_error( $switch_result ), 'classification helper switches Article to E-magazine' );
	$switch_categories = array_map( 'intval', wp_get_post_categories( $switch_post_id ) );
	pgds_cms_editor_assert( in_array( $surface_terms['emagazine'], $switch_categories, true ), 'surface switch assigns the new primary category' );
	pgds_cms_editor_assert( ! in_array( $surface_terms['tin-phat-su'], $switch_categories, true ), 'surface switch removes the previous primary category' );
	pgds_cms_editor_assert( in_array( $surface_terms['phat-tich'], $switch_categories, true ), 'surface switch preserves secondary categories' );
	pgds_cms_editor_assert_meta( $switch_post_id, '_pgds_source_id', 'surface-switch-source', 'surface switch preserves importer identity' );
	pgds_cms_editor_assert_meta( $switch_post_id, '_pgds_youtube_dur', '321', 'surface switch preserves synchronization metadata' );

	$confirm_post_id = $surface_fixture_ids['emagazine'];
	pgds_cms_editor_submit(
		$confirm_post_id,
		array(
			'pgds_surface'              => 'video',
			'pgds_article_primary_slug' => '',
		),
		'valid',
		null,
		array( 'editorial' )
	);
	pgds_cms_editor_assert( 'emagazine' === pgds_get_editorial_classification( $confirm_post_id )['surface'], 'surface change without confirmation preserves classification' );
	pgds_cms_editor_submit(
		$confirm_post_id,
		array(
			'pgds_surface'                => 'video',
			'pgds_article_primary_slug'   => '',
			'pgds_surface_change_confirm' => '1',
		),
		'valid',
		null,
		array( 'editorial' )
	);
	pgds_cms_editor_assert( 'video' === pgds_get_editorial_classification( $confirm_post_id )['surface'], 'confirmed draft surface change updates classification' );

	$rest_missing_classification = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts',
		array(
			'status' => 'publish',
			'title'  => 'PGDS rejected unclassified publish ' . $token,
		)
	);
	pgds_cms_editor_assert_rest_error( $rest_missing_classification, 'pgds_article_category_required', 400, 'REST blocks publishing an unclassified Article' );

	$rest_video_without_youtube = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts',
		array(
			'status'     => 'publish',
			'title'      => 'PGDS rejected incomplete video ' . $token,
			'categories' => array( $surface_terms['video'] ),
			'meta'       => array( '_pgds_primary_cat' => $surface_terms['video'] ),
		)
	);
	pgds_cms_editor_assert_rest_error( $rest_video_without_youtube, 'pgds_video_requires_youtube', 400, 'REST blocks publishing a Video without a YouTube ID' );

	$rest_video_draft = pgds_cms_editor_rest_request(
		'POST',
		'/wp/v2/posts',
		array(
			'status'     => 'draft',
			'title'      => 'PGDS incomplete video draft ' . $token,
			'categories' => array( $surface_terms['video'] ),
			'meta'       => array( '_pgds_primary_cat' => $surface_terms['video'] ),
		)
	);
	$rest_video_draft_data = $rest_video_draft->get_data();
	pgds_cms_editor_assert( 201 === $rest_video_draft->get_status(), 'REST allows an incomplete Video draft' );
	if ( isset( $rest_video_draft_data['id'] ) ) {
		$pgds_cms_editor_posts[] = (int) $rest_video_draft_data['id'];
	}

	$pattern_registry = WP_Block_Patterns_Registry::get_instance();
	foreach ( array( 'emagazine-chapter-heading', 'emagazine-wide-image', 'emagazine-full-image', 'emagazine-image-pair', 'emagazine-pull-quote' ) as $pattern_slug ) {
		pgds_cms_editor_assert( $pattern_registry->is_registered( 'pgds/' . $pattern_slug ), sprintf( '%s E-magazine pattern is registered', $pattern_slug ) );
	}
	$chapter_pattern = $pattern_registry->get_registered( 'pgds/emagazine-chapter-heading' );
	pgds_cms_editor_assert(
		false !== strpos( (string) ( $chapter_pattern['content'] ?? '' ), 'pgds-emagazine-chapter__number">01<' ),
		'E-magazine chapter pattern keeps the numbered marker contract'
	);
	$recommendation_ids = array();
	foreach ( array( 'emagazine', 'article' ) as $recommendation_surface ) {
		$term_id = 'emagazine' === $recommendation_surface ? $surface_terms['emagazine'] : $surface_terms['tin-phat-su'];
		for ( $index = 0; $index < 5; $index++ ) {
			$recommendation_id = wp_insert_post(
				array(
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_title'    => sprintf( 'PGDS %s recommendation %d %s', $recommendation_surface, $index, $token ),
					'post_content'  => 'Recommendation fixture.',
					'post_category' => array( $term_id ),
				),
				true
			);
			if ( is_wp_error( $recommendation_id ) ) {
				throw new RuntimeException( 'Could not create E-magazine recommendation fixture.' );
			}
			$recommendation_id          = (int) $recommendation_id;
			$pgds_cms_editor_posts[]     = $recommendation_id;
			$recommendation_ids[]        = $recommendation_id;
			update_post_meta( $recommendation_id, '_pgds_primary_cat', $term_id );
		}
	}
	ob_start();
	pgds_render_meta_box( get_post( $recommendation_ids[1] ) );
	$emagazine_metabox = ob_get_clean();
	foreach ( array( 'sapo', 'cover', 'caption', 'author', 'credit', 'chapter' ) as $check_key ) {
		pgds_cms_editor_assert( false !== strpos( $emagazine_metabox, 'data-pgds-check="' . $check_key . '"' ), sprintf( 'E-magazine checklist includes %s guidance', $check_key ) );
	}
	pgds_cms_editor_assert( false !== strpos( $emagazine_metabox, 'không chặn lưu hoặc xuất bản' ), 'E-magazine checklist is advisory rather than a publish guard' );

	$recommendations = pgds_emagazine_more_posts( $recommendation_ids[0] );
	pgds_cms_editor_assert( 4 === count( $recommendations['emagazine'] ) && 4 === count( $recommendations['latest'] ), 'E-magazine detail returns two complete four-post recommendation groups' );
	pgds_cms_editor_assert( ! in_array( $recommendation_ids[0], wp_list_pluck( $recommendations['emagazine'], 'ID' ), true ), 'E-magazine recommendations exclude the current post' );
	foreach ( $recommendations['emagazine'] as $recommendation ) {
		pgds_cms_editor_assert( 'emagazine' === pgds_get_editorial_classification( $recommendation->ID )['surface'], 'E-magazine recommendation grid contains only canonical E-magazine posts' );
	}
	foreach ( $recommendations['latest'] as $recommendation ) {
		pgds_cms_editor_assert( 'emagazine' !== pgds_get_editorial_classification( $recommendation->ID )['surface'], 'latest recommendation strip excludes E-magazine posts' );
	}

	$previous_menu = $GLOBALS['menu'] ?? array();
	$previous_submenu = $GLOBALS['submenu'] ?? array();
	$GLOBALS['menu'] = array(
		5  => array( 'Posts', 'edit_posts', 'edit.php' ),
		25 => array( 'Comments', 'moderate_comments', 'edit-comments.php' ),
	);
	$GLOBALS['submenu']['edit.php'] = array( array( 'All Posts', 'edit_posts', 'edit.php' ) );
	pgds_register_editorial_surface_menus();
	$surface_menu_urls = array();
	foreach ( $GLOBALS['menu'] as $menu_item ) {
		if ( isset( $menu_item[2] ) && str_contains( $menu_item[2], 'pgds_surface=' ) ) {
			$surface_menu_urls[] = $menu_item[2];
		}
	}
	pgds_cms_editor_assert( 4 === count( $surface_menu_urls ), 'admin sidebar exposes exactly four top-level editorial menus' );
	$menu_slugs = wp_list_pluck( $GLOBALS['menu'], 2 );
	pgds_cms_editor_assert( in_array( 'edit-comments.php', $menu_slugs, true ), 'native Comments screen remains available for moderation and deletion' );
	$GLOBALS['menu'] = $previous_menu;
	$GLOBALS['submenu'] = $previous_submenu;

	$lunar_post_type = get_post_type_object( 'pgds_lunar_note' );
	pgds_cms_editor_assert(
		$lunar_post_type instanceof WP_Post_Type && $lunar_post_type->show_ui && ! $lunar_post_type->show_in_menu,
		'Lunar calendar keeps its management screen but stays hidden from the editorial menu'
	);

	$GLOBALS['submenu']['themes.php'] = array(
		array( 'Themes', 'switch_themes', 'themes.php' ),
		array( 'Menus', 'edit_theme_options', 'nav-menus.php' ),
	);
	pgds_remove_menu_management();
	$menu_slugs = wp_list_pluck( $GLOBALS['submenu']['themes.php'], 2 );
	pgds_cms_editor_assert( ! in_array( 'nav-menus.php', $menu_slugs, true ), 'unused menu-management screen is hidden from Appearance' );
	unset( $GLOBALS['submenu']['themes.php'] );

} catch ( Throwable $exception ) {
	pgds_cms_editor_assert( false, $exception->getMessage() );
} finally {
	pgds_cms_editor_cleanup();
}

if ( $pgds_cms_editor_failures > 0 ) {
	WP_CLI::error( sprintf( 'CMS editor regression suite failed with %d issue(s).', $pgds_cms_editor_failures ) );
}

WP_CLI::success( 'CMS editor regression suite passed.' );
