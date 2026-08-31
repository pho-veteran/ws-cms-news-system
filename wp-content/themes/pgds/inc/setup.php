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
 * Vietnamese archive titles.
 *
 * the_archive_title() emits English prefixes from core's translations ("Tag: …",
 * "Author: …", "Month: August 2026"), and archive.php prints it directly. On a
 * Vietnamese news site that surfaced "Author: admin" and "Month: August 2026" as the
 * <h1> of publicly reachable pages — archive.php serves tag, author, date and taxonomy
 * archives (proposal §3.3), so this is four routes, not an edge case.
 *
 * Rewritten here rather than relying on a vi language pack, for the same reason as
 * pgds_weekday_vi(): the install's locale is frequently en_US and the reader must see
 * Vietnamese either way.
 *
 * @param string $title Title from core.
 * @return string
 */
function pgds_archive_title( $title ) {
	if ( is_category() ) {
		return single_cat_title( '', false );
	}
	if ( is_tag() ) {
		/* translators: %s: tag name */
		return sprintf( __( 'Chủ đề: %s', 'pgds' ), single_tag_title( '', false ) );
	}
	if ( is_author() ) {
		/* translators: %s: author display name */
		return sprintf( __( 'Tác giả: %s', 'pgds' ), get_the_author() );
	}
	if ( is_year() ) {
		/* translators: %s: year */
		return sprintf( __( 'Năm %s', 'pgds' ), get_the_date( 'Y' ) );
	}
	if ( is_month() ) {
		/*
		 * Built from the QUERY's year/month, not from a post's timestamp: an archive
		 * title must describe the month that was requested even when the archive is
		 * empty, and get_post_timestamp() would describe whichever post happens to be
		 * first in the loop.
		 *
		 * pgds_month_year_vi() is used for the formatting because it escapes every
		 * literal character — see its docblock for why an unescaped format string
		 * produced "+0702á92 09 năm 2026".
		 */
		$year  = (int) get_query_var( 'year' );
		$month = (int) get_query_var( 'monthnum' );
		if ( $year && $month ) {
			return pgds_month_year_vi( mktime( 0, 0, 0, $month, 1, $year ) );
		}
		return pgds_month_year_vi();
	}
	if ( is_day() ) {
		/* translators: %s: full date */
		return sprintf( __( 'Ngày %s', 'pgds' ), get_the_date( 'd/m/Y' ) );
	}
	if ( is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$tax = get_taxonomy( $term->taxonomy );
			$label = $tax ? $tax->labels->singular_name : '';
			return $label ? $label . ': ' . $term->name : $term->name;
		}
	}
	if ( is_post_type_archive() ) {
		return post_type_archive_title( '', false );
	}

	return $title;
}
add_filter( 'get_the_archive_title', 'pgds_archive_title' );

/**
 * Return 404 for non-public post types instead of silently rendering the front page.
 *
 * pgds_lunar_note is registered `public => false` (it is a sidebar widget's data, not a
 * page), but its permalink still resolved: WordPress fell through to the front page and
 * answered **200**. A soft-404 like that gets indexed as duplicate content under a junk
 * URL, and it exposes an internal content type as if it were an article.
 *
 * Checked on the real query rather than the permalink string, so it also covers feeds
 * and any other route that resolves to the same object.
 */
function pgds_block_private_cpt() {
	if ( is_admin() || is_robots() || is_feed() ) {
		return;
	}

	global $wp_query;

	/*
	 * Case 1 — the URL resolved to a real object of a non-public type.
	 */
	if ( is_singular() ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post ) {
			$type = get_post_type_object( $queried->post_type );
			if ( $type && empty( $type->public ) ) {
				pgds_force_404();
				return;
			}
		}
	}

	/*
	 * Case 2 — the soft-404, which is what actually happened here.
	 *
	 * `pgds_lunar_note` is registered `public => false`, so it is NOT
	 * publicly_queryable and its permalink never resolves to the post. But
	 * register_post_type() still generated 17 rewrite rules for the slug, and with no
	 * matching query WordPress fell back to the HOME query: body_class reported
	 * "home blog", the entire curated front page rendered, and the response was
	 * **200**.
	 *
	 * That is worse than a dead link. Search engines index the front page under a junk
	 * URL as duplicate content, and an internal content type looks like a section of
	 * the site. Because the request never becomes is_singular(), case 1 above cannot
	 * catch it — the only reliable signal is "this is the home query, but the path is
	 * not the home path".
	 */
	if ( is_home() || is_front_page() ) {
		$path = trim( (string) wp_parse_url( add_query_arg( array() ), PHP_URL_PATH ), '/' );

		// Genuinely the home page: empty path, or a /page/N/ pagination path.
		if ( '' === $path || preg_match( '#^page/\d+$#', $path ) ) {
			return;
		}

		// A real posts page (Settings > Reading) is legitimately is_home() on its own
		// path, so never 404 that.
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page ) {
			$posts_path = trim( (string) wp_parse_url( get_permalink( $posts_page ), PHP_URL_PATH ), '/' );
			if ( $posts_path && 0 === strpos( $path, $posts_path ) ) {
				return;
			}
		}

		pgds_force_404();
	}
}
add_action( 'template_redirect', 'pgds_block_private_cpt' );

/**
 * Turn the current request into a real 404 and render the theme's 404 template.
 *
 * status_header() alone is not enough: without set_404() conditional tags still report
 * the original query, so the wrong template renders under a 404 status.
 */
function pgds_force_404() {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	// Render the theme's 404 rather than a blank page, so a stray link is a normal dead
	// end with a search box and recent posts.
	include get_query_template( '404' );
	exit;
}

/**
 * Default content width for the editor.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 760;
}
