<?php
/**
 * Additional hardening lines for the actual wp-config.php (proposal §9.3, §5).
 * DO NOT commit the actual wp-config.php (it is ignored). This is only a reference sample.
 *
 * @package pgds
 */

// --- Behind a proxy (CloudFront/Cloudflare): detect HTTPS correctly ---
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	$_SERVER['HTTPS'] = 'on';
}

// --- Security ---
define( 'DISALLOW_FILE_EDIT', true );   // disable the code editor in admin
define( 'DISALLOW_FILE_MODS', true );   // disable UI installs/updates in production (deploy through CI)
define( 'FORCE_SSL_ADMIN', true );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'AUTOMATIC_UPDATER_DISABLED', false );

// --- Revisions: limit to 3 versions (proposal §7.1 sizing) ---
define( 'WP_POST_REVISIONS', 3 );
define( 'EMPTY_TRASH_DAYS', 7 );
define( 'AUTOSAVE_INTERVAL', 120 );

// --- WP-Cron: disable cron on requests; run it through system cron ---
define( 'DISABLE_WP_CRON', true );
// crontab: */5 * * * * cd /var/www/pgds && wp cron event run --due-now >/dev/null 2>&1

// --- Redis object cache (the Redis Object Cache plugin reads these constants) ---
define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
define( 'WP_REDIS_MAXTTL', 43200 );
define( 'WP_CACHE_KEY_SALT', 'pgds:' );

// --- FastCGI cache directory for the mu-plugin flush ---
define( 'PGDS_FCGI_CACHE_DIR', '/var/cache/nginx/fcgi' );

// --- If NO SEO plugin is installed: enable the theme to emit NewsArticle schema ---
// define( 'PGDS_EMIT_ARTICLE_SCHEMA', true );

// --- Memory ---
define( 'WP_MEMORY_LIMIT', '256M' );

/* Remember: use real SALT keys from https://api.wordpress.org/secret-key/1.1/salt/ */
