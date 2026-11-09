<?php
/**
 * Pending-review regressions. Run through WP-CLI against an installed PGDS site:
 * wp eval-file tools/tests/editorial-workflow-regression.php
 * All test records are isolated and removed on exit.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$failures = 0;
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( $condition ) {
		WP_CLI::log( 'PASS: ' . $message );
	} else {
		++$failures;
		WP_CLI::warning( 'FAIL: ' . $message );
	}
};
$original_user = get_current_user_id();
$original_post = $_POST;
$original_request = $_REQUEST;
$post_id = 0;
$reporter_id = 0;
$editor_id = 0;
$die_handler = static function () {
	return static function ( $message ) {
		throw new RuntimeException( (string) $message );
	};
};

try {
	$empty_gallery = '<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped"></figure><!-- /wp:gallery -->';
	foreach ( array(
		'empty paragraph' => '<!-- wp:paragraph --><p>&nbsp;</p><!-- /wp:paragraph -->',
		'empty gallery'   => $empty_gallery,
		'empty image'     => '<!-- wp:image --><figure><img></figure><!-- /wp:image -->',
		'empty video'     => '<!-- wp:video --><figure><video controls></video></figure><!-- /wp:video -->',
		'empty audio'     => '<!-- wp:audio --><audio><source src=" "></audio><!-- /wp:audio -->',
		'empty embed'     => '<!-- wp:embed /-->',
		'comment only'    => '<!-- <img src="/not-visible.jpg"> -->',
	) as $label => $body ) {
		$assert( ! pgds_content_has_body( $body ), $label . ' does not satisfy the body requirement' );
	}
	foreach ( array(
		'text'        => '<p>Nội dung bài.</p>',
		'image'       => '<!-- wp:image --><figure><img src="/photo.jpg"></figure><!-- /wp:image -->',
		'gallery'     => '<!-- wp:gallery --><figure><img srcset="/photo.jpg 640w"></figure><!-- /wp:gallery -->',
		'audio'       => '<audio controls><source src="/recording.mp3"></audio>',
		'video'       => '<video src="/movie.mp4" controls></video>',
		'video source' => '<video controls><source src="/movie.mp4"></video>',
		'iframe'      => '<iframe src="https://www.youtube-nocookie.com/embed/abcdefghijk"></iframe>',
		'embed URL'   => '<!-- wp:embed --><figure><div>https://www.youtube.com/watch?v=abcdefghijk</div></figure><!-- /wp:embed -->',
	) as $label => $body ) {
		$assert( pgds_content_has_body( $body ), $label . ' satisfies the body requirement' );
	}

	$reporter_id = wp_insert_user( array(
		'user_login' => 'pgds-review-test-' . wp_generate_uuid4(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'pgds_reporter',
	) );
	if ( is_wp_error( $reporter_id ) ) {
		throw new RuntimeException( $reporter_id->get_error_message() );
	}
	wp_set_current_user( $reporter_id );
	foreach ( array( 'publish_posts', 'edit_others_posts', 'create_users', 'promote_users', 'delete_users' ) as $cap ) {
		$assert( ! current_user_can( $cap ), 'reporters cannot ' . $cap );
	}
	$assert( current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' ), 'reporters can write drafts and upload media' );
	$editor_id = wp_insert_user( array(
		'user_login' => 'pgds-editor-test-' . wp_generate_uuid4(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'editor',
	) );
	if ( is_wp_error( $editor_id ) ) {
		throw new RuntimeException( $editor_id->get_error_message() );
	}
	wp_set_current_user( $editor_id );
	$assert( ! current_user_can( 'publish_posts' ) && ! current_user_can( 'edit_published_posts' ), 'default editors cannot publish or change published articles' );
	$assert( current_user_can( 'moderate_comments' ), 'default editors retain comment moderation' );
	$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	if ( ! $administrators ) {
		throw new RuntimeException( 'The suite requires an administrator account.' );
	}
	wp_set_current_user( $administrators[0] );
	foreach ( array( 'publish_posts', 'edit_published_posts', 'list_users', 'create_users', 'edit_users', 'promote_users', 'delete_users', 'manage_options' ) as $cap ) {
		$assert( current_user_can( $cap ), 'newsroom administrators retain ' . $cap );
	}
	wp_set_current_user( $reporter_id );
	$term_id = (int) pgds_category_term( 'tin-phat-su' )->term_id;
	$post_id = wp_insert_post( array(
		'post_title'    => 'PGDS pending regression',
		'post_status'   => 'draft',
		'post_author'   => $reporter_id,
		'post_content'  => $empty_gallery,
		'post_category' => array( $term_id ),
		'meta_input'    => array( '_pgds_primary_cat' => $term_id, '_pgds_sapo' => 'Sa-pô kiểm thử.' ),
	), true );
	if ( is_wp_error( $post_id ) ) {
		throw new RuntimeException( $post_id->get_error_message() );
	}
	$rest_save = static function ( array $body ) use ( $post_id ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params( $body );
		return rest_do_request( $request );
	};
	$response = $rest_save( array( 'status' => 'pending' ) );
	$assert( 400 === $response->get_status() && 'pgds_pending_incomplete' === $response->get_data()['code'], 'REST rejects a submission containing only an empty gallery' );
	$assert( 'draft' === get_post_status( $post_id ), 'rejected REST submission stays draft' );

	add_filter( 'wp_die_handler', $die_handler );
	add_filter( 'wp_die_ajax_handler', $die_handler );
	$quick_save = static function ( array $changes = array() ) use ( $post_id, $term_id ) {
		$_POST = array_merge( array(
			'post_ID'       => $post_id,
			'_inline_edit'  => wp_create_nonce( 'inlineeditnonce' ),
			'_status'       => 'pending',
			'post_title'    => get_post( $post_id )->post_title,
			'post_category' => array( $term_id ),
		), $changes );
		$_REQUEST = $_POST;
		try {
			pgds_ajax_validate_pending_submission();
			return '';
		} catch ( RuntimeException $exception ) {
			return $exception->getMessage();
		}
	};
	$assert( str_contains( $quick_save(), 'Còn thiếu: nội dung bài' ), 'Quick Edit returns an inline message explaining the missing body' );
	$assert( '' === $quick_save( array( '_status' => 'draft' ) ), 'Quick Edit still allows incomplete drafts' );
	$_POST = array();
	$response = $rest_save( array( 'status' => 'pending', 'content' => '<figure><img src="/photo.jpg"></figure>' ) );
	$assert( 200 === $response->get_status() && 'pending' === get_post_status( $post_id ), 'REST still accepts a complete media-only article' );
	$assert( '' === $quick_save(), 'Quick Edit accepts complete pending articles' );
	$assert( str_contains( $quick_save( array( 'post_title' => '' ) ), 'Còn thiếu: tít' ), 'Quick Edit explains a title removed from a pending article' );
	$assert( 'PGDS pending regression' === get_post( $post_id )->post_title && 'pending' === get_post_status( $post_id ), 'rejected Quick Edit preserves the stored pending article' );
	$assert( str_contains( $quick_save( array( 'post_category' => array() ) ), 'chuyên mục' ), 'Quick Edit validates submitted categories rather than stored categories' );
	$_POST = array();
	$response = $rest_save( array( 'content' => $empty_gallery ) );
	$assert( 400 === $response->get_status() && 'pending' === get_post_status( $post_id ), 'REST rejects replacing a pending article body with an empty gallery' );
	$response = $rest_save( array( 'status' => 'publish' ) );
	$assert( 403 === $response->get_status() && 'pending' === get_post_status( $post_id ), 'REST forbids a reporter from publishing their pending article' );
	wp_set_current_user( $administrators[0] );
	$response = $rest_save( array( 'status' => 'publish' ) );
	$assert( 200 === $response->get_status() && 'publish' === get_post_status( $post_id ), 'REST lets the newsroom administrator publish a complete submission' );
} catch ( Throwable $exception ) {
	$assert( false, $exception->getMessage() );
} finally {
	$_POST = $original_post;
	$_REQUEST = $original_request;
	remove_filter( 'wp_die_handler', $die_handler );
	remove_filter( 'wp_die_ajax_handler', $die_handler );
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		wp_delete_post( $post_id, true );
	}
	if ( $reporter_id && ! is_wp_error( $reporter_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $reporter_id );
	}
	if ( $editor_id && ! is_wp_error( $editor_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $editor_id );
	}
	wp_set_current_user( $original_user );
}

if ( $failures ) {
	WP_CLI::error( sprintf( 'Editorial workflow regressions: %d failure(s).', $failures ) );
}
WP_CLI::success( 'Editorial workflow regressions passed.' );
