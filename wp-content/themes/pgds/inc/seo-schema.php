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
 * JSON_HEX_TAG is what makes this safe, and JSON_UNESCAPED_SLASHES is what made it unsafe.
 *
 * json_encode() never escapes `<` or `>` on its own. What normally prevents a string from
 * closing the surrounding <script> block is the DEFAULT slash escaping: `</script>` becomes
 * `<\/script>`, which the HTML parser does not recognise as an end tag. Passing
 * JSON_UNESCAPED_SLASHES switched that single protection off.
 *
 * Reproduced end to end. Setting `_pgds_sapo` to
 *
 *   x</script><script>alert(1)</script>
 *
 * on a post with a video rendered, verbatim in <head>:
 *
 *   ..."description":"x</script><script>alert(1)</script>","thumbnailUrl":[...
 *
 * i.e. live, executing markup — for every visitor including logged-in administrators. The
 * reachable source is the import (§9): validate_record() does not inspect `sapo` and
 * create_post() stored it verbatim, so a single crafted record in the source JSON was
 * enough. The editor UI was never affected, because sanitize_textarea_field() strips tags
 * on save — which is precisely why this could not be found by using the admin.
 *
 * Fixed at both ends: the meta is sanitised on import (see create_post()), and the encoder
 * now hex-escapes tag and ampersand characters so no future value can break out regardless
 * of how it was stored. JSON_UNESCAPED_SLASHES is dropped as well; readable URLs in the
 * markup are not worth being one careless string away from XSS. JSON_UNESCAPED_UNICODE is
 * kept so Vietnamese headlines stay legible rather than becoming \uXXXX escapes.
 *
 * @param array $data Data.
 */
function pgds_print_jsonld( $data ) {
	echo "\n" . '<script type="application/ld+json">'
		. wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP )
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
 * Keep /video-sitemap.xml free of the canonical trailing slash.
 *
 * With a '/%postname%/' permalink structure, redirect_canonical() 301s
 * /video-sitemap.xml to /video-sitemap.xml/ . Search engines fetch the URL we
 * publish, and a sitemap that answers 301 instead of 200 is a needless hop; the
 * trailing-slash form is also wrong for a file-style path.
 *
 * @param string $redirect_url  The URL core wants to redirect to.
 * @return string|false False to cancel the redirect.
 */
function pgds_video_sitemap_no_canonical_redirect( $redirect_url ) {
	if ( get_query_var( 'pgds_video_sitemap' ) ) {
		return false;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'pgds_video_sitemap_no_canonical_redirect' );

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
		// §6.3: a private / removed / age-restricted video is omitted, for the same
		// reason VideoObject is suppressed for it — submitting a dead video to a
		// sitemap invites crawl errors.
		if ( '1' === get_post_meta( $p->ID, '_pgds_video_unavailable', true ) ) {
			continue;
		}
		$thumb = get_post_meta( $p->ID, '_pgds_youtube_poster', true );
		if ( ! $thumb ) {
			// Falls back to YouTube's own CDN only when the local poster has not been
			// generated yet (wp pgds yt-sync downloads it). This is a sitemap
			// reference, not a hotlink on a rendered page, and a thumbnail_loc is
			// required for the entry to be valid.
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

/**
 * Advertise the video sitemap in robots.txt.
 *
 * §7 makes the video sitemap a deliverable, but nothing pointed a crawler at it: robots.txt
 * listed only core's `wp-sitemap.xml`, and /video-sitemap.xml is not referenced from any
 * page. A sitemap nobody can discover does the job of no sitemap at all — the entries are
 * only found if the URL is submitted by hand in Search Console.
 *
 * Appended rather than replacing the output, so core's own `Sitemap:` line and any line the
 * SEO plugin adds both survive. Emitted only when there is at least one eligible video: a
 * `Sitemap:` directive pointing at an empty urlset is a crawl error rather than a hint.
 *
 * @param string $output Existing robots.txt body.
 * @param bool   $public Whether the site is set to be indexed.
 * @return string
 */
function pgds_robots_video_sitemap( $output, $public ) {
	if ( ! $public ) {
		// Discouraged-from-indexing sites get core's minimal output; do not add to it.
		return $output;
	}

	$q = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_pgds_youtube_id',
					'compare' => 'EXISTS',
				),
				// Mirrors the sitemap body, which omits unavailable videos (§6.3). Without
				// this, a site whose only videos are all private would advertise an empty
				// sitemap.
				array(
					'key'     => '_pgds_video_unavailable',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	if ( ! $q->posts ) {
		return $output;
	}

	return $output . "\nSitemap: " . esc_url_raw( home_url( '/video-sitemap.xml' ) ) . "\n";
}
add_filter( 'robots_txt', 'pgds_robots_video_sitemap', 10, 2 );
