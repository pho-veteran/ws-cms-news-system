<?php
/**
 * Plugin Name: PGDS Cache Flush
 * Description: Cleanly flush the Nginx FastCGI page cache when content changes (proposal §5.4).
 *              Prioritize "edits appear immediately" for anonymous visitors.
 * Version: 1.0.0
 *
 * Requirement: php-fpm must be able to WRITE to the cache directory (nginx and php-fpm
 * share a group; the directory is group-writable). See infra/nginx/README.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FastCGI cache directory. Can be overridden with a constant in wp-config.php.
 */
if ( ! defined( 'PGDS_FCGI_CACHE_DIR' ) ) {
	define( 'PGDS_FCGI_CACHE_DIR', '/var/cache/nginx/fcgi' );
}

/**
 * Delete every file in the cache directory (clean flush, without path mapping).
 * For the current scale, deleting everything is appropriate: one command, no leftovers, no mapping logic needed.
 */
function pgds_flush_page_cache() {
	$dir = PGDS_FCGI_CACHE_DIR;
	if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
		return;
	}
	try {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $f ) {
			if ( $f->isDir() ) {
				@rmdir( $f->getPathname() );
			} else {
				@unlink( $f->getPathname() );
			}
		}
	} catch ( Exception $e ) {
		// Remain silent: a failed cache flush must not break the request.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[pgds] cache flush error: ' . $e->getMessage() );
		}
	}
}

/**
 * save_post gate: flush only when a reader-visible post actually changed.
 *
 * §5.4 requires "Hook only save_post, not autosaves/revisions", and the comment below
 * used to claim exactly that — but the callback was attached with zero accepted arguments
 * and performed no checks, so it could not distinguish anything. Every autosave (the block
 * editor fires one roughly every 10 seconds while an editor types) and every revision
 * write triggered a full recursive delete of the FastCGI cache.
 *
 * The consequence is not stale content, it is the opposite: one editor with an open draft
 * repeatedly empties the page cache for the whole site, so anonymous visitors get
 * uncached responses from a 2 GB origin. That is the exact load §5 exists to prevent.
 *
 * Three gates, each covering a case observed in the editor's request flow:
 *   1. DOING_AUTOSAVE / wp_is_post_autosave() — the periodic editor autosave.
 *   2. wp_is_post_revision() — the revision row saved alongside the real post.
 *   3. auto-draft / inherit status — a post that no reader can see yet, so no cached page
 *      can be describing it.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function pgds_flush_page_cache_on_save( $post_id, $post = null ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	$post = $post instanceof WP_Post ? $post : get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	// 'inherit' is a revision/attachment-child status; 'auto-draft' is a post the editor
	// has not written yet. Neither is reachable by a reader, so no cache entry is affected.
	if ( in_array( $post->post_status, array( 'auto-draft', 'inherit' ), true ) ) {
		return;
	}
	// Non-public types (pgds_lunar_note) never render a cacheable page of their own, but
	// they DO appear in the front-page sidebar, so they still need the flush. Only skip
	// types WordPress hides entirely from the front end.
	$type = get_post_type_object( $post->post_type );
	if ( $type && ! empty( $type->exclude_from_search ) && empty( $type->public ) && 'pgds_lunar_note' !== $post->post_type ) {
		return;
	}

	pgds_flush_page_cache();
}

// Hook only events where content actually changes (not autosaves/revisions).
add_action( 'save_post', 'pgds_flush_page_cache_on_save', 10, 2 );
add_action( 'deleted_post', 'pgds_flush_page_cache' );
add_action( 'edited_term', 'pgds_flush_page_cache' );
add_action( 'wp_update_nav_menu', 'pgds_flush_page_cache' );
add_action( 'switch_theme', 'pgds_flush_page_cache' );
add_action( 'customize_save_after', 'pgds_flush_page_cache' );
