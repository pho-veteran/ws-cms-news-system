<?php
/**
 * pgds theme bootstrap.
 *
 * This file ONLY requires modules in inc/. Do not write logic directly here.
 * See PROPOSAL_01 §3.3 for the directory structure.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PGDS_VERSION', '1.0.0' );
define( 'PGDS_DIR', get_template_directory() );
define( 'PGDS_URI', get_template_directory_uri() );
define( 'PGDS_LOGO_URI', PGDS_URI . '/assets/images/pgds-logo.png' );

/**
 * Load modules in dependency order.
 */
$pgds_modules = array(
	'inc/setup.php',         // theme support, nav menu, image size
	'inc/icons.php',         // inline SVG icon set (loaded early: templates and nav use it)
	'inc/nav-walker.php',    // nav walker + fallback (7 items, dropdown a11y)
	'inc/enqueue.php',       // asset + hash versioning (reads manifest.json)
	'inc/cpt-tax.php',       // CPT + taxonomy
	'inc/editorial-surfaces.php', // four admin workflows over the shared post model
	'inc/meta-fields.php',   // custom field + meta box (no ACF)
	'inc/template-tags.php', // display helpers
	'inc/comments.php',      // reader-comment approval and presentation
	'inc/query-blocks.php',  // query for the 11 front-page blocks + dedup
	'inc/cron.php',          // scheduled jobs (daily YouTube metadata sync, §6.4)
	'inc/seo-schema.php',    // VideoObject + NewsMediaOrganization + video sitemap
	'inc/admin-ux.php',      // admin columns, editorial UX
	'inc/customizer.php',    // editorial/legal footer info (§13 go-live gate)
	'inc/cli-import.php',    // WP-CLI import (only loaded when WP_CLI)
);

foreach ( $pgds_modules as $pgds_module ) {
	$pgds_path = PGDS_DIR . '/' . $pgds_module;
	if ( is_readable( $pgds_path ) ) {
		require_once $pgds_path;
	}
}
unset( $pgds_modules, $pgds_module, $pgds_path );
