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
 * Whether a supported SEO plugin owns article schema and metadata.
 *
 * @return bool
 */
function pgds_seo_plugin_owns_schema() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_PLUGIN_VERSION' );
}

/**
 * Fallback <meta name="description"> - ONLY when no SEO plugin owns it.
 *
 * Same division of labour as pgds_schema_article(): if an SEO plugin is active it
 * emits the description and the theme must stay out of the way, because two
 * description tags on one page is worse than none. With no plugin installed the
 * category archives shipped without any description at all, which is the one SEO
 * failure the audit of /category/song-an-lanh/ reported.
 *
 * The term description is author-entered and may contain markup, so it is stripped
 * to plain text, trimmed to a search-snippet length, and escaped as an attribute.
 */
function pgds_meta_description() {
	if ( pgds_seo_plugin_owns_schema() ) {
		return;
	}

	$text = '';

	if ( is_category() || is_tax() || is_tag() ) {
		$text = term_description();
	} elseif ( is_singular( 'post' ) ) {
		$text = pgds_sapo( get_queried_object_id() );
	} elseif ( is_front_page() || is_home() ) {
		$text = get_bloginfo( 'description' );
	}

	$text = trim( wp_strip_all_tags( (string) $text, true ) );
	if ( '' === $text ) {
		return;
	}

	printf(
		'<meta name="description" content="%s">' . "\n",
		esc_attr( wp_trim_words( $text, 30, '' ) )
	);
}
add_action( 'wp_head', 'pgds_meta_description', 5 );

/**
 * NewsMediaOrganization - site-wide, printed in <head>.
 */
function pgds_schema_organization() {
	if ( ! is_front_page() && ! is_home() ) {
		return;
	}
	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'NewsMediaOrganization',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
		'logo'     => array(
			'@type' => 'ImageObject',
			'url'   => PGDS_LOGO_URI,
		),
	);
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
	if ( ! pgds_is_video_indexable( $post_id ) ) {
		return;
	}
	$vid = pgds_validate_youtube_id( get_post_meta( $post_id, '_pgds_youtube_id', true ) );

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
	if (
		pgds_seo_plugin_owns_schema() ||
		! ( defined( 'PGDS_EMIT_ARTICLE_SCHEMA' ) && PGDS_EMIT_ARTICLE_SCHEMA )
	) {
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
 * Whether one post is eligible for VideoObject and the video sitemap.
 *
 * Classification, assignment, YouTube ID and availability must agree. This prevents
 * incidental YouTube metadata on an Article from leaking into video search results.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function pgds_is_video_indexable( $post_id ) {
	$post_id = (int) $post_id;
	return $post_id > 0 &&
		'publish' === get_post_status( $post_id ) &&
		'video' === pgds_detail_layout( $post_id ) &&
		'' !== pgds_validate_youtube_id( get_post_meta( $post_id, '_pgds_youtube_id', true ) ) &&
		'1' !== (string) get_post_meta( $post_id, '_pgds_video_unavailable', true );
}

/**
 * Query arguments shared by the video sitemap and its robots advertisement.
 *
 * @param int  $posts_per_page Maximum rows, or -1 for the complete sitemap.
 * @param bool $ids_only       Return IDs instead of post objects.
 * @return array<string,mixed>
 */
function pgds_video_index_query_args( $posts_per_page = -1, $ids_only = false ) {
	$video = pgds_category_term( 'video' );
	if ( ! $video ) {
		return array( 'post__in' => array( 0 ) );
	}

	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $posts_per_page,
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => array( (int) $video->term_id ),
			),
		),
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'   => '_pgds_primary_cat',
				'value' => (string) $video->term_id,
			),
			array(
				'key'     => '_pgds_youtube_id',
				'value'   => '^[A-Za-z0-9_-]{11}$',
				'compare' => 'REGEXP',
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => '_pgds_video_unavailable',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_pgds_video_unavailable',
					'value'   => '1',
					'compare' => '!=',
				),
			),
		),
	);
	if ( $ids_only ) {
		$args['fields'] = 'ids';
	}
	return $args;
}

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

	$q = new WP_Query( pgds_video_index_query_args() );

	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

	foreach ( $q->posts as $p ) {
		if ( ! pgds_is_video_indexable( $p->ID ) ) {
			continue;
		}
		$vid = pgds_validate_youtube_id( get_post_meta( $p->ID, '_pgds_youtube_id', true ) );
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

	$q = new WP_Query( pgds_video_index_query_args( 1, true ) );
	if ( ! $q->posts ) {
		return $output;
	}

	return $output . "\nSitemap: " . esc_url_raw( home_url( '/video-sitemap.xml' ) ) . "\n";
}
add_filter( 'robots_txt', 'pgds_robots_video_sitemap', 10, 2 );
