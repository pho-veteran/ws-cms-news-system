<?php
/**
 * Asset enqueue.
 *
 * Doc assets/dist/manifest.json de lay filename co content hash
 * (build.mjs sinh ra). Filename doi khi noi dung doi -> ket hop
 * `Cache-Control: immutable` an toan (proposal §5.5).
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Doc manifest build (cache trong 1 request).
 *
 * @return array<string,string>
 */
function pgds_manifest() {
	static $manifest = null;
	if ( null !== $manifest ) {
		return $manifest;
	}
	$path = PGDS_DIR . '/assets/dist/manifest.json';
	if ( is_readable( $path ) ) {
		$decoded  = json_decode( (string) file_get_contents( $path ), true );
		$manifest = is_array( $decoded ) ? $decoded : array();
	} else {
		$manifest = array();
	}
	return $manifest;
}

/**
 * URL cua 1 asset da build theo logical name (vd 'main.css').
 *
 * @param string $logical Ten logic trong manifest.
 * @return string|null
 */
function pgds_asset_url( $logical ) {
	$m = pgds_manifest();
	if ( empty( $m[ $logical ] ) ) {
		return null;
	}
	return PGDS_URI . '/assets/dist/' . $m[ $logical ];
}

/**
 * Enqueue CSS + JS + preload font.
 */
function pgds_enqueue_assets() {
	$css = pgds_asset_url( 'main.css' );
	$js  = pgds_asset_url( 'app.js' );

	if ( $css ) {
		// Version = null vi filename da co hash -> tranh double cache-busting.
		wp_enqueue_style( 'pgds-main', $css, array(), null );
	}

	if ( $js ) {
		wp_enqueue_script( 'pgds-app', $js, array(), null, true );
		// Truyen config runtime cho JS (vd nonce sau nay).
		wp_localize_script(
			'pgds-app',
			'PGDS',
			array(
				'restUrl' => esc_url_raw( rest_url() ),
				'ytHost'  => 'https://www.youtube-nocookie.com',
			)
		);
	}

	// Binh luan: chi nap khi trang don + comment mo.
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'pgds_enqueue_assets' );

/**
 * Preload 2 font critical + preconnect (self-host nen chi la same-origin).
 * Font file dat o assets/fonts/. Neu chua co file, preload se bi bo qua boi trinh duyet.
 */
function pgds_preload_fonts() {
	$fonts = array(
		'/assets/fonts/be-vietnam-pro-400.woff2',
		'/assets/fonts/fraunces-700.woff2',
	);
	foreach ( $fonts as $f ) {
		$abs = PGDS_DIR . $f;
		if ( is_readable( $abs ) ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( PGDS_URI . $f )
			);
		}
	}
}
add_action( 'wp_head', 'pgds_preload_fonts', 1 );

/**
 * Them defer cho script cua theme (JS khong chan render).
 *
 * @param string $tag    The tag.
 * @param string $handle Handle.
 * @return string
 */
function pgds_script_defer( $tag, $handle ) {
	if ( 'pgds-app' === $handle && false === strpos( $tag, 'defer' ) ) {
		$tag = str_replace( ' src', ' defer src', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'pgds_script_defer', 10, 2 );

/**
 * Go jQuery o front-end (theme khong dung). Van giu o admin.
 */
function pgds_dequeue_jquery() {
	if ( ! is_admin() ) {
		// Chi go neu khong plugin nao phu thuoc (an toan: chi de-register khi khong co dep).
		// De giu an toan voi plugin, chi tat migrate.
		wp_deregister_script( 'jquery-migrate' );
	}
}
add_action( 'wp_enqueue_scripts', 'pgds_dequeue_jquery', 100 );
