<?php
// Reset + set featured/photo cho demo (deterministic theo source_id).
$get_id = function ( $sid ) {
	$q = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_pgds_source_id', 'meta_value' => $sid, 'posts_per_page' => 1, 'fields' => 'ids' ) );
	return $q->posts ? (int) $q->posts[0] : 0;
};

// Xoa featured cu.
$all = get_posts( array( 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids' ) );
foreach ( $all as $id ) {
	delete_post_meta( $id, '_pgds_is_featured' );
	delete_post_meta( $id, '_pgds_feature_rank' );
	delete_post_meta( $id, '_pgds_photo_story' );
}

// Lead = Dai le Phat dan (1001), rank 1.
$featured = array(
	'legacy-1001' => 1,  // tin-phat-su (lead)
	'legacy-1002' => 2,  // song-an-lanh
	'legacy-1003' => 3,  // phat-tich
	'legacy-1007' => 4,  // tot-doi-dep-dao
);
foreach ( $featured as $sid => $rank ) {
	$id = $get_id( $sid );
	if ( $id ) {
		update_post_meta( $id, '_pgds_is_featured', '1' );
		update_post_meta( $id, '_pgds_feature_rank', $rank );
		WP_CLI::log( "featured {$sid} => post {$id} rank {$rank}" );
	}
}

// Photo story: 3 bai co anh dep.
foreach ( array( 'legacy-1001', 'legacy-1009', 'legacy-1023' ) as $sid ) {
	$id = $get_id( $sid );
	if ( $id ) {
		update_post_meta( $id, '_pgds_photo_story', '1' );
	}
}
WP_CLI::success( 'Seed featured/photo xong.' );
