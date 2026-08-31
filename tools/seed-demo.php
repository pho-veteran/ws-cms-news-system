<?php
/**
 * Seed additional demo posts to fill all 11 blocks (idempotent by _pgds_source_id = 'demo-*').
 * Run: wp eval-file /tools/seed-demo.php
 */

$plan = array(
	// category_slug => [number of additional posts, title prefix]
	'tin-phat-su'      => array( 8, 'Tin Phật sự' ),
	'song-an-lanh'     => array( 5, 'Sống an lành' ),
	'phat-tich'        => array( 5, 'Phật tích' ),
	'tot-doi-dep-dao'  => array( 5, 'Tốt đời đẹp đạo' ),
	'vietnam-buddhism' => array( 3, 'Vietnam Buddhism' ),
	'video'            => array( 4, 'Video' ),
	'infographic-emagazine' => array( 3, 'Emagazine' ),
);

$created = 0;
foreach ( $plan as $cat_slug => $spec ) {
	list( $n, $prefix ) = $spec;
	$term = get_term_by( 'slug', $cat_slug, 'category' );
	if ( ! $term ) {
		continue;
	}
	// Parent category (to assign a sensible primary category).
	$primary_slug = $term->parent ? get_term( $term->parent, 'category' )->slug : $cat_slug;
	$primary_id   = $term->parent ? $term->parent : $term->term_id;

	for ( $i = 1; $i <= $n; $i++ ) {
		$sid = "demo-{$cat_slug}-{$i}";
		$q   = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_pgds_source_id', 'meta_value' => $sid, 'posts_per_page' => 1, 'fields' => 'ids' ) );
		if ( $q->posts ) {
			continue; // already exists
		}
		$is_media = in_array( $cat_slug, array( 'video', 'infographic-emagazine' ), true );
		$cat_ids  = array( $term->term_id );
		if ( $is_media ) {
			$media = get_term_by( 'slug', 'media', 'category' );
			if ( $media ) {
				$cat_ids[] = $media->term_id;
			}
			$primary_id = $media ? $media->term_id : $term->term_id;
		}

		$pid = wp_insert_post(
			array(
				'post_title'    => "{$prefix} — bài mẫu {$i}",
				'post_content'  => "<p>Nội dung mẫu cho chuyên mục {$prefix}. Đây là bài seed để minh hoạ bố cục 11 khối trang chủ.</p><p>Đoạn thứ hai giúp sa-pô và thân bài có đủ độ dài hiển thị.</p>",
				'post_excerpt'  => "Bài mẫu {$i} thuộc chuyên mục {$prefix}, dùng để lấp đầy giao diện demo.",
				'post_status'   => 'publish',
				'post_type'     => 'post',
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() - ( $created * 3600 ) - 3600 ),
				'post_category' => $cat_ids,
			)
		);
		if ( ! is_wp_error( $pid ) ) {
			update_post_meta( $pid, '_pgds_source_id', $sid );
			update_post_meta( $pid, '_pgds_sapo', "Bài mẫu {$i} thuộc chuyên mục {$prefix}." );
			update_post_meta( $pid, '_pgds_primary_cat', $primary_id );
			if ( 'video' === $cat_slug ) {
				update_post_meta( $pid, '_pgds_youtube_id', 'IDrb0rGinII' );
			}
			$created++;
		}
	}
}

WP_CLI::success( "Demo seed complete. Created {$created} additional posts." );
