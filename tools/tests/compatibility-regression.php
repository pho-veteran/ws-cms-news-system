<?php
/**
 * Runtime regression checks for EPIC #3 category and compatibility contracts.
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$failures = array();
$posts    = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( $condition ) {
		WP_CLI::log( 'PASS: ' . $message );
		return;
	}
	$failures[] = $message;
	WP_CLI::warning( 'FAIL: ' . $message );
};

$canonical = pgds_seed_categories();
if ( is_wp_error( $canonical ) ) {
	WP_CLI::error( $canonical );
}

WP_CLI::log( '==> Checking canonical category route wrappers...' );
foreach ( pgds_category_slugs() as $slug ) {
	$path   = get_theme_file_path( '/category-' . $slug . '.php' );
	$source = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	$assert( is_readable( $path ), sprintf( '%s has a dedicated category wrapper', $slug ) );
	$assert( false !== strpos( $source, "get_theme_file_path( '/category.php' )" ), sprintf( '%s delegates to the shared category renderer', $slug ) );
}

$assert( pgds_category_slugs() === array_keys( pgds_category_definitions() ), 'canonical category order has one source of truth' );
foreach ( array_keys( pgds_legacy_category_map() ) as $legacy_slug ) {
	$assert( ! in_array( $legacy_slug, pgds_category_slugs(), true ), sprintf( 'retired slug %s is excluded from the runtime vocabulary', $legacy_slug ) );
}

WP_CLI::log( '==> Checking the shared post model and video indexability...' );
foreach ( array( 'article', 'emagazine', 'video', 'vietnam-buddhism' ) as $surface ) {
	$assert( ! post_type_exists( $surface ), sprintf( '%s does not create a parallel post type', $surface ) );
}

$create_post = static function ( $title, $primary_slug, $youtube_id = '', $status = 'publish', $unavailable = false ) use ( &$posts, $canonical ) {
	$post_id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => $status,
			'post_title'    => $title,
			'post_content'  => '<p>Compatibility regression fixture.</p>',
			'post_category' => array( (int) $canonical[ $primary_slug ] ),
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	$posts[] = (int) $post_id;
	update_post_meta( $post_id, '_pgds_primary_cat', (int) $canonical[ $primary_slug ] );
	update_post_meta( $post_id, '_pgds_source_id', 'epic-3-compat-' . $post_id );
	if ( '' !== $youtube_id ) {
		update_post_meta( $post_id, '_pgds_youtube_id', $youtube_id );
	}
	if ( $unavailable ) {
		update_post_meta( $post_id, '_pgds_video_unavailable', '1' );
	}
	return (int) $post_id;
};

$article_id     = $create_post( 'Article with incidental video metadata', 'tin-phat-su', 'dQw4w9WgXcQ' );
$video_id       = $create_post( 'Indexable video', 'video', 'dQw4w9WgXcQ' );
$unavailable_id = $create_post( 'Unavailable video', 'video', 'dQw4w9WgXcQ', 'publish', true );
$invalid_id     = $create_post( 'Invalid video ID', 'video', 'invalid' );
$draft_id       = $create_post( 'Draft video', 'video', 'dQw4w9WgXcQ', 'draft' );

try {
	foreach ( array( $article_id, $video_id, $unavailable_id, $invalid_id, $draft_id ) as $post_id ) {
		$assert( ! is_wp_error( $post_id ), 'compatibility fixture was created' );
	}
	if ( ! array_filter( array( $article_id, $video_id, $unavailable_id, $invalid_id, $draft_id ), 'is_wp_error' ) ) {
		$assert( ! pgds_is_video_indexable( $article_id ), 'Article metadata alone cannot emit VideoObject or enter the video sitemap' );
		$assert( pgds_is_video_indexable( $video_id ), 'valid published Video is indexable' );
		$assert( ! pgds_is_video_indexable( $unavailable_id ), 'unavailable Video is not indexable' );
		$assert( ! pgds_is_video_indexable( $invalid_id ), 'invalid YouTube ID is not indexable' );
		$assert( ! pgds_is_video_indexable( $draft_id ), 'draft Video is not indexable' );
		$index_query = new WP_Query( pgds_video_index_query_args( -1, true ) );
		$index_ids   = array_map( 'intval', $index_query->posts );
		$assert( in_array( $video_id, $index_ids, true ), 'video index query includes the valid Video' );
		$assert( ! in_array( $article_id, $index_ids, true ), 'video index query excludes an Article with incidental metadata' );
		$assert( ! in_array( $unavailable_id, $index_ids, true ), 'video index query excludes an unavailable Video' );
		$assert( ! in_array( $invalid_id, $index_ids, true ), 'video index query excludes an invalid YouTube ID' );

		global $post, $wp_query;
		$previous_post  = $post;
		$previous_query = $wp_query;
		$wp_query       = new WP_Query( array( 'p' => $video_id, 'post_type' => 'post' ) );
		$post           = get_post( $video_id );
		setup_postdata( $post );
		ob_start();
		pgds_schema_video();
		$video_schema = (string) ob_get_clean();
		$assert( false !== strpos( $video_schema, '"@type":"VideoObject"' ), 'valid Video emits VideoObject' );
		$assert( 1 === preg_match( '#youtube-nocookie\.com(?:\\\\/|/)embed(?:\\\\/|/)dQw4w9WgXcQ#', $video_schema ), 'VideoObject uses the privacy-enhanced embed host' );

		$wp_query = new WP_Query( array( 'p' => $article_id, 'post_type' => 'post' ) );
		$post     = get_post( $article_id );
		setup_postdata( $post );
		ob_start();
		pgds_schema_video();
		$assert( '' === trim( (string) ob_get_clean() ), 'Article with incidental YouTube metadata emits no VideoObject' );

		$wp_query = $previous_query;
		$post     = $previous_post;
		wp_reset_postdata();
	}

	WP_CLI::log( '==> Checking schema ownership, importer identity, cron and cache hooks...' );
	if ( ! defined( 'WPSEO_VERSION' ) ) {
		define( 'WPSEO_VERSION', 'compatibility-test' );
	}
	$assert( pgds_seo_plugin_owns_schema(), 'SEO plugin ownership suppresses the theme Article schema fallback' );
	ob_start();
	pgds_schema_article();
	$assert( '' === trim( (string) ob_get_clean() ), 'theme emits no duplicate NewsArticle when an SEO plugin owns schema' );

	$command  = new PGDS_CLI_Command();
	$validate = new ReflectionMethod( PGDS_CLI_Command::class, 'validate_record' );
	$validate->setAccessible( true );
	$record = array(
		'source_id'   => 'compatibility-record',
		'title'       => 'Compatibility record',
		'primary_cat' => 'not-allowed',
		'cats'        => array(),
	);
	$assert( false !== strpos( (string) $validate->invoke( $command, $record ), 'unknown primary_cat' ), 'importer rejects a category outside the canonical allowlist' );
	$find = new ReflectionMethod( PGDS_CLI_Command::class, 'find_by_source_id' );
	$find->setAccessible( true );
	$assert( (int) $article_id === (int) $find->invoke( $command, 'epic-3-compat-' . $article_id ), 'import identity resolves the existing post instead of creating a duplicate' );

	pgds_schedule_jobs();
	$assert( false !== wp_next_scheduled( PGDS_YT_SYNC_HOOK ), 'YouTube metadata cron remains scheduled' );
	$assert( false !== has_action( PGDS_YT_SYNC_HOOK, 'pgds_run_yt_sync' ), 'YouTube cron uses the shared sync callback' );
	$assert( false !== has_action( 'transition_post_status', 'pgds_flush_page_cache_on_transition' ), 'page cache purge remains attached to transition_post_status' );
	$assert( false === has_action( 'save_post', 'pgds_flush_page_cache_on_transition' ), 'page cache purge is not attached to save_post' );

	$cache_dir = trailingslashit( sys_get_temp_dir() ) . 'pgds-epic-3-cache-' . wp_generate_uuid4();
	add_filter(
		'pgds_page_cache_directory',
		static function () use ( $cache_dir ) {
			return $cache_dir;
		}
	);
	if ( is_dir( $cache_dir ) || wp_mkdir_p( $cache_dir ) ) {
		$cache_marker = trailingslashit( $cache_dir ) . 'epic-3-compatibility.cache';
		file_put_contents( $cache_marker, 'direct' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		pgds_flush_page_cache();
		$assert( ! is_file( $cache_marker ), 'cache flusher clears the isolated dedicated directory' );

		file_put_contents( $cache_marker, 'draft' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		pgds_flush_page_cache_on_transition( 'draft', 'draft', get_post( $draft_id ) );
		$assert( is_file( $cache_marker ), 'draft-to-draft does not purge the public page cache' );

		foreach (
			array(
				array( 'publish', 'draft', 'publish purges the public page cache' ),
				array( 'draft', 'publish', 'unpublish purges the public page cache' ),
				array( 'trash', 'publish', 'trash purges the public page cache' ),
				array( 'publish', 'trash', 'untrash-to-publish purges the public page cache' ),
			) as $transition
		) {
			file_put_contents( $cache_marker, 'public' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			pgds_flush_page_cache_on_transition( $transition[0], $transition[1], get_post( $video_id ) );
			$assert( ! is_file( $cache_marker ), $transition[2] );
		}
	} else {
		$assert( false, 'temporary FastCGI cache directory is writable for transition tests' );
	}
	@rmdir( $cache_dir );
} finally {
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

if ( $failures ) {
	WP_CLI::error( sprintf( 'EPIC #3 compatibility regression failed with %d error(s).', count( $failures ) ) );
}

WP_CLI::success( 'EPIC #3 runtime compatibility contracts passed.' );
