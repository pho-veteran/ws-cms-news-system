<?php
/**
 * E-magazine detail entry point.
 *
 * The dedicated visual treatment is implemented by issue #9; P0 keeps this stable
 * route while preserving the complete CMS-driven Article reader.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/content-single-article' );
