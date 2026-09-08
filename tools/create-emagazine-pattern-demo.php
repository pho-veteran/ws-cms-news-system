<?php
require '/var/www/html/wp-load.php';

// Find or create attachments for demo images
$attachments = get_posts( array(
	'post_type'   => 'attachment',
	'numberposts' => 5,
) );

$img1 = ! empty( $attachments[0] ) ? wp_get_attachment_url( $attachments[0]->ID ) : 'http://localhost:8080/wp-content/uploads/2026/09/sample-1.jpg';
$img2 = ! empty( $attachments[1] ) ? wp_get_attachment_url( $attachments[1]->ID ) : 'http://localhost:8080/wp-content/uploads/2026/09/sample-2.jpg';
$img3 = ! empty( $attachments[2] ) ? wp_get_attachment_url( $attachments[2]->ID ) : 'http://localhost:8080/wp-content/uploads/2026/09/sample-3.jpg';

// Content for E-magazine Demo post containing ALL 5 patterns filled with sample text & captions
$pattern_blocks = '<!-- wp:group {"align":"wide","className":"pgds-emagazine-chapter"} -->
<div class="wp-block-group alignwide pgds-emagazine-chapter"><p class="pgds-emagazine-chapter__number">Chương 01</p><h2 class="wp-block-heading">Hành trình Khai sáng và Nhận thức Tự tính</h2></div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"is-style-default"} -->
<p>Nội dung mở đầu chương 1: Trải qua nhiều giai đoạn phát triển, văn hóa Phật giáo luôn là chỗ dựa tinh thần vững chắc cho cộng đồng...</p>
<!-- /wp:paragraph -->

<!-- wp:quote {"align":"wide","className":"is-style-plain pgds-emagazine-pull-quote"} -->
<blockquote class="wp-block-quote alignwide is-style-plain pgds-emagazine-pull-quote"><p>"Giữ tâm thanh tịnh giữa biến động cuộc đời chính là suối nguồn của sự an lạc vĩnh hằng."</p><cite>Thượng tọa Thích Thanh Từ — Thiền viện Trúc Lâm</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:image {"align":"wide","sizeSlug":"full"} -->
<figure class="wp-block-image alignwide size-full"><img src="' . esc_url( $img1 ) . '" alt="Wide Image Demo"/><figcaption class="wp-element-caption">Ảnh 1: Quang cảnh Đại lễ Vesak rực rỡ sắc màu tâm linh — Ảnh: Ban Biên Tập PGDS</figcaption></figure>
<!-- /wp:image -->

<!-- wp:gallery {"linkTo":"none","align":"wide","columns":2} -->
<figure class="wp-block-gallery alignwide has-nested-images columns-2 is-cropped">
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="' . esc_url( $img2 ) . '" alt="Pair 1"/><figcaption class="wp-element-caption">Ảnh trái: Nghi lễ dâng hoa cầu nguyện bình an cho vạn chúng</figcaption></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="' . esc_url( $img3 ) . '" alt="Pair 2"/><figcaption class="wp-element-caption">Ảnh phải: Chư tôn đức tăng ni thực hiện nghi thức thắp nến hoa đăng</figcaption></figure>
<!-- /wp:image -->
</figure>
<!-- /wp:gallery -->

<!-- wp:image {"align":"full","sizeSlug":"full"} -->
<figure class="wp-block-image alignfull size-full"><img src="' . esc_url( $img1 ) . '" alt="Full Image Demo"/><figcaption class="wp-element-caption">Ảnh tràn toàn màn hình: Toàn cảnh không gian linh thiêng từ trên cao — Tác giả: Phóng viên Hoàng Nam</figcaption></figure>
<!-- /wp:image -->';

// Update post 71 with all 5 patterns filled
$post_id = 71;
wp_update_post( array(
	'ID'           => $post_id,
	'post_title'   => 'E-magazine Đặc Biệt: Hành Trình Khai Sáng & Di Sản Phật Giáo',
	'post_content' => $pattern_blocks,
) );

update_post_meta( $post_id, '_pgds_sapo', 'Tác phẩm E-magazine trải nghiệm đa phương tiện đặc biệt khắc họa hành trình gìn giữ di sản và tư tưởng triết lý Phật giáo qua các thời kỳ.' );
update_post_meta( $post_id, '_pgds_author_name', 'Nguyễn Văn A — Ảnh: Hoàng Nam' );
update_post_meta( $post_id, '_pgds_primary_cat', get_term_by( 'slug', 'emagazine', 'category' )->term_id ?? 0 );

echo "E-magazine post {$post_id} updated with all 5 patterns filled with sample data!\n";
