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
 * @return void
 */
function pgds_cms_editor_submit( $post_id, array $fields, $nonce = 'valid', $post = null ) {
	$previous_post = $_POST;
	$_POST          = $fields;
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
	);
	foreach ( $required_functions as $function ) {
		pgds_cms_editor_assert( function_exists( $function ), sprintf( '%s is available to the article editor', $function ) );
	}
	if ( array_filter( $required_functions, static function ( $function ) {
		return ! function_exists( $function );
	} ) ) {
		throw new RuntimeException( 'The current theme does not expose the approved article-editor API.' );
	}

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

	$groups          = pgds_meta_groups();
	$fields          = pgds_meta_fields();
	$expected_groups = array(
		'editorial' => array( '_pgds_sapo', '_pgds_primary_cat', '_pgds_source', '_pgds_display_author' ),
		'homepage'  => array( '_pgds_is_featured', '_pgds_feature_rank', '_pgds_photo_story' ),
		'video'     => array( '_pgds_youtube_id', '_pgds_youtube_dur' ),
	);
	foreach ( $expected_groups as $group => $keys ) {
		pgds_cms_editor_assert( isset( $groups[ $group ]['label'], $groups[ $group ]['description'] ), sprintf( '%s field group has editor guidance', $group ) );
		foreach ( $keys as $key ) {
			pgds_cms_editor_assert( isset( $fields[ $key ] ) && $group === $fields[ $key ]['group'], sprintf( '%s belongs to the %s group', $key, $group ) );
		}
	}
	pgds_cms_editor_assert( isset( $fields['_pgds_youtube_dur']['editable'] ) && ! $fields['_pgds_youtube_dur']['editable'], 'video duration is rendered read-only' );
		pgds_cms_editor_assert( in_array( '_pgds_youtube_dur', pgds_synchronized_meta_keys(), true ), 'video duration remains synchronization-owned' );
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
	pgds_cms_editor_assert_meta( $post_id, '_pgds_youtube_id', 'M7lc1UVf-VE', 'YouTube watch URL normalizes to its canonical ID' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_youtube_dur', '367', 'editor input cannot overwrite synchronized video duration' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_youtube_title', 'Legacy synchronized title', 'editor input cannot overwrite synchronized video title' );
	pgds_cms_editor_assert_meta( $post_id, '_pgds_video_unavailable', '1', 'editor input cannot overwrite synchronized video status' );

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
			false !== strpos( $redirect_metabox_markup, 'aria-describedby="_pgds_primary_cat-help"' ),
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
		pgds_cms_editor_assert( 'PGDS REST valid ' . $token === get_post( $rest_post_id )->post_title, sprintf( 'REST %s rejection preserves the title', $readonly_key ) );
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
	pgds_cms_editor_assert( 2 === substr_count( $metabox_markup, '<output' ), 'video duration and synchronization status render as read-only outputs' );
	pgds_cms_editor_assert( 0 === preg_match( '/<output[^>]+name=/', $metabox_markup ), 'synchronization-owned outputs have no writable form name' );

} catch ( Throwable $exception ) {
	pgds_cms_editor_assert( false, $exception->getMessage() );
} finally {
	pgds_cms_editor_cleanup();
}

if ( $pgds_cms_editor_failures > 0 ) {
	WP_CLI::error( sprintf( 'CMS editor regression suite failed with %d issue(s).', $pgds_cms_editor_failures ) );
}

WP_CLI::success( 'CMS editor regression suite passed.' );
