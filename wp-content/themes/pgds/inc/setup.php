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
 * Cac kich thuoc anh khai bao tuong minh de tranh CLS va phuc vu srcset.
 * Ty le bam sat layout v12 (16:10, 16:11, 4:3, vuong).
 */
function pgds_image_sizes() {
	// name => array( width, height, crop )
	return array(
		'pgds-lead'   => array( 960, 600, true ),  // hero trang chu / anh dai dien bai
		'pgds-card'   => array( 480, 300, true ),  // card 3 cot, feature-secondary
		'pgds-list'   => array( 360, 220, true ),  // list-item
		'pgds-thumb'  => array( 320, 220, true ),  // media thumb grid (16:11 xap xi)
		'pgds-mini'   => array( 144, 108, true ),  // mini item cot chuyen muc
		'pgds-rank'   => array( 140, 100, true ),  // sidebar "Doc nhieu nhat"
		'pgds-square' => array( 116, 116, true ),  // compact-list Vietnam Buddhism
	);
}

/**
 * Theme supports + dang ky image size + nav menu.
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
 * Bo cac tinh nang khong dung de giam payload va be mat tan cong.
 */
function pgds_trim_head() {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	// Emoji: bo hoan toan (khong can, nang JS/CSS).
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'pgds_trim_head' );

/**
 * Tat XML-RPC (hardening §9.3). Origin chi phuc vu web + admin.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Do dai excerpt + "doc them".
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
 * Kich thuoc content mac dinh cho editor.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 760;
}
