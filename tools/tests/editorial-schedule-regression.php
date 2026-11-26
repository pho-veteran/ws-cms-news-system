<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};
$saved_user = get_current_user_id();
$saved_post = $_POST;
$saved_request = $_REQUEST;
$ids = array();
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
wp_set_current_user( (int) reset( $admin ) );
$video_id = (int) pgds_category_term( 'video' )->term_id;
$later = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );

$save = static function ( $fields, $id = 0 ) use ( &$ids ) {
	$request = new WP_REST_Request( 'POST', '/wp/v2/posts' . ( $id ? '/' . $id : '' ) );
	$request->set_body_params( $fields );
	$response = rest_do_request( $request );
	if ( 201 === $response->get_status() ) {
		$ids[] = (int) $response->get_data()['id'];
	}
	return $response;
};

try {
	$video = array(
		'title'      => 'PGDS scheduled video',
		'content'    => '<p>Scheduled content.</p>',
		'status'     => 'future',
		'date_gmt'   => str_replace( ' ', 'T', $later ),
		'categories' => array( $video_id ),
		'meta'       => array( '_pgds_primary_cat' => $video_id ),
	);
	$missing_video = $save( $video );
	$assert( 400 === $missing_video->get_status() && 'pgds_video_requires_youtube' === $missing_video->get_data()['code'], 'REST refuses to schedule a video without YouTube.' );
	$unclassified = $save( array_diff_key( $video, array( 'meta' => true, 'categories' => true ) ) );
	$assert( 400 === $unclassified->get_status() && 'pgds_article_category_required' === $unclassified->get_data()['code'], 'REST refuses to schedule an unclassified article.' );
	$video['status'] = 'draft';
	$draft = $save( $video );
	$assert( 201 === $draft->get_status() && 'draft' === $draft->get_data()['status'], 'An incomplete video can still be saved as a draft.' );
	$draft_id = (int) $draft->get_data()['id'];
	$_POST = array( 'pgds_meta_nonce' => wp_create_nonce( 'pgds_meta_save' ) );
	wp_update_post( array( 'ID' => $draft_id, 'post_status' => 'future', 'post_date_gmt' => $later, 'post_date' => get_date_from_gmt( $later ), 'edit_date' => true ) );
	$assert( 'draft' === get_post_status( $draft_id ), 'The classic form returns an incomplete scheduled video to draft.' );
	$_POST = array();

	$video['status'] = 'future';
	$video['meta']['_pgds_youtube_id'] = 'dQw4w9WgXcQ';
	$scheduled = $save( $video );
	$assert( 201 === $scheduled->get_status() && 'future' === $scheduled->get_data()['status'], 'A complete video can be scheduled.' );
	$scheduled_id = (int) $scheduled->get_data()['id'];
	$invalid_update = $save( array( 'meta' => array( '_pgds_youtube_id' => '' ) ), $scheduled_id );
	$assert( 400 === $invalid_update->get_status() && 'dQw4w9WgXcQ' === get_post_meta( $scheduled_id, '_pgds_youtube_id', true ), 'REST rejects clearing the required ID on a scheduled video.' );

	$legacy_id = wp_insert_post( array( 'post_title' => 'PGDS published legacy video', 'post_status' => 'publish', 'post_category' => array( $video_id ), 'meta_input' => array( '_pgds_primary_cat' => $video_id ) ) );
	$ids[] = $legacy_id;
	$date_only = $save( array( 'date_gmt' => $video['date_gmt'] ), $legacy_id );
	$assert( 400 === $date_only->get_status() && 'publish' === get_post_status( $legacy_id ), 'REST rejects moving a legacy video without a YouTube ID into the schedule by date alone.' );

	require_once ABSPATH . 'wp-admin/includes/post.php';
	$quick_edit = static function ( $post_id, $changes = array() ) {
		$_POST = $_REQUEST = array_merge( array(
			'post_ID'       => $post_id,
			'post_type'     => 'post',
			'_status'       => 'future',
			'_inline_edit'  => wp_create_nonce( 'inlineeditnonce' ),
		), $changes );
		$handler = static function () {
			return static function ( $message ) {
				throw new RuntimeException( (string) $message );
			};
		};
		add_filter( 'wp_die_handler', $handler );
		try {
			do_action( 'wp_ajax_inline-save' );
			return '';
		} catch ( RuntimeException $exception ) {
			return $exception->getMessage();
		} finally {
			remove_filter( 'wp_die_handler', $handler );
		}
	};
	$assert( '' === $quick_edit( $scheduled_id ), 'Quick Edit accepts a complete scheduled video.' );
	$assert( '' !== $quick_edit( $scheduled_id, array( 'post_category' => array() ) ) && has_category( $video_id, $scheduled_id ), 'Quick Edit rejects removing a scheduled video’s primary category before saving.' );

	$unclassified_id = wp_insert_post( array( 'post_title' => 'PGDS unclassified admin draft', 'post_status' => 'draft' ) );
	$ids[] = $unclassified_id;
	$category_message = pgds_meta_feedback_messages( 'article' )['pgds_article_category_required'];
	$assert( esc_html( $category_message ) === $quick_edit( $unclassified_id, array( '_status' => 'publish' ) ) && 'draft' === get_post_status( $unclassified_id ), 'Quick Edit rejects immediate publication of an unclassified draft.' );
	$_POST = array(
		'pgds_meta_nonce'    => wp_create_nonce( 'pgds_meta_save' ),
		'pgds_meta_groups'   => array( 'editorial' ),
		'pgds_surface'       => 'article',
	);
	wp_update_post( array( 'ID' => $unclassified_id, 'post_status' => 'draft' ) );
	$feedback = $GLOBALS['pgds_meta_feedback'] ?? array();
	$assert( array() === wp_get_post_categories( $unclassified_id ) && empty( $feedback['errors'] ), 'The classic form saves a draft without asking for a category.' );
	wp_update_post( array( 'ID' => $unclassified_id, 'post_status' => 'publish' ) );
	$assert( 'draft' === get_post_status( $unclassified_id ) && array() === wp_get_post_categories( $unclassified_id ), 'A rejected classic publication stays an uncategorized draft.' );
	$_POST = array();

	$bulk_die = static function () {
		return static function ( $message ) {
			throw new RuntimeException( (string) $message );
		};
	};
	$_REQUEST = array(
		'action'    => 'edit',
		'bulk_edit' => 'Update',
		'_wpnonce'  => wp_create_nonce( 'bulk-posts' ),
		'_status'   => 'publish',
		'post'      => array( $scheduled_id, $unclassified_id ),
	);
	add_filter( 'wp_die_handler', $bulk_die );
	try {
		do_action( 'load-edit.php' );
		$assert( false, 'Bulk Edit must reject an unclassified article before publishing the batch.' );
	} catch ( RuntimeException $exception ) {
		$assert( str_contains( $exception->getMessage(), esc_html( $category_message ) ) && 'future' === get_post_status( $scheduled_id ) && 'draft' === get_post_status( $unclassified_id ), 'Bulk Edit rejects the batch before publication if an article has no primary category.' );
	} finally {
		remove_filter( 'wp_die_handler', $bulk_die );
	}
	$_REQUEST = $saved_request;

	$quick_date = array_combine( array( 'aa', 'mm', 'jj', 'hh', 'mn', 'ss' ), preg_split( '/[- :]/', get_date_from_gmt( $later ) ) );
	$quick_date['edit_date'] = '1';
	$quick_date['_status'] = 'publish';
	$assert( esc_html( pgds_meta_feedback_messages( 'video' )['pgds_video_requires_youtube'] ) === $quick_edit( $legacy_id, $quick_date ), 'Quick Edit validates a published video when its date moves into the future.' );
	$_POST = array();
	$_REQUEST = $saved_request;

	$second = $save( $video );
	$invalid_id = (int) $second->get_data()['id'];
	delete_post_meta( $invalid_id, '_pgds_youtube_id' );
	global $wpdb;
	foreach ( array( $scheduled_id => 'publish', $invalid_id => 'draft' ) as $post_id => $expected ) {
		$past = gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS );
		$wpdb->update( $wpdb->posts, array( 'post_date_gmt' => $past ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
		do_action( 'publish_future_post', $post_id );
		$assert( $expected === get_post_status( $post_id ) && '<p>Scheduled content.</p>' === get_post_field( 'post_content', $post_id ), 'Due posts go to ' . $expected . ' and keep their content.' );
	}
} finally {
	$_POST = $saved_post;
	$_REQUEST = $saved_request;
	foreach ( $ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	wp_set_current_user( $saved_user );
}

if ( $failures ) {
	WP_CLI::error( implode( "\n", $failures ) );
}
WP_CLI::success( 'Editorial schedule regressions passed.' );
