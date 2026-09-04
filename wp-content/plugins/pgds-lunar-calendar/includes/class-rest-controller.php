<?php
/**
 * REST API controller for the lunar calendar.
 *
 * @package pgds-lunar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PGDS_Lunar_REST {

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_rest_route(
			'pgds-lunar/v1',
			'/today',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Handle the REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle(): WP_REST_Response {
		return new WP_REST_Response( pgds_lunar_get_today(), 200 );
	}
}
