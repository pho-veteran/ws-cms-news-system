<?php
require '/var/www/html/wp-load.php';

// Post 4: Article
update_post_meta( 4, '_pgds_sapo', 'Đại lễ Phật đản PL.2570 đã chính thức diễn ra tại tất cả các tỉnh thành trên cả nước trong không khí trang nghiêm và thanh tịnh.' );
update_post_meta( 4, '_pgds_author_name', 'Biên tập viên Minh Anh' );

// Post 10: Video
update_post_meta( 10, '_pgds_sapo', 'Phim tài liệu ghi lại toàn cảnh Đại lễ Phật đản PL.2570 với hàng ngàn chư tôn đức tăng ni và Phật tử tham dự.' );
update_post_meta( 10, '_pgds_author_name', 'Truyền hình Phật giáo PGDS' );
update_post_meta( 10, '_pgds_youtube_id', 'https://www.youtube.com/watch?v=IDrb0rGinII' );
update_post_meta( 10, '_pgds_video_duration', '05:30' );

// Post 71: E-magazine
update_post_meta( 71, '_pgds_sapo', 'Tác phẩm E-magazine đặc biệt điều tra về các hình thức lừa đảo trực tuyến và giải pháp phòng tránh dành cho Phật tử và người dân.' );
update_post_meta( 71, '_pgds_author_name', 'Nhóm Phóng viên Thực hiện' );

// Insert Gutenberg E-magazine blocks into Post 71 content if empty
$emagazine_blocks = '<!-- wp:paragraph {"className":"is-style-default"} -->
<p>Trong thời đại công nghệ số phát triển mạnh mẽ, các hình thức lừa đảo trực tuyến ngày càng trở nên tinh vi hơn bao giờ hết...</p>
<!-- /wp:paragraph -->

<!-- wp:group {"align":"wide","className":"pgds-emagazine-chapter"} -->
<div class="wp-block-group alignwide pgds-emagazine-chapter"><p class="pgds-emagazine-chapter__number">Chương 01</p><h2 class="wp-block-heading">Bẫy lừa đảo mạng xã hội</h2></div>
<!-- /wp:group -->

<!-- wp:quote {"align":"wide","className":"is-style-plain pgds-emagazine-pull-quote"} -->
<blockquote class="wp-block-quote alignwide is-style-plain pgds-emagazine-pull-quote"><p>"Tâm bình thì thế giới bình, tỉnh giác trước mọi cạm bẫy ảo trên mạng xã hội."</p><cite>Thượng tọa Thích Thanh Từ</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:image {"align":"wide","sizeSlug":"full"} -->
<figure class="wp-block-image alignwide size-full"><figcaption class="wp-element-caption">Hình ảnh minh họa tác phẩm E-magazine — Ảnh: Ban Biên Tập</figcaption></figure>
<!-- /wp:image -->';

wp_update_post( array(
	'ID'           => 71,
	'post_content' => $emagazine_blocks,
) );

echo "Sample post metadata updated successfully!\n";
