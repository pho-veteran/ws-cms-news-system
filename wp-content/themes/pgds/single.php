<?php
/**
 * Single-post detail dispatcher.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$layout = pgds_detail_layout( get_queried_object() );

if ( 'emagazine' === $layout ) {
	get_header( 'emagazine' );
} else {
	get_header();
}

while ( have_posts() ) :
	the_post();

	get_template_part( 'template-parts/content-single-' . $layout );
endwhile;

get_footer();
