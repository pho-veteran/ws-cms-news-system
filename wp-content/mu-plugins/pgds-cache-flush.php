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
	/*
	 * Resolve and sanity-check the target before deleting anything recursively.
	 *
	 * PGDS_FCGI_CACHE_DIR is overridable from wp-config.php, and the only previous guards
	 * were is_dir() and is_writable(). A typo — or a constant defined in the wrong order —
	 * pointing at, say, /var/www/pgds/wp-content means the next publish silently deletes the
	 * site: every filesystem error here is suppressed and the catch only logs under WP_DEBUG,
	 * so there would be no signal at all. These checks cost one realpath() per flush.
	 */
	$dir = realpath( PGDS_FCGI_CACHE_DIR );
	if ( ! $dir || ! is_dir( $dir ) || ! is_writable( $dir ) ) {
		return;
	}
	// Refuse a filesystem root or a near-root path, and refuse anything that looks like a
	// WordPress install rather than a dedicated cache directory.
	if ( substr_count( $dir, DIRECTORY_SEPARATOR ) < 2
		|| $dir === realpath( ABSPATH )
		|| $dir === realpath( WP_CONTENT_DIR )
		|| file_exists( $dir . '/wp-config.php' )
		|| file_exists( $dir . '/wp-load.php' )
	) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[pgds] refusing to flush: ' . $dir . ' does not look like a dedicated FastCGI cache directory.' );
		}
		return;
	}
	try {
		/*
		 * Symlinks are NOT followed, so the delete cannot escape the cache directory.
		 * Verified rather than assumed: with `/var/cache/nginx/fcgi/evil-link ->
		 * /tmp/precious` in place, a flush removed the real cache entry and left
		 * /tmp/precious/important.txt untouched. RecursiveDirectoryIterator does not
		 * descend into symlinked directories unless FOLLOW_SYMLINKS is passed, and it is
		 * deliberately not passed here.
		 *
		 * A symlink is unlinked rather than rmdir'd. isDir() is TRUE for a link to a
		 * directory and rmdir() then fails, which left the link in the cache directory
		 * forever while the flush still reported success — so the directory was never
		 * actually emptied. nginx does not create symlinks in its cache, so this is
		 * defensive, but a flush that silently leaves entries behind is the wrong thing to
		 * build the "edits appear immediately" guarantee (§5.1) on.
		 */
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $f ) {
			$path = $f->getPathname();
			if ( is_link( $path ) ) {
				@unlink( $path );
			} elseif ( $f->isDir() ) {
				@rmdir( $path );
			} else {
				@unlink( $path );
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
 * Flush only when a post that IS or WAS publicly visible changed.
 *
 * §5.4 requires "Hook only save_post, not autosaves/revisions". The original callback was
 * attached to `save_post` with zero accepted arguments and performed no checks, so it could
 * not distinguish anything despite a comment claiming it did: every autosave (the block
 * editor fires one roughly every 10 seconds while an editor types) and every revision write
 * recursively deleted the whole FastCGI cache.
 *
 * The consequence is not stale content, it is the opposite — one editor with an open draft
 * repeatedly empties the page cache for the entire site, so anonymous visitors are served
 * from PHP by a 2 GB origin. That is the exact load §5 exists to prevent, and it is
 * reachable by a Contributor, the lowest role holding `edit_posts`, simply by holding Save
 * Draft or looping `POST /wp-json/wp/v2/posts` with `status=draft`.
 *
 * Hooked on `transition_post_status` rather than `save_post` because the correct signal
 * needs BOTH statuses. A status list checked on `save_post` alone gets unpublishing wrong:
 * publish -> draft arrives with the new status `draft`, which any "skip drafts" rule
 * discards — leaving the unpublished article live in the cache, which is the worst possible
 * direction for a news site to fail in. `transition_post_status` fires for every save,
 * including publish -> publish, so it is a strict superset of what `save_post` gave us.
 *
 * @param string  $new_status Status after the save.
 * @param string  $old_status Status before the save.
 * @param WP_Post $post       Post object.
 * @return void
 */
function pgds_flush_page_cache_on_transition( $new_status, $old_status, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
		return;
	}

	/*
	 * Nothing cached can reference a post that was never public and still is not. This
	 * covers draft/pending/future/auto-draft edits (no flush) while still flushing both
	 * directions of a visibility change: publishing AND unpublishing or trashing.
	 */
	if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
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

// Content changes only: transition_post_status sees both statuses, so autosaves, revisions
// and purely-private edits are excluded while publish AND unpublish both flush.
add_action( 'transition_post_status', 'pgds_flush_page_cache_on_transition', 10, 3 );
add_action( 'deleted_post', 'pgds_flush_page_cache' );
add_action( 'edited_term', 'pgds_flush_page_cache' );
add_action( 'wp_update_nav_menu', 'pgds_flush_page_cache' );
add_action( 'switch_theme', 'pgds_flush_page_cache' );
add_action( 'customize_save_after', 'pgds_flush_page_cache' );
