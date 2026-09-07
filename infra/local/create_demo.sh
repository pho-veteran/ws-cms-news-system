#!/usr/bin/env bash
set -e

# ID ảnh bìa lấy từ DB (36, 35, 34...)
COVER_IMAGE_ID=36
IMAGE_1_ID=35
IMAGE_2_ID=34

echo "Seeding categories..."
docker compose run --rm wpcli -c "wp eval 'pgds_seed_categories();' --allow-root"

CONTENT='<!-- wp:paragraph -->
<p>Kinh doanh online ngày càng phổ biến do xu thế công nghệ 4.0. Nhiều cá nhân tận dụng mạng xã hội (Facebook, Zalo, YouTube...) để buôn bán, kiếm lời. Nhờ thủ tục đơn giản, ít vốn, dễ tiếp cận lượng lớn khách hàng, kinh doanh online mang lại lợi nhuận cao.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Hệ quả từ việc tàn phá thiên nhiên</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Tuy nhiên, sự phát triển này cũng kéo theo tình trạng lừa đảo tinh vi, đánh vào tâm lý hám rẻ của người mua.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://picsum.photos/1000/600" alt=""/><figcaption class="wp-element-caption">Những gì con người đang gánh chịu đều từ những nhân đã gieo từ nhiều đời nhiều kiếp</figcaption></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Mặc dù cơ quan chức năng đã liên tục cảnh báo và triệt phá nhiều đường dây, tình trạng lừa đảo vẫn tái diễn với nhiều thủ đoạn mới. Vì vậy, người dân cần nâng cao cảnh giác, không tin vào các lời chào mời "việc nhẹ lương cao" hay quà tặng miễn phí trên mạng để tránh sập bẫy kẻ gian.</p>
<!-- /wp:paragraph -->

<!-- wp:gallery {"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-default is-cropped">
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://picsum.photos/500/600" alt=""/></figure>
<!-- /wp:image -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://picsum.photos/500/600" alt=""/></figure>
<!-- /wp:image -->
<figcaption class="wp-element-caption">Sự phát triển này cũng kéo theo tình trạng lừa đảo tinh vi</figcaption></figure>
<!-- /wp:gallery -->

<!-- wp:paragraph -->
<p>Thượng tọa Thích Đức Thiện, Phó Chủ tịch kiêm Tổng Thư ký Hội đồng Trị sự TƯ GHPGVN nhắc lại câu thành ngữ “Nhân nào quả nấy”, chỉ ra rằng những gì con người đang gánh chịu đều từ những nhân đã gieo từ nhiều đời nhiều kiếp.</p>
<!-- /wp:paragraph -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>“Trong bối cảnh công nghệ số, việc tiếp cận thông tin sai lệch rất dễ dàng. Người tiêu dùng cần hết sức tỉnh táo để không bị rơi vào cạm bẫy của những kẻ lừa đảo.”</p><cite>Thượng tọa Thích Đức Thiện</cite></blockquote>
<!-- /wp:quote -->'

echo "Creating the post..."
POST_ID=$(docker compose run --rm wpcli -c "wp post create --post_type=post --post_title='Demo E-magazine: Vấn nạn lừa đảo trên mạng' --post_content='$CONTENT' --post_status=publish --post_category='emagazine' --porcelain --allow-root")

if [ -n "$POST_ID" ]; then
    echo "Post created with ID: $POST_ID"
    # Set the thumbnail
    docker compose run --rm wpcli -c "wp post meta update $POST_ID _thumbnail_id $COVER_IMAGE_ID --allow-root"
    
    # Set sapo and primary category
    docker compose run --rm wpcli -c "wp post meta update $POST_ID _pgds_sapo 'Đây là dòng sapo mẫu cho bài viết E-magazine, hiển thị to và rõ nét ngay phía dưới tiêu đề để thu hút người đọc.' --allow-root"
    
    # Get the term ID of emagazine
    TERM_ID=$(docker compose run --rm wpcli -c "wp term get category emagazine --field=term_id --allow-root")
    docker compose run --rm wpcli -c "wp post meta update $POST_ID _pgds_primary_cat $TERM_ID --allow-root"
    
    echo "Demo URL: http://localhost:8080/?p=$POST_ID"
else
    echo "Failed to create post."
fi
