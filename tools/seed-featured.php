<?php
// Reset and set featured/photo metadata for the demo (deterministic by source_id).
$get_id = function ( $sid ) {
	$q = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_pgds_source_id', 'meta_value' => $sid, 'posts_per_page' => 1, 'fields' => 'ids' ) );
	return $q->posts ? (int) $q->posts[0] : 0;
};

// Remove existing featured metadata.
$all = get_posts( array( 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids' ) );
foreach ( $all as $id ) {
	delete_post_meta( $id, '_pgds_is_featured' );
	delete_post_meta( $id, '_pgds_feature_rank' );
	delete_post_meta( $id, '_pgds_photo_story' );
}

// Lead = Vesak Celebration (1001), rank 1.
$featured = array(
	'legacy-1001' => 1,  // Buddhist news (lead)
	'legacy-1002' => 2,  // Peaceful living
	'legacy-1003' => 3,  // Buddhist heritage
	'legacy-1007' => 4,  // Good life, beautiful way
);
foreach ( $featured as $sid => $rank ) {
	$id = $get_id( $sid );
	if ( $id ) {
		update_post_meta( $id, '_pgds_is_featured', '1' );
		update_post_meta( $id, '_pgds_feature_rank', $rank );
		WP_CLI::log( "featured {$sid} => post {$id} rank {$rank}" );
	}
}

// Photo story: 3 posts with beautiful images.
foreach ( array( 'legacy-1001', 'legacy-1009', 'legacy-1023' ) as $sid ) {
	$id = $get_id( $sid );
	if ( $id ) {
		update_post_meta( $id, '_pgds_photo_story', '1' );
	}
}
WP_CLI::success( 'Featured/photo seed complete.' );
