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
 * Emit the generated image variants as WebP.
 *
 * §9.4 asks `media-variants` to produce "variants + WebP", and §6.2's markup serves the
 * video poster as `/wp-content/uploads/yt/<id>-640.webp`. Nothing in the theme produced a
 * single WebP file: every one of the seven sizes in pgds_image_sizes() was written in the
 * upload's own format, so the nginx rule that already matches `\.webp$` (infra/nginx §5.4)
 * had nothing to serve and the poster URL in the proposal could not exist.
 *
 * Done through core's own `image_editor_output_format` rather than a shunt that writes a
 * second file alongside each JPEG. Core applies the mapping inside
 * WP_Image_Editor::get_output_format(), which means:
 *
 *   - it rewrites the sub-size EXTENSION too, so `_wp_attachment_metadata` records
 *     `...-480x300.webp` and wp_get_attachment_image_url() / srcset return the WebP URL
 *     with no further work — including the poster URL cli-import.php stores;
 *   - the uploaded file itself is never overwritten. Core keeps it and records it in
 *     `metadata['original_image']`, so wp_get_original_image_path() still returns the
 *     JPEG/PNG. Note that `_wp_attached_file` IS repointed at the generated WebP, which
 *     is why media_variants() re-encodes from wp_get_original_image_path() rather than
 *     from get_attached_file() — see the generational-loss note there;
 *   - it is guarded by $this->supports_mime_type(), so a host whose image library lacks a
 *     WebP delegate silently keeps the source format instead of failing the upload.
 *
 * That last point is not hypothetical. On the verification stack Imagick is present but
 * has NO WebP delegate, while GD does, and WordPress selects GD:
 *
 *   Imagick class: yes          Imagick editor webp: no
 *   GD imagewebp(): yes         GD editor webp: yes     -> Editor: WP_Image_Editor_GD
 *
 * so the same install would have quietly produced zero WebP had this been implemented by
 * calling Imagick directly.
 *
 * AVIF is deliberately not used. nginx matches it, but encode cost on the 2 GB / 2 vCPU
 * origin is several times WebP's for a bulk regeneration of 2,000 posts' media (§9.4
 * already has to be niced to avoid swap), and WebP is universally supported by the
 * browsers in §2's matrix.
 *
 * @param array  $formats   Source mime => destination mime.
 * @param string $filename  Path to the image (unused; mapping is format-based).
 * @param string $mime_type Source mime type.
 * @return array
 */
function pgds_webp_output_format( $formats, $filename = '', $mime_type = '' ) {
	// Merged, not replaced: core maps HEIC/HEIF to JPEG here since 6.7 and dropping that
	// would break iPhone uploads, which is most of what a reporter sends in from an event.
	return array_merge(
		(array) $formats,
		array(
			'image/jpeg' => 'image/webp',
			'image/png'  => 'image/webp',
		)
	);
}
add_filter( 'image_editor_output_format', 'pgds_webp_output_format', 10, 3 );

/**
 * Resolve the true uploaded file for an attachment, never a generated WebP.
 *
 * Regenerating variants must read the ORIGINAL upload. Reading the generated WebP instead
 * re-encodes lossy-from-lossy: measured on this install, one extra pass moved a variant
 * from 2308 bytes (md5 38dff48e) to 2328 bytes (md5 7f32480f), and every later pass
 * degrades it further. Nothing errors and the images still render, so the damage is silent.
 *
 * Why not wp_get_original_image_path() on its own: it reads
 * `metadata['original_image']`, which core writes ONLY in
 * _wp_image_meta_replace_original() — i.e. only when it converts or scales the full size.
 * Regenerating from the WebP produces no conversion, so the key is dropped, and from then
 * on the helper cheerfully returns the WebP as the "original". Observed exactly that:
 *
 *   original_image: ABSENT
 *   wp_get_original_image_path(): .../pgds-seed-18-3.webp
 *
 * so a self-healing regeneration cannot rely on it. `post_mime_type` is the durable
 * signal: it keeps describing the UPLOAD (`image/png`) even after `_wp_attached_file` has
 * been repointed at the WebP, because the conversion never touches the attachment post.
 *
 * Resolution order:
 *   1. metadata['original_image'] when present — authoritative, set by core.
 *   2. Otherwise, if post_mime_type disagrees with the attached file's extension, look for
 *      a sibling with the extension post_mime_type implies. This is the recovery path for
 *      an attachment already flattened by a bad pass.
 *   3. The attached file, for attachments that were never converted.
 *
 * @param int $attachment_id Attachment ID.
 * @return string Absolute path, or '' when nothing readable exists.
 */
