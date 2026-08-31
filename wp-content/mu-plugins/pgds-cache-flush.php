<?php
/**
 * Plugin Name: PGDS Cache Flush
 * Description: Flush sach Nginx FastCGI page cache khi noi dung doi (proposal §5.4).
 *              Uu tien "sua la thay ngay" cho khach anonymous.
 * Version: 1.0.0
 *
 * Dieu kien: php-fpm phai GHI duoc thu muc cache (nginx + php-fpm cung group,
 * thu muc group-writable). Xem infra/nginx/README.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thu muc cache FastCGI. Cho phep override qua constant trong wp-config.php.
 */
if ( ! defined( 'PGDS_FCGI_CACHE_DIR' ) ) {
	define( 'PGDS_FCGI_CACHE_DIR', '/var/cache/nginx/fcgi' );
}

/**
 * Xoa toan bo file trong thu muc cache (flush sach, khong mapping path).
 * O tai hien tai, xoa het la dung: 1 lenh, khong sot, khong can logic mapping.
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
		// Im lang: cache flush that bai khong duoc lam vo request.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[pgds] cache flush error: ' . $e->getMessage() );
		}
	}
}

// Chi hook cac su kien noi dung that su doi (khong autosave/revision).
add_action( 'save_post', 'pgds_flush_page_cache', 10, 0 );
add_action( 'deleted_post', 'pgds_flush_page_cache' );
add_action( 'edited_term', 'pgds_flush_page_cache' );
add_action( 'wp_update_nav_menu', 'pgds_flush_page_cache' );
add_action( 'switch_theme', 'pgds_flush_page_cache' );
add_action( 'customize_save_after', 'pgds_flush_page_cache' );
