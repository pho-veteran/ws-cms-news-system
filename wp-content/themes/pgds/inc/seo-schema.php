<?php
/**
 * Schema owned by the THEME (proposal §7): VideoObject + NewsMediaOrganization + video sitemap.
 * The remaining schema (NewsArticle, BreadcrumbList, WebSite) is emitted by the SEO plugin
 * -> avoid duplication. If the plugin is NOT installed, set PGDS_EMIT_ARTICLE_SCHEMA=true so the theme handles it.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NewsMediaOrganization - site-wide, printed in <head>.
 */
function pgds_schema_organization() {
	if ( ! is_front_page() && ! is_home() ) {
		return;
	}
	$logo_id  = (int) get_theme_mod( 'custom_logo' );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'NewsMediaOrganization',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
	);
	if ( $logo_url ) {
		$data['logo'] = array(
			'@type' => 'ImageObject',
			'url'   => $logo_url,
		);
	}
	pgds_print_jsonld( $data );
}
add_action( 'wp_head', 'pgds_schema_organization', 20 );

/**
 * VideoObject for posts with _pgds_youtube_id + valid data.
 * Fallback (proposal §6.3): missing data / video removed -> do NOT emit.
 */
function pgds_schema_video() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$post_id = get_the_ID();
	$vid     = get_post_meta( $post_id, '_pgds_youtube_id', true );
	if ( ! $vid ) {
		return;
	}
	// If the video is marked unavailable -> don't emit schema.
	if ( '1' === get_post_meta( $post_id, '_pgds_video_unavailable', true ) ) {
		return;
	}

	$dur   = (int) get_post_meta( $post_id, '_pgds_youtube_dur', true );
	$thumb = get_post_meta( $post_id, '_pgds_youtube_poster', true );
	if ( ! $thumb ) {
		$thumb = 'https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg';
	}

	$data = array(
		'@context'     => 'https://schema.org',
		'@type'        => 'VideoObject',
		'name'         => get_the_title(),
		'description'  => pgds_sapo( $post_id ) ? pgds_sapo( $post_id ) : get_the_title(),
		'thumbnailUrl' => array( $thumb ),
		'uploadDate'   => get_the_date( 'c' ),
		'embedUrl'     => 'https://www.youtube-nocookie.com/embed/' . $vid,
		'contentUrl'   => 'https://www.youtube.com/watch?v=' . $vid,
	);
	if ( $dur > 0 ) {
		$data['duration'] = pgds_iso8601_duration( $dur );
	}
	pgds_print_jsonld( $data );
}
add_action( 'wp_head', 'pgds_schema_video', 21 );

/**
 * NewsArticle - ONLY when there's no SEO plugin (avoid duplication).
 */
function pgds_schema_article() {
	if ( ! ( defined( 'PGDS_EMIT_ARTICLE_SCHEMA' ) && PGDS_EMIT_ARTICLE_SCHEMA ) ) {
		return;
	}
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$data = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'NewsArticle',
		'headline'      => get_the_title(),
		'datePublished' => get_the_date( 'c' ),
		'dateModified'  => get_the_modified_date( 'c' ),
		'author'        => array(
			'@type' => 'Person',
			'name'  => get_the_author(),
		),
		'mainEntityOfPage' => get_permalink(),
	);
	if ( has_post_thumbnail() ) {
		$data['image'] = array( get_the_post_thumbnail_url( null, 'pgds-lead' ) );
	}
	pgds_print_jsonld( $data );
}
add_action( 'wp_head', 'pgds_schema_article', 22 );

/**
 * Print JSON-LD safely.
 *
 * @param array $data Data.
 */
function pgds_print_jsonld( $data ) {
	echo "\n" . '<script type="application/ld+json">'
		. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. '</script>' . "\n";
}

/**
 * Seconds -> ISO-8601 duration (PT#H#M#S).
 *
 * @param int $seconds Seconds.
 * @return string
 */
function pgds_iso8601_duration( $seconds ) {
	$seconds = (int) $seconds;
	$h       = floor( $seconds / 3600 );
	$m       = floor( ( $seconds % 3600 ) / 60 );
	$s       = $seconds % 60;
	$out     = 'PT';
	if ( $h ) {
		$out .= $h . 'H';
	}
	if ( $m ) {
		$out .= $m . 'M';
	}
	if ( $s || 'PT' === $out ) {
		$out .= $s . 'S';
	}
	return $out;
}

/* =========================================================================
 * VIDEO SITEMAP  ->  /video-sitemap.xml
 * ======================================================================= */

/**
 * Register rewrite + query var.
 */
function pgds_video_sitemap_rewrite() {
	add_rewrite_rule( '^video-sitemap\.xml$', 'index.php?pgds_video_sitemap=1', 'top' );
}
add_action( 'init', 'pgds_video_sitemap_rewrite' );

function pgds_video_sitemap_qv( $vars ) {
	$vars[] = 'pgds_video_sitemap';
	return $vars;
}
add_filter( 'query_vars', 'pgds_video_sitemap_qv' );

/**
 * Output the video sitemap XML.
 */
function pgds_render_video_sitemap() {
	if ( ! get_query_var( 'pgds_video_sitemap' ) ) {
		return;
	}

	$q = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => '_pgds_youtube_id',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

	foreach ( $q->posts as $p ) {
		$vid = get_post_meta( $p->ID, '_pgds_youtube_id', true );
		if ( ! $vid ) {
			continue;
		}
		$thumb = get_post_meta( $p->ID, '_pgds_youtube_poster', true );
		if ( ! $thumb ) {
			$thumb = 'https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg';
		}
		echo "  <url>\n";
		echo '    <loc>' . esc_url( get_permalink( $p ) ) . "</loc>\n";
		echo "    <video:video>\n";
		echo '      <video:thumbnail_loc>' . esc_url( $thumb ) . "</video:thumbnail_loc>\n";
		echo '      <video:title>' . esc_html( get_the_title( $p ) ) . "</video:title>\n";
		echo '      <video:description>' . esc_html( wp_strip_all_tags( pgds_sapo( $p ) ) ) . "</video:description>\n";
		echo '      <video:player_loc>' . esc_url( 'https://www.youtube-nocookie.com/embed/' . $vid ) . "</video:player_loc>\n";
		echo '      <video:publication_date>' . esc_html( get_the_date( 'c', $p ) ) . "</video:publication_date>\n";
		echo "    </video:video>\n";
		echo "  </url>\n";
	}

	echo '</urlset>';
	exit;
}
add_action( 'template_redirect', 'pgds_render_video_sitemap' );
