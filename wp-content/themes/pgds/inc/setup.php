<?php
/**
 * Theme setup: supports, nav menu, image size.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image sizes declared explicitly to avoid CLS and support srcset.
 * Aspect ratios follow layout v12 closely (16:10, 16:11, 4:3, square).
 */
function pgds_image_sizes() {
	// name => array( width, height, crop )
	return array(
		'pgds-lead'   => array( 960, 600, true ),  // front page hero / post featured image
		'pgds-card'   => array( 480, 300, true ),  // 3-column card, feature-secondary
		'pgds-list'   => array( 360, 220, true ),  // list-item
		'pgds-thumb'  => array( 320, 220, true ),  // media thumb grid (roughly 16:11)
		'pgds-mini'   => array( 144, 108, true ),  // category column mini item
		'pgds-rank'   => array( 140, 100, true ),  // sidebar "Most read"
		'pgds-square' => array( 116, 116, true ),  // Vietnam Buddhism compact-list
	);
}

/**
 * Theme supports + register image sizes + nav menus.
 */
function pgds_setup() {
	load_theme_textdomain( 'pgds', PGDS_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'post-formats',
		array( 'video', 'gallery' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 64,
			'width'       => 240,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	foreach ( pgds_image_sizes() as $name => $spec ) {
		add_image_size( $name, $spec[0], $spec[1], $spec[2] );
	}

	register_nav_menus(
		array(
			'primary' => __( 'Menu chính (7 chuyên mục)', 'pgds' ),
			'footer'  => __( 'Menu chân trang', 'pgds' ),
		)
	);
}
add_action( 'after_setup_theme', 'pgds_setup' );

/**
 * Remove unused features to reduce payload and attack surface.
 */
function pgds_trim_head() {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	// Emoji: remove entirely (not needed, adds JS/CSS weight).
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'pgds_trim_head' );

/**
 * Disable XML-RPC (hardening §9.3). Origin serves web + admin only.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Excerpt length + "read more".
 */
function pgds_excerpt_length() {
	return 32;
}
add_filter( 'excerpt_length', 'pgds_excerpt_length' );

function pgds_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'pgds_excerpt_more' );

/**
 * Default content width for the editor.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 760;
}
