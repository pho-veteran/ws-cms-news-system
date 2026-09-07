<?php
/**
 * Single-post detail dispatcher.
 *
 * @package pgds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_obj = get_queried_object();
$layout   = pgds_detail_layout( $post_obj );

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
