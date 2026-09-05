<?php
/**
 * Plugin Name: PGDS Lunar Loader
 * Description: Loads the pgds-lunar-calendar plugin without a database activation.
 *
 * WHY THIS EXISTS
 * ---------------
 * The lunar calendar shipped as a regular plugin, which means it only runs once
 * `active_plugins` lists it. Nothing in the deployment pipeline ever wrote that option,
 * so production served the theme's fallback path instead: the sidebar read the manual
 * `pgds_lunar_note` CPT, and https://vihn.id.vn/wp-json/pgds-lunar/v1/today answered 404
 * while the same route worked locally. The gap was invisible because the homepage still
 * returned 200.
 *
 * Activating it from CI would need `wp plugin activate` on the server. The deployment
 * account instead has one fixed-purpose sudo grant for validated release promotion — see
 * .github/workflows/README.md. Widening it to run WP-CLI as www-data just to write one
 * option buys a larger privilege surface than the problem is worth.
 *
 * mu-plugins load unconditionally on every request, with no option to set and no
 * privilege beyond the file copy the deploy already performs. So the plugin ships as
 * files and this loader boots it.
 *
 * The plugin remains a normal plugin on disk: it still works when activated by hand in
 * wp-admin, and the guard below keeps that case from loading it twice.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boot the lunar calendar plugin from mu-plugins.
 *
 * Runs at `muplugins_loaded` rather than at file scope because `is_plugin_active()` lives
 * in wp-admin/includes/plugin.php, which is not loaded this early on front-end requests;
 * the `active_plugins` option is read directly instead, which needs no include.
 */
function pgds_lunar_boot_from_mu_plugin(): void {
	// Already loaded as a normal active plugin — WordPress will require the same file, so
	// requiring it here too would redeclare every class and fatal the request.
	$active = (array) get_option( 'active_plugins', array() );
	if ( in_array( 'pgds-lunar-calendar/pgds-lunar-calendar.php', $active, true ) ) {
		return;
	}

	// A network activation on multisite stores plugins in a separate option.
	if ( is_multisite() ) {
		$network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
		if ( isset( $network_active['pgds-lunar-calendar/pgds-lunar-calendar.php'] ) ) {
			return;
		}
	}

	// Defends against a partially-synced deploy: a missing plugin directory must degrade
	// to the theme's CPT fallback, not fatal the whole site from an mu-plugin.
	$bootstrap = WP_PLUGIN_DIR . '/pgds-lunar-calendar/pgds-lunar-calendar.php';
	if ( ! is_readable( $bootstrap ) ) {
		return;
	}

	require_once $bootstrap;
}

/*
 * Priority 0 so the plugin's own `plugins_loaded` and `rest_api_init` hooks are registered
 * before either action fires. `muplugins_loaded` runs before normal plugins load, which is
 * early enough for both.
 */
add_action( 'muplugins_loaded', 'pgds_lunar_boot_from_mu_plugin', 0 );
