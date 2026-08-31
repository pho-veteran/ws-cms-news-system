<?php
/**
 * Cac dong hardening bo sung vao wp-config.php THAT (proposal §9.3, §5).
 * KHONG commit wp-config.php that (da ignore). Day chi la mau tham khao.
 *
 * @package pgds
 */

// --- Behind proxy (CloudFront/Cloudflare): nhan dung HTTPS ---
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	$_SERVER['HTTPS'] = 'on';
}

// --- Bao mat ---
define( 'DISALLOW_FILE_EDIT', true );   // tat editor code trong admin
define( 'DISALLOW_FILE_MODS', true );   // tat cai/update qua UI tren prod (deploy qua CI)
define( 'FORCE_SSL_ADMIN', true );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'AUTOMATIC_UPDATER_DISABLED', false );

// --- Revision: gioi han 3 ban (proposal §7.1 sizing) ---
define( 'WP_POST_REVISIONS', 3 );
define( 'EMPTY_TRASH_DAYS', 7 );
define( 'AUTOSAVE_INTERVAL', 120 );

// --- WP-Cron: tat cron tren request, chay bang system cron ---
define( 'DISABLE_WP_CRON', true );
// crontab: */5 * * * * cd /var/www/pgds && wp cron event run --due-now >/dev/null 2>&1

// --- Redis object cache (plugin Redis Object Cache doc cac hang so nay) ---
define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
define( 'WP_REDIS_MAXTTL', 43200 );
define( 'WP_CACHE_KEY_SALT', 'pgds:' );

// --- Thu muc FastCGI cache cho mu-plugin flush ---
define( 'PGDS_FCGI_CACHE_DIR', '/var/cache/nginx/fcgi' );

// --- Neu KHONG cai plugin SEO: bat theme tu xuat NewsArticle schema ---
// define( 'PGDS_EMIT_ARTICLE_SCHEMA', true );

// --- Memory ---
define( 'WP_MEMORY_LIMIT', '256M' );

/* Nho: dat cac SALT keys that tu https://api.wordpress.org/secret-key/1.1/salt/ */
