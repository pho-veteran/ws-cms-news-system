<?php
require '/var/www/html/wp-load.php';

$posts = get_posts( array(
	'numberposts' => 50,
	'post_status' => 'publish',
) );

foreach ( $posts as $p ) {
	$surface = pgds_current_editorial_surface( $p->ID );
	echo "ID: {$p->ID} | Surface: {$surface} | Title: {$p->post_title}\n";
}
