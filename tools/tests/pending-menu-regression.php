<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

global $wpdb, $menu;
$previous_menu = $menu;
$previous_user = get_current_user_id();
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
wp_set_current_user( (int) reset( $admin ) );
$definitions = pgds_editorial_surfaces();
$posts = array();
$reporter = 0;
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$read_badges = static function () use ( $definitions, $wpdb, &$menu ) {
	$menu = array();
	foreach ( array_keys( $definitions ) as $surface ) {
		$menu[ $surface ] = array( $surface, '', '', '', '', 'menu-posts-' . $surface );
	}
	$before = count( $wpdb->queries ?? array() );
	pgds_pending_menu_bubbles();
	$queries = array_filter( array_slice( $wpdb->queries ?? array(), $before ), static function ( $entry ) {
		return str_contains( $entry[0], 'pgds_surface_meta' ) || str_contains( $entry[0], 'SELECT FOUND_ROWS()' );
	} );
	$badges = array_map( static function ( $item ) {
		return preg_match( '/awaiting-mod count-(\d+)/', $item[0], $match ) ? (int) $match[1] : 0;
	}, $menu );
	return array( $badges, count( $queries ) );
};

try {
	$expected = array();
	foreach ( $definitions as $surface => $definition ) {
		$expected[ $surface ] = pgds_editorial_view_count( $surface, 'pending' );
	}
	list( $initial ) = $read_badges();
	$assert( $initial === $expected, 'Initial badges agree with the native pending views.' );
	foreach ( $definitions as $surface => $definition ) {
		$term = (int) pgds_category_term( $definition['primary_slugs'][0] )->term_id;
		$posts[ $surface ] = wp_insert_post( array(
			'post_title'    => 'PGDS pending count ' . $surface,
			'post_status'   => 'pending',
			'post_category' => array( $term ),
			'meta_input'    => array( '_pgds_primary_cat' => $term ),
		) );
		++$expected[ $surface ];
	}
	$posts['unclassified'] = wp_insert_post( array( 'post_title' => 'PGDS unclassified pending count', 'post_status' => 'pending' ) );
	++$expected['article'];
	list( $cold_badges, $cold_queries ) = $read_badges();
	list( $warm_badges, $warm_queries ) = $read_badges();
	WP_CLI::log( sprintf( 'Pending badge queries (cold / warm): %d / %d.', $cold_queries, $warm_queries ) );
	$assert( $cold_badges === $expected && $warm_badges === $expected, 'All four badges count the correct posts, including unclassified Article posts.' );
	$assert( 1 === $cold_queries && 0 === $warm_queries, 'Badge counts use one SQL aggregate when cold and no count query when warm.' );

	wp_update_post( array( 'ID' => $posts['article'], 'post_status' => 'draft' ) );
	--$expected['article'];
	pgds_apply_editorial_classification( $posts['video'], 'article', 'phat-tich' );
	--$expected['video'];
	++$expected['article'];
	list( $updated ) = $read_badges();
	$assert( $expected === $updated, 'Changing status or category invalidates the badge counts.' );

	$reporter = wp_insert_user( array( 'user_login' => 'pgds-count-test-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'pgds_reporter' ) );
	if ( is_wp_error( $reporter ) ) {
		throw new RuntimeException( $reporter->get_error_message() );
	}
	wp_set_current_user( $reporter );
	list( $reporter_badges, $reporter_queries ) = $read_badges();
	$assert( 0 === array_sum( $reporter_badges ) && 0 === $reporter_queries, 'Reporters have no pending badges or count queries.' );
} finally {
	$menu = $previous_menu;
	wp_set_current_user( $previous_user );
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( $reporter && ! is_wp_error( $reporter ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $reporter );
	}
}

if ( $failures ) {
	WP_CLI::error( implode( "\n", $failures ) );
}
WP_CLI::success( 'Pending menu regressions passed.' );
