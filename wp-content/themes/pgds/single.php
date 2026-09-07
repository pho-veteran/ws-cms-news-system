<?php
/**
 * Single-post detail dispatcher.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$layout = pgds_detail_layout( get_post() );
	get_template_part( 'template-parts/content-single-' . $layout );
endwhile;

get_footer();
