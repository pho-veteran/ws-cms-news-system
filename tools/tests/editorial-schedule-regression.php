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
	foreach ( $ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	wp_set_current_user( $saved_user );
}

if ( $failures ) {
	WP_CLI::error( implode( "\n", $failures ) );
}
WP_CLI::success( 'Editorial schedule regressions passed.' );