function pgds_original_upload_path( $attachment_id ) {
	$attached = (string) get_attached_file( $attachment_id );
	$meta     = wp_get_attachment_metadata( $attachment_id );

	// 1. Core's own record of the untouched upload.
	if ( ! empty( $meta['original_image'] ) ) {
		$candidate = (string) wp_get_original_image_path( $attachment_id );
		if ( $candidate && file_exists( $candidate ) ) {
			return $candidate;
		}
	}

	// 2. Recovery: post_mime_type still names the upload's real format.
	$mime = (string) get_post_mime_type( $attachment_id );
	if ( $attached && $mime ) {
		$ext = pathinfo( $attached, PATHINFO_EXTENSION );
		// wp_get_mime_types() maps 'jpg|jpeg|jpe' => 'image/jpeg', so a source JPEG has to
		// try each alternative rather than assume one spelling.
		$wanted = array();
		foreach ( wp_get_mime_types() as $pattern => $pattern_mime ) {
			if ( $pattern_mime === $mime ) {
				$wanted = explode( '|', $pattern );
				break;
			}
		}
		if ( $wanted && ! in_array( strtolower( $ext ), $wanted, true ) ) {
			$base = preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/i', '', $attached );
			foreach ( $wanted as $try ) {
				$candidate = $base . '.' . $try;
				if ( file_exists( $candidate ) ) {
					return $candidate;
				}
			}
		}
	}

	// 3. Never converted, or the original is genuinely gone.
	return $attached && file_exists( $attached ) ? $attached : '';
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
	/*
	 * Gutenberg is used for post body content (§1), so the theme has to support what it
	 * emits. Without responsive-embeds an embedded video keeps its hardcoded 560x315
	 * and overflows the 760px article measure on mobile.
	 */
	add_theme_support( 'responsive-embeds' );
	// Lets the editor's alignwide/alignfull match the article measure below.
	add_theme_support( 'align-wide' );
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
 * Resolve embed blocks whose oEmbed cache was never primed.
 *
 * The core embed block does NOT call the oEmbed provider at render time. It relies on a
 * per-post `_oembed_*` meta cache that is written when a post is saved through the
 * editor. Content created any other way never gets it, and the block then degrades to
 * printing the bare URL as text.
 *
 * Measured on a CLI-created post:
 *   oembed meta keys: 0
 *   do_blocks(embed block)      -> iframe: NO   (raw URL rendered instead)
 *   $wp_embed->autoembed(url)   -> iframe: YES
 *   $wp_embed->shortcode([],url) -> iframe: YES
 *
 * So the provider and the network are fine; only the cache is missing. This matters
 * well beyond one test post: §9 imports 2,000 posts through WP-CLI, and any of them
 * with a body embed would have shipped a naked URL in the middle of the article.
 *
 * Fixed at render time rather than at import time on purpose — it then also covers
 * posts migrated by any other route, and re-rendering is cheap because the whole page
 * is FastCGI-cached (§5.3) and $wp_embed keeps its own transient cache.
 *
 * @param string $html  Rendered block HTML.
 * @param array  $block Parsed block.
 * @return string
 */
function pgds_resolve_embed_block( $html, $block ) {
	if ( empty( $block['blockName'] ) || 'core/embed' !== $block['blockName'] ) {
		return $html;
	}
	// Already resolved (editor-saved post, or another filter got there first).
	if ( false !== strpos( $html, '<iframe' ) || false !== strpos( $html, '<blockquote' ) ) {
		return $html;
	}

	$url = $block['attrs']['url'] ?? '';
	if ( ! $url || ! wp_http_validate_url( $url ) ) {
		return $html;
	}

	global $wp_embed;
	if ( ! $wp_embed instanceof WP_Embed ) {
		return $html;
	}

	// shortcode() consults the provider and writes the transient cache, so subsequent
	// renders are local.
	$resolved = $wp_embed->shortcode( array(), $url );

	// A provider outage returns a link rather than an embed; keep the original markup
	// in that case so the block still carries its figure/caption structure.
	if ( ! $resolved || false === strpos( $resolved, '<iframe' ) ) {
		return $html;
	}

	/*
	 * Force youtube-nocookie.com. oEmbed returns www.youtube.com/embed/..., which sets
	 * tracking cookies on page load — exactly what the lazy facade avoids for the
	 * canonical video (§6.2, and CLAUDE.md: "loads the youtube-nocookie.com iframe").
	 * Leaving the body embed on the tracking domain would reintroduce, halfway down
	 * every article, the precise thing the facade exists to prevent.
	 *
	 * Also add loading="lazy": a body embed sits below the fold, and an eager iframe
	 * there competes with LCP on the article's own featured image.
	 */
	$resolved = str_replace(
		array( 'https://www.youtube.com/embed/', 'https://youtube.com/embed/' ),
		'https://www.youtube-nocookie.com/embed/',
		$resolved
	);
	if ( false === strpos( $resolved, 'loading=' ) ) {
		$resolved = str_replace( '<iframe ', '<iframe loading="lazy" ', $resolved );
	}

	// Swap only the wrapper's contents, preserving the block's classes and <figcaption>.
	return preg_replace(
		'#(<div class="wp-block-embed__wrapper">)(.*?)(</div>)#s',
		'$1' . $resolved . '$3',
		$html,
		1
	);
}
add_filter( 'render_block', 'pgds_resolve_embed_block', 10, 2 );

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
/**
 * Is this request a theme-registered custom endpoint rather than a real front-end route?
 *
 * Such a request looks identical to a soft-404 from the outside: its query matches no
 * archive and no singular, so WordPress falls back to the HOME query. The distinguishing
 * fact is that the theme deliberately registered a query var for it via the `query_vars`
 * filter and pointed a rewrite rule at it.
 *
 * Derived from the registered vars rather than a hardcoded list, so an endpoint added later
 * is covered without having to remember to edit pgds_block_private_cpt().
 *
 * @return bool
 */
function pgds_is_custom_endpoint() {
	// Vars core knows about anyway; anything the theme added arrives on top of these.
	$core_vars = ( new WP() )->public_query_vars;

	foreach ( (array) apply_filters( 'query_vars', array() ) as $var ) {
		if ( in_array( $var, $core_vars, true ) ) {
			continue;
		}
		if ( '' !== (string) get_query_var( $var ) ) {
			return true;
		}
	}

	return false;
}

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
		/*
		 * A custom endpoint is NOT a soft-404, even though it looks exactly like one here.
		 *
		 * /video-sitemap.xml (§7) is served by a rewrite rule to
		 * `index.php?pgds_video_sitemap=1`. That query matches no archive or singular, so
		 * WordPress falls back to the HOME query — is_home() is true and the path is not the
		 * home path, which is precisely the signal this guard 404s on. Both callbacks sit on
		 * template_redirect at priority 10 and this one is registered first, so it fired
		 * before pgds_render_video_sitemap() ever ran:
		 *
		 *   /video-sitemap.xml -> 404 (text/html)
		 *
		 * i.e. a guard added to fix one soft-404 silently broke a §7 deliverable, and the
		 * sitemap code itself was perfectly correct. Found by requesting the URL, not by
		 * reading either function.
		 *
		 * Checked against the registered query vars rather than a hardcoded list, so any
		 * future endpoint added via query_vars + a rewrite rule is covered automatically.
		 */
		if ( pgds_is_custom_endpoint() ) {
			return;
		}

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
