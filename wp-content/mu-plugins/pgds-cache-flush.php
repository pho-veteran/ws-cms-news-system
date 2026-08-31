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

// Hook only events where content actually changes (not autosaves/revisions).
add_action( 'save_post', 'pgds_flush_page_cache', 10, 0 );
add_action( 'deleted_post', 'pgds_flush_page_cache' );
add_action( 'edited_term', 'pgds_flush_page_cache' );
add_action( 'wp_update_nav_menu', 'pgds_flush_page_cache' );
add_action( 'switch_theme', 'pgds_flush_page_cache' );
add_action( 'customize_save_after', 'pgds_flush_page_cache' );
