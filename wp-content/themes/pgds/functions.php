<?php
/**
 * pgds theme bootstrap.
 *
 * File nay CHI require cac module trong inc/. Khong viet logic truc tiep o day.
 * Xem PROPOSAL_01 §3.3 cho cau truc thu muc.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PGDS_VERSION', '1.0.0' );
define( 'PGDS_DIR', get_template_directory() );
define( 'PGDS_URI', get_template_directory_uri() );

/**
 * Nap cac module theo thu tu phu thuoc.
 */
$pgds_modules = array(
	'inc/setup.php',         // theme support, nav menu, image size
	'inc/nav-walker.php',    // nav walker + fallback (7 muc, dropdown a11y)
	'inc/enqueue.php',       // asset + hash versioning (doc manifest.json)
	'inc/cpt-tax.php',       // CPT + taxonomy
	'inc/meta-fields.php',   // custom field + meta box (khong ACF)
	'inc/template-tags.php', // helper hien thi
	'inc/query-blocks.php',  // query 11 block trang chu + dedup
	'inc/seo-schema.php',    // VideoObject + NewsMediaOrganization + video sitemap
	'inc/admin-ux.php',      // cot admin, UX bien tap
	'inc/cli-import.php',    // WP-CLI import (chi nap khi WP_CLI)
);

foreach ( $pgds_modules as $pgds_module ) {
	$pgds_path = PGDS_DIR . '/' . $pgds_module;
	if ( is_readable( $pgds_path ) ) {
		require_once $pgds_path;
	}
}
unset( $pgds_modules, $pgds_module, $pgds_path );
