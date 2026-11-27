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

		$english_id = $create_post( 'Vietnam Buddhism with a secondary category', 'vietnam-buddhism' );
		$legacy_id  = $create_post( 'Legacy Tin Phat su without a primary category', 'tin-phat-su' );
		$assert( ! is_wp_error( $english_id ) && ! is_wp_error( $legacy_id ), 'archive fixtures are created' );
		if ( ! is_wp_error( $english_id ) && ! is_wp_error( $legacy_id ) ) {
			wp_set_post_categories( $english_id, array( $canonical['vietnam-buddhism'], $canonical['tin-phat-su'] ) );
			delete_post_meta( $legacy_id, '_pgds_primary_cat' );

			$archive = static function ( $slug ) use ( $article_id, $english_id, $legacy_id, $video_id ) {
				global $wp_the_query;
				$previous_query = $wp_the_query;
				$wp_the_query   = new WP_Query();
				try {
					$wp_the_query->query( array(
						'category_name' => $slug,
						'fields'        => 'ids',
						'post__in'      => array( $article_id, $english_id, $legacy_id, $video_id ),
						'post_status'   => 'publish',
						'orderby'       => 'ID',
						'order'         => 'ASC',
					) );
					return $wp_the_query;
				} finally {
					$wp_the_query = $previous_query;
				}
			};
			$news_archive = $archive( 'tin-phat-su' );
			$assert( array( $article_id, $legacy_id ) === $news_archive->posts && 2 === (int) $news_archive->found_posts, 'Tin Phat su excludes off-branch primary articles before counting pages and keeps legacy articles' );
			$assert( array( $english_id ) === $archive( 'vietnam-buddhism' )->posts, 'Vietnam Buddhism keeps the article in its primary category' );
			$assert( array( $video_id ) === $archive( 'media' )->posts, 'parent archives include primary categories in their child branches' );
			$assert( has_category( $canonical['tin-phat-su'], $english_id ), 'the archive filter preserves saved secondary category assignments' );
		}

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
	$purge_calls = 0;
	add_filter(
		'pgds_page_cache_directory',
		static function () use ( $cache_dir, &$purge_calls ) {
			++$purge_calls;
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
		do_action( 'shutdown' );
		$assert( is_file( $cache_marker ), 'draft-to-draft does not purge the public page cache' );
		wp_delete_post( $draft_id, true );
		do_action( 'shutdown' );
		$assert( is_file( $cache_marker ), 'deleting an unpublished draft keeps the public page cache' );

		$reader_ids = array();
		for ( $index = 0; $index <= pgds_comments_per_page(); ++$index ) {
			$reader_ids[] = wp_insert_comment( array(
				'comment_post_ID'  => $article_id,
				'comment_parent'   => $reader_ids[0] ?? 0,
				'comment_content'  => 'Reader fixture ' . $index,
				'comment_date_gmt' => '2026-01-01 00:00:00',
				'comment_approved' => 1,
			) );
		}
		wp_insert_comment( array(
			'comment_post_ID'  => $article_id,
			'comment_type'     => 'pingback',
			'comment_content'  => 'Hidden pingback',
			'comment_approved' => 1,
		) );
		$sorted_ids = array_map( 'intval', wp_list_pluck( pgds_reader_comments( $article_id ), 'comment_ID' ) );
		$assert( array_reverse( $reader_ids ) === $sorted_ids, 'reader pages order same-time comments and replies by ID and exclude pingbacks' );
		$first = end( $reader_ids );
		$oldest = $reader_ids[0];
		$assert( get_comment_link( $first, array( 'cpage' => 0 ) ) === get_comment_link( $first ), 'the newest comment permalink points to page one' );
		$assert( get_comment_link( $oldest, array( 'cpage' => 2 ) ) === get_comment_link( $oldest ), 'the oldest parent permalink points to page two' );
		do_action( 'shutdown' );
		$assert( is_file( $cache_marker ), 'public comment submissions keep the site-wide cache' );

		foreach ( array( 'spam', 'approve', 'trash', 'approve' ) as $status ) {
			file_put_contents( $cache_marker, 'comment moderation' );
			wp_set_comment_status( $first, $status );
			do_action( 'shutdown' );
			$assert( ! is_file( $cache_marker ), 'reader comment moderation clears the public cache after ' . $status );
		}
		file_put_contents( $cache_marker, 'old comment text' );
		wp_update_comment( array( 'comment_ID' => $first, 'comment_content' => 'Updated reader comment' ) );
		do_action( 'shutdown' );
		$assert( ! is_file( $cache_marker ), 'editing a published reader comment clears the public cache' );
		file_put_contents( $cache_marker, 'deleted comment' );
		wp_delete_comment( $first, true );
		do_action( 'shutdown' );
		$assert( ! is_file( $cache_marker ), 'permanently deleting a published reader comment clears the public cache' );

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
			$assert( is_file( $cache_marker ), 'the transition leaves the cache in place until shutdown' );
			do_action( 'shutdown' );
			$assert( ! is_file( $cache_marker ), $transition[2] );
		}

		$previous_user = get_current_user_id();
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
		wp_set_current_user( (int) reset( $admins ) );
		$purge_calls = 0;
		file_put_contents( $cache_marker, 'previous summary' );
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $article_id );
		$request->set_body_params( array( 'meta' => array( '_pgds_sapo' => 'Updated summary' ) ) );
		$response = rest_do_request( $request );
		wp_set_current_user( $previous_user );
		$assert( 200 === $response->get_status() && 'Updated summary' === get_post_meta( $article_id, '_pgds_sapo', true ), 'REST saves the public article metadata' );
		$assert( is_file( $cache_marker ) && 0 === $purge_calls, 'REST completes metadata writes before clearing the cache' );
		pgds_flush_page_cache_on_transition( 'publish', 'publish', get_post( $article_id ) );
		do_action( 'shutdown' );
		$assert( ! is_file( $cache_marker ) && 1 === $purge_calls, 'repeated invalidations purge only once at shutdown' );
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
