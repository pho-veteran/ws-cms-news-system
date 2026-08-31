# PROPOSAL 01 — WordPress "Phật giáo và Đời sống"

> **Phạm vi:** Greenfield. Không có code nào được tái sử dụng.
> **Nhân lực:** 1–2 engineer. **Thời gian:** 3 ngày (6–9 person-day).
> **Vòng đời hệ thống:** ephemeral, tối đa 6 tháng.

---

## 1. TL;DR

WordPress 6.x + PHP 8.3 + MariaDB 10.11 + Redis, chạy trên một Lightsail 2GB duy nhất. Theme là **standalone custom theme `pgds`** (classic PHP templates, không FSE, không parent theme). Gutenberg chỉ dùng cho nội dung bài viết.

Cache 2 tầng: Redis object cache + Nginx FastCGI page cache. Cloudflare chỉ cache static asset, **không cache HTML**. Media nằm trên disk instance, không offload S3.

Migration 2.000 bài chạy xong trong Ngày 2, verify Ngày 3, switch DNS cuối cùng.

**Điều kiện tiên quyết (D0, trước khi bấm đồng hồ):** dataset 2.000 bài đã validate, toàn bộ media file/URL đã kiểm, 5 quyết định BOM đã chốt. Không có D0 thì lịch 3 ngày không thành lập.

---

## 2. Phạm vi giao diện

Website dùng một design system thống nhất cho trang chủ và toàn bộ template nội dung. Trong chu kỳ launch phải triển khai:

- Trang chủ với 11 vùng nội dung chính, header, navigation và footer.
- `single`, `category`, `archive`, `author`, `search`, `404` và page tĩnh.
- Hành vi JavaScript cho navigation mobile, YouTube facade và media tabs.
- Responsive tại 480px, 768px, 880px và 1180px.
- Ảnh responsive bằng `<picture>`/`srcset`, kích thước ảnh khai báo tường minh và placeholder không gây layout shift.
- Semantic HTML, keyboard navigation và ARIA cho mọi thành phần tương tác.

### 2.1 Nav (7 mục)

```
1. Trang chủ
2. Tin Phật sự      → Tin Giáo hội, Sự kiện – Lễ hội
3. Sống an lành     → Ẩm thực chay, Lối sống xanh
4. Phật tích        → Chùa – Am, Di tích – Danh thắng
5. Media            → Video, Infographic – Emagazine
6. Tốt đời – đẹp đạo → Người tốt việc tốt, Thiện nguyện
7. Vietnam Buddhism
```

### 2.2 Các vùng trang chủ

1. Header + logo
2. Nav 7 mục
3. Feature grid: 1 lead + 3 secondary card + 1 panel "Tin ảnh"
4. SVG divider
5. Media block (dark): 3 tab + 1 featured + 4 thumbnail + 3 bullet
6. Content grid 1: "Tin Phật sự" 3 card + 7 list item
7. Sidebar: "Đọc nhiều nhất" (5 item) + "Lịch Vạn Niên"
8. Divider
9. Three-category block: 3 cột, mỗi cột 1 feature + 2 mini
10. Content grid 2: 5 bài mixed + "Vietnam Buddhism" (3 bài) + sidebar "Lời Phật dạy" (4 item)
11. Footer 4 cột

### 2.3 Chuẩn semantic và accessibility

- Mỗi trang có đúng một `<main>` và một `<h1>` phù hợp ngữ cảnh.
- Search là `<form>` hoàn chỉnh với label, `name` và submit.
- Dropdown và navigation mobile dùng được bằng keyboard, quản lý focus và có ARIA state.
- "Xem thêm" và mọi tiêu đề card điều hướng được đều dùng `<a>`.
- Logo và ảnh nội dung khai báo `width`/`height` để tránh CLS.
- Text clamp không được làm mất nội dung khi zoom 200%.
- Nội dung pháp lý và thông tin toà soạn phải được duyệt trước go-live.

---

## 3. Theme `pgds`

**Chốt: standalone custom theme.** Không child theme, không GeneratePress, không parent. Lý do: cấu trúc cần thiết đã là một theme đầy đủ; thêm parent chỉ thêm CSS phải override và một dependency ngoài.

### 3.1 Design token — đầy đủ

Token inventory hoàn chỉnh cho production.

#### 3.1.1 Màu

Toàn bộ màu production dùng các token dưới đây; không đặt màu hard-code trong component.

```css
:root{
  /* ── Brand ─────────────────────────────────────────────────── */
  --pgds-ink:         #2A211B;  /* text chính */
  --pgds-paper:       #F4EEDF;  /* nền trang */
  --pgds-paper-deep:  #EAE1CC;  /* nền vùng nhấn */
  --pgds-robe:        #5C3A2E;  /* nâu áo cà sa */
  --pgds-robe-deep:   #452A21;  /* nâu đậm — header, media block */
  --pgds-gilt:        #A9812F;  /* vàng nhấn */
  --pgds-gilt-light:  #C9A24E;  /* vàng sáng — link, hover */
  --pgds-moss:        #3F5D4E;  /* xanh rêu — badge */
  --pgds-line:        #D9CDAE;  /* viền */

  /* ── Surface ───────────────────────────────────────────────── */
  --pgds-white:       #fff;
  --pgds-surface-1:   #fefdfa;  /* card nền sáng nhất */
  --pgds-surface-2:   #faf8f2;  /* card nền phụ */
  --pgds-surface-3:   #EFE6CF;  /* vùng sidebar */

  /* ── Text phụ ──────────────────────────────────────────────── */
  --pgds-text-muted:  #8a7f68;  /* meta, ngày tháng */
  --pgds-text-soft:   #6b5f4a;  /* mô tả */
  --pgds-text-dim:    #9B8F79;  /* text mờ nhất */
  --pgds-text-alt:    #5c5243;  /* text phụ trên nền sáng */

  /* ── Accent trên nền tối ───────────────────────────────────── */
  --pgds-gilt-bright: #E8C468;  /* nhấn trong media block */
  --pgds-gilt-pale:   #E4D6AE;
  --pgds-line-dark:   #5a4234;  /* viền trên nền tối */
  --pgds-line-soft:   #C4B79B;  /* viền nhạt */
  --pgds-line-mid:    #8C7E68;

  /* ── Alpha ─────────────────────────────────────────────────── */
  --pgds-shadow-sm:   rgba(42,33,27,.15);
  --pgds-shadow-robe: rgba(92,58,46,.12);
  --pgds-shadow-flat: rgba(0,0,0,.10);
  --pgds-overlay:     rgba(0,0,0,.72);   /* gradient trên ảnh */
  --pgds-overlay-hi:  rgba(0,0,0,.88);
  --pgds-on-dark-35:  rgba(255,255,255,.35);
  --pgds-on-dark-25:  rgba(255,255,255,.25);
  --pgds-on-dark-22:  rgba(255,255,255,.22);
}
```

Hai quyết định cần chốt ở D1:
- **`--lotus: #E3A8AA`** được khai báo nhưng không dùng ở đâu. Giữ làm accent dự phòng hay bỏ — quyết dứt điểm.
- Các cặp màu gần nhau (`#8a7f68` / `#8C7E68`, `#6b5f4a` / `#5c5243`) có thể là biến thể không chủ đích. Gộp hay giữ riêng — quyết khi normalize.

#### 3.1.2 Typography

```scss
// Font family
$font-body:    'Be Vietnam Pro', system-ui, sans-serif;  // 400/500/600/700
$font-display: 'Fraunces', Georgia, serif;               // opsz 9..144, 400/700

// Scale — 9 mức
$fs-hero:   44px;   // tiêu đề lead trang chủ
$fs-xl:     20px;
$fs-lg:     19px;   // heading section
$fs-md:     16px;
$fs-base:   15px;   // thân bài
$fs-sm:     14px;
$fs-xs:     13px;   // MẶC ĐỊNH, dùng nhiều nhất
$fs-2xs:    12px;   // meta
$fs-3xs:    11.5px; // nhãn, badge

// Line-height
$lh-tight:  1.35;   // tiêu đề card
$lh-snug:   1.4;
$lh-normal: 1.45;
$lh-relax:  1.55;   // thân bài
$lh-loose:  1.6;
$lh-none:   1;      // badge, số

// Letter-spacing
$ls-wide:   .04em;
$ls-wider:  .06em;
$ls-widest: .08em;  // nhãn uppercase
```

Không thêm cỡ chữ ngoài scale; giá trị trung gian phải gộp vào mức gần nhất.

Production **self-host `.woff2`**, dùng `font-display: swap` và preload hai file critical để bỏ third-party font origin và kiểm soát LCP.

#### 3.1.3 Spacing & layout

```scss
// Container
$container-max: 1180px;

// Spacing scale theo bậc 4px
$sp-1:  4px;
$sp-2:  8px;
$sp-3:  12px;
$sp-4:  16px;
$sp-5:  20px;
$sp-6:  24px;
$sp-7:  28px;   // khoảng cách mặc định giữa các section
$sp-8:  32px;
$sp-10: 40px;
$sp-12: 48px;
```

Không thêm spacing ngoài scale; giá trị trung gian làm tròn về bậc gần nhất.

#### 3.1.4 Radius & shadow

```scss
$radius-xs:   2px;
$radius-sm:   4px;
$radius-md:   6px;    // mặc định cho card
$radius-lg:   8px;
$radius-xl:   10px;
$radius-2xl:  14px;   // panel lớn
$radius-full: 50%;    // avatar, dot

$shadow-sm: 0 1px 3px var(--pgds-shadow-flat);
$shadow-md: 0 2px 8px var(--pgds-shadow-sm);
$shadow-lg: 0 4px 16px var(--pgds-shadow-robe);
```

Không thêm radius ngoài scale; giá trị trung gian gộp về mức gần nhất.

#### 3.1.5 Breakpoint

Production dùng bốn breakpoint:

```scss
$bp-sm: 480px;
$bp-md: 768px;
$bp-lg: 880px;
$bp-xl: 1180px;  // khớp $container-max
```

Mọi template phải test ở cả bốn mốc, không chỉ trang chủ.

#### 3.1.6 Z-index

Dùng thang z-index cố định để tránh xung đột với admin bar và plugin:

```scss
$z-base:     1;
$z-dropdown: 100;
$z-sticky:   200;
$z-overlay:  900;
$z-modal:    1000;
```

#### 3.1.7 Chuẩn nghiệm thu

Nghiệm thu bằng visual regression ở tối thiểu 360px và 1280px. Token là phương tiện; tính nhất quán thị giác, không overflow và không layout shift là mục tiêu.

### 3.2 CSS

SCSS, ITCSS layer (`settings → tools → generic → elements → objects → components → utilities`), BEM-lite (`.pgds-card`, `.pgds-card__title`, `.pgds-card--lead`). Build ra 1 file CSS, tên có content hash.

Token màu là **CSS custom property** (runtime, cho phép đổi theme sau). Token spacing/typography/radius/breakpoint là **SCSS variable** (compile-time, không cần đổi runtime).

### 3.3 Cấu trúc

```
wp-content/themes/pgds/
├── style.css
├── functions.php
├── inc/
│   ├── setup.php          # theme support, nav menu, image size
│   ├── enqueue.php        # asset + hash versioning
│   ├── cpt-tax.php        # CPT + taxonomy
│   ├── meta-fields.php    # custom field + meta box
│   ├── query-blocks.php   # query 11 block trang chủ + dedup
│   ├── template-tags.php
│   ├── seo-schema.php     # chỉ VideoObject + NewsMediaOrganization
│   └── admin-ux.php
├── template-parts/
│   ├── card-lead.php  card-secondary.php  card-mini.php
│   ├── list-item.php  sidebar-popular.php  sidebar-lunar.php
│   └── video-facade.php
├── front-page.php
├── single.php
├── category.php
├── archive.php          # dùng chung cho tag, taxonomy, author, date
├── search.php
├── 404.php
├── page.php
├── header.php  footer.php  sidebar.php
└── assets/{scss,js,fonts,dist}
```

**Không có trong launch:** `single-video.php`, `single-longform.php`, `category-media.php`, `category-vietnam-buddhism.php`, `author.php`, `page-lien-he.php`, `taxonomy-pgds_topic.php`. Tất cả fallback về `single.php` / `category.php` / `archive.php`. Xem mục 10.

### 3.4 JavaScript

Vanilla ES2020, không framework. **Build tool: `@wordpress/scripts`** (chốt, không phải Vite — nó xử lý sẵn asset versioning và dependency của WP).

Launch chỉ 3 module:

| Module | Vì sao bắt buộc |
|---|---|
| `nav-mobile` | Navigation phải dùng được trên màn hình hẹp và bằng keyboard. |
| `youtube-facade` | Tránh tải iframe trước tương tác và bảo vệ LCP trên trang có video. |
| `media-tabs` | Điều khiển các nhóm nội dung trong media block. |

**Hoãn:** `search-suggest`, `reading-progress`, `share`, `photo-panel-slider`, `lazy-embed`.

Cả ba module launch phải có keyboard support, quản lý focus và ARIA state đúng.

---

## 4. Data model

### 4.1 Category (khớp nav)

```
tin-phat-su/{tin-giao-hoi, su-kien-le-hoi}
song-an-lanh/{am-thuc-chay, loi-song-xanh}
phat-tich/{chua-am, di-tich-danh-thang}
media/{video, infographic-emagazine}
tot-doi-dep-dao/{nguoi-tot-viec-tot, thien-nguyen}
vietnam-buddhism
```

### 4.2 CPT & taxonomy

| | Slug | Ghi chú |
|---|---|---|
| CPT | `pgds_teaching` | "Lời Phật dạy" — sidebar block #11 |
| CPT | `pgds_lunar_note` | "Lịch Vạn Niên" — sidebar block #8 |
| Taxonomy | `pgds_topic` | chủ đề cắt ngang category |

Không tạo `pgds_author_role` ở launch — dùng WP role sẵn có.

### 4.3 Post meta

| Key | Type | Dùng ở |
|---|---|---|
| `_pgds_sapo` | text | card + article lead |
| `_pgds_primary_cat` | int (term_id) | breadcrumb, schema, canonical category |
| `_pgds_youtube_id` | string | **1 video canonical/bài** — xem 6.1 |
| `_pgds_youtube_dur` | int (giây) | badge thời lượng |
| `_pgds_is_featured` | bool | slot lead trang chủ |
| `_pgds_feature_rank` | int | thứ tự slot |
| `_pgds_photo_story` | bool | panel "Tin ảnh" |
| `_pgds_source` | text | nguồn tin |

Bỏ `_pgds_reading_time` — tính runtime từ word count, không cần lưu.

### 4.4 Query 11 block — deterministic, không transient

Trang chủ có 11 vùng nội dung; một bài không được xuất hiện hai lần. Cách làm:

1. **Slot curated trước:** lead, featured, photo panel lấy từ `_pgds_is_featured` + `_pgds_feature_rank` (editor kiểm soát).
2. **Sau đó query category theo batch**, loại các ID đã dùng.
3. **Fallback tất định** khi slot curated trống: bài mới nhất của category tương ứng.
4. **Thứ tự dedup tường minh**, ghi rõ trong `inc/query-blocks.php`: slot curated → media block → content grid 1 → three-category → content grid 2 → sidebar.

**Không dùng transient cache cho từng block.** Toàn bộ trang chủ đã được FastCGI cache; cache thêm bên trong là dư, và `$used_ids` chia sẻ giữa các block có TTL riêng sẽ sinh trạng thái không nhất quán. Trên cache MISS, ~11 `WP_Query` với Redis object cache mất khoảng 50–150ms — một lần sau mỗi purge, chấp nhận được.

---

## 5. Cache — ưu tiên "sửa là thấy ngay"

### 5.1 Nguyên tắc

**Người đang sửa bài luôn thấy thay đổi tức thì**, vì cookie `wordpress_logged_in_` khiến mọi tầng bypass. Yêu cầu "reflect ngay" chỉ áp dụng cho **khách anonymous** — và giải quyết bằng purge explicit, không bằng TTL ngắn.

### 5.2 Hai tầng, không ba

| Tầng | Cache gì | Invalidate |
|---|---|---|
| Redis object cache | query, option, meta | WP core tự lo, không purge tay |
| Nginx FastCGI | HTML anonymous | flush khi nội dung đổi |
| Cloudflare | **chỉ static asset** | filename có hash |

**Cloudflare không cache HTML.** Quyết định có chủ đích:
- Xoá stale window ở edge → purge origin xong là request kế tiếp đã fresh.
- Từ 2 purge target về 1, bỏ luôn thứ tự "origin trước, edge sau".
- Bỏ tương tác với Origin Cache Control (Free plan không tắt được cái này).
- Free plan chỉ chắc chắn có Purge Everything + Purge by URL → không nên phụ thuộc purge chính xác ở edge.

Chi phí: origin nhận toàn bộ request HTML. Ở 300k PV/tháng ≈ 0.12 req/s trung bình, peak ~5–10 req/s. FastCGI serve từ disk. Không đáng kể với 2 vCPU.

### 5.3 Nginx FastCGI

```nginx
fastcgi_cache_path /var/cache/nginx/fcgi levels=1:2 keys_zone=WP:32m
                   inactive=12h max_size=1g;
fastcgi_cache_key "$scheme$request_method$host$request_uri";

set $skip 0;
if ($request_method = POST)  { set $skip 1; }
if ($arg_s != "")            { set $skip 1; }
if ($arg_preview != "")      { set $skip 1; }
if ($request_uri ~* "/wp-admin/|/wp-login\.php|/wp-json/|/wp-cron\.php|/xmlrpc\.php") { set $skip 1; }
if ($http_cookie ~* "wordpress_logged_in_|wp-postpass_|comment_author_")             { set $skip 1; }

fastcgi_cache        WP;
fastcgi_cache_bypass $skip;
fastcgi_no_cache     $skip;
fastcgi_cache_valid  200 301 302 12h;
fastcgi_cache_lock   on;
fastcgi_cache_use_stale error timeout http_500 http_502 http_503;

add_header X-Cache $upstream_cache_status always;
```

Chú ý:
- **TTL 12h**, không phải 1h. Vì purge là explicit trên mọi thay đổi, TTL không còn là cơ chế đúng-sai — chỉ là hit rate.
- `fastcgi_cache_lock` là thứ chống thundering herd: mỗi URL chỉ 1 lần render PHP dù nhiều request đồng thời.
- **Không dùng** `use_stale updating` / `background_update`. Sau khi flush sạch thì không còn bản cũ để serve; hai directive này chỉ có nghĩa với expiry tự nhiên.
- **Không skip toàn bộ query string.** URL có `utm_*` từ social phải được cache. Chỉ skip `s=` và `preview=`.
- **Sitemap thì cache**, đừng skip — nó đắt để sinh.
- **Cache để trên disk, không tmpfs.** tmpfs ăn thẳng vào RAM của instance 2GB.

### 5.4 Purge: flush sạch, không mapping path

Cách phức tạp là suy ra chính xác path bị ảnh hưởng khi sửa 1 bài: URL bài, trang chủ, các category, tag, author, feed, sitemap, archive theo tháng. Dễ sót, mà sót thì thành bug "sao chưa thấy đổi".

Ở tải này, **xoá toàn bộ** là đúng: một lệnh, không sót gì, không cần logic mapping.

```php
<?php // wp-content/mu-plugins/pgds-cache-flush.php

function pgds_flush_page_cache() {
    $dir = '/var/cache/nginx/fcgi';
    if (!is_dir($dir)) { return; }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) { $f->isDir() ? @rmdir($f) : @unlink($f); }
}

add_action('save_post',          'pgds_flush_page_cache', 10, 0);
add_action('deleted_post',       'pgds_flush_page_cache');
add_action('edited_term',        'pgds_flush_page_cache');
add_action('wp_update_nav_menu', 'pgds_flush_page_cache');
```

- Không cần module `ngx_cache_purge` (phải build nginx riêng) — xoá file trực tiếp là đủ.
- **Điều kiện:** php-fpm phải ghi được thư mục cache. Cho nginx + php-fpm cùng group, thư mục group-writable.
- Chỉ hook `save_post`, không hook autosave/revision.
- **Khi nào đổi sang purge chính xác:** khi vượt ~500k–1M PV/tháng. Chưa tới thì đừng làm.

### 5.5 Browser cache — chỗ dễ bỏ sót

Nếu browser khách cache HTML thì purge server vô nghĩa với người đó.

```nginx
location / {
    add_header Cache-Control "no-cache" always;
}
location ~* \.(css|js|woff2|webp|jpg|jpeg|png|svg)$ {
    add_header Cache-Control "public, max-age=31536000, immutable";
}
```

`no-cache` nghĩa là "cache nhưng phải revalidate", không phải "không cache" — kết hợp ETag thì 304 rất nhẹ. Static 1 năm + `immutable`, đổi nội dung bằng đổi tên file.

### 5.6 Cloudflare — 2 Cache Rule (free cho 10)

```
Rule 1  bypass : URI path chứa /wp-admin/, /wp-login.php, /wp-json/,
                 /wp-cron.php, /xmlrpc.php          → Bypass cache
Rule 2  static : extension css|js|woff2|webp|jpg|png|svg
                 → Eligible for cache, Edge TTL 1 month
```

Không cần rule bypass-theo-cookie vì HTML không cache ở edge. Dùng 2/10 slot.

### 5.7 Redis

Drop-in `object-cache.php`. `maxmemory 160mb`, `maxmemory-policy allkeys-lru`. Chỉ object cache, không dính HTML.

### 5.8 Ngân sách RAM — cache phải vừa máy 2GB

Cache chỉ có tác dụng nếu nó không đẩy máy vào swap. Trên instance 2GB, ngân sách phải tính trước rồi mới chọn số PHP worker:

| Thành phần | RAM |
|---|---|
| OS + nginx | ~250 MB |
| MariaDB (`innodb_buffer_pool_size=256M`) | ~400 MB |
| Redis (`maxmemory 160mb`) | ~160 MB |
| PHP-FPM: `pm=ondemand`, `pm.max_children=6` × ~60MB | ~360 MB |
| **Tổng** | **~1.17 GB** |
| **Còn lại cho OS page cache** | **~850 MB** |

- **`pm.max_children=6`** là điểm khởi đầu, tune theo quan sát thật. Chỉ tăng khi thấy `max_children reached` trong log.
- **FastCGI cache để trên disk, KHÔNG tmpfs.** tmpfs ăn thẳng vào 2GB RAM; để OS page cache lo phần nóng.
- **2GB swap** là bảo hiểm chống OOM, không phải capacity. Nếu swap được dùng thường xuyên thì cấu hình sai.
- Trong lúc import ảnh: giảm `pm.max_children` xuống 2 và chạy `nice -n 19`.

---

## 6. YouTube

### 6.1 Mô hình dữ liệu — chốt

**1 video canonical/bài** lưu ở `_pgds_youtube_id` + `_pgds_youtube_dur`. Video này dùng cho: badge thời lượng trên card, thumbnail, schema `VideoObject`, video sitemap.

Video phụ trong thân bài dùng block embed thường, **không** vào meta, **không** vào schema. Lý do: `_pgds_youtube_id` là single meta, không mô hình hoá được nhiều video mà không sinh nhập nhằng "cái nào là canonical".

### 6.2 Facade bắt buộc

```html
<figure class="pgds-video" data-pgds="youtube-facade" data-video-id="IDrb0rGinII">
  <img class="pgds-video__poster" src="/wp-content/uploads/yt/IDrb0rGinII-640.webp"
       width="1280" height="720" loading="lazy" decoding="async"
       alt="Toàn cảnh Đại lễ Phật đản PL.2570">
  <button class="pgds-video__play" type="button"
          aria-label="Phát video: Toàn cảnh Đại lễ Phật đản PL.2570">
    <svg aria-hidden="true" focusable="false">…</svg>
  </button>
  <span class="pgds-video__dur">12:34</span>
</figure>
```

Click → chèn iframe `youtube-nocookie.com` với `autoplay=1`. Thumbnail lưu local (disk instance), không hotlink.

### 6.3 Fallback bắt buộc định nghĩa

| Trạng thái | Hành vi |
|---|---|
| Video private / removed / age-restricted | Ẩn facade, hiện text "Video không còn khả dụng", **không** xuất `VideoObject` |
| API fail / hết quota | Dùng meta đã lưu lần cuối, không ghi đè bằng giá trị rỗng |
| Chưa có thumbnail local | Placeholder gradient đúng tỷ lệ, không để layout shift |

### 6.4 API sync

Cron 1 lần/ngày, lấy `duration` + `title` cho các bài có `_pgds_youtube_id`. Quota YouTube Data API v3 tính theo **đơn vị/endpoint**, không phải "10.000 request/ngày" — `videos.list` tốn 1 unit/call, batch tối đa 50 ID/call. Ghi rõ số này trong runbook.

Kiểm tra điều khoản YouTube về việc tải và lưu thumbnail trước khi triển khai.

### 6.5 Về kỳ vọng hiệu năng

Facade thay iframe trực tiếp là cải thiện đã được chứng minh rộng rãi, nhưng **con số cụ thể là mục tiêu cần đo, không phải cam kết**. Đo trước/sau bằng Lighthouse trên chính trang có video, báo cáo số thật.

---

## 7. SEO — chốt sở hữu output

**Plugin: The SEO Framework** (chốt, không phải Rank Math — nhẹ hơn, ít bloat admin, đủ cho news site).

Phân chia sở hữu, tránh duplicate:

| Output | Chủ sở hữu |
|---|---|
| `<title>`, meta description | Plugin |
| Canonical | Plugin |
| Open Graph, Twitter Card | Plugin |
| `NewsArticle`, `BreadcrumbList`, `WebSite` | Plugin |
| XML sitemap chính | Plugin |
| **`VideoObject`** | **Theme** (`inc/seo-schema.php`) |
| **`NewsMediaOrganization`** | **Theme** |
| **Video sitemap** | **Theme** |
| News sitemap | Plugin nếu có, không thì hoãn |

**Gate bắt buộc:** chạy Rich Results Test trên 1 bài thường + 1 bài có video, xác nhận **không có schema bị xuất 2 lần**.

---

## 8. Bill of materials — chốt cả 5

Build 3 ngày không được để hở quyết định.

| Hạng mục | Chốt | Bỏ |
|---|---|---|
| SEO | The SEO Framework | Rank Math |
| Page cache | **Nginx FastCGI** (config tay, không plugin) | W3 Total Cache |
| Media offload | **Không dùng** — media trên disk | WP Offload Media, Media Cloud |
| Custom field | **Custom meta box** (`inc/meta-fields.php`) | ACF |
| Build tool | `@wordpress/scripts` | Vite |

Plugin list cuối cùng: The SEO Framework, Redis Object Cache, WP Mail SMTP (SES), Two Factor. **Bốn plugin.** Mọi thứ khác là code trong theme.

Bỏ ACF vì: 8 field đơn giản, thêm dependency + có bản Pro tính phí + di chuyển dữ liệu khó. Meta box tay tốn ~2h.

---

## 9. Migration 2.000 bài

### 9.1 D0 — điều kiện tiên quyết

Chưa xong D0 thì **không bắt đầu đếm 3 ngày**:

- [ ] Dump/export nguồn đầy đủ 2.000 bài, đã đọc được bằng script
- [ ] Mapping category nguồn → 13 category đích (bảng tường minh)
- [ ] Toàn bộ URL media kiểm tra HTTP 200, biết tổng dung lượng (dự kiến 25–40 GB)
- [ ] Đo throughput thật: import 50 bài + xử lý ảnh, suy ra thời gian cho 2.000

### 9.2 Script

WP-CLI command, **idempotent** (chạy lại không tạo trùng — khoá theo `_pgds_source_id`):

```bash
wp pgds import --file=data.json --batch=200 --dry-run
wp pgds import --file=data.json --batch=200
wp pgds media-variants --regenerate
wp pgds build-redirects --out=/etc/nginx/redirects.map
```

Ngưỡng dừng: dry-run fail > 2% thì **stop, không import**.

### 9.3 Ảnh

Sinh variant + WebP. **Chạy `nice -n 19` và giảm `pm.max_children` xuống 2 trong lúc chạy** — xử lý ảnh là thứ dễ đẩy instance 2GB vào swap nhất.

Nếu throughput đo được ở D0 cho thấy quá chậm: tạm nâng instance lên 4GB trong ngày import (~$0.40/ngày), xong hạ lại.

Lưu ý: resize Lightsail **không** in-place. Quy trình là snapshot → tạo instance mới bundle lớn hơn → detach/attach static IP → verify → cutover → xoá instance cũ. Có downtime ngắn ở bước đổi IP, nên chỉ làm khi đã có snapshot và trước khi switch DNS.

### 9.4 Redirect

Sinh `redirects.map` cho nginx (`map` directive), không dùng plugin redirect — 2.000 rule trong DB là gánh nặng mỗi request.

```nginx
map $request_uri $pgds_redirect { include /etc/nginx/redirects.map; default ""; }
if ($pgds_redirect != "") { return 301 $pgds_redirect; }
```

### 9.5 Một môi trường — cửa sổ trước cutover chính là rehearsal

Không cần staging instance riêng. Site chưa live cho tới khi Cloudflare DNS trỏ vào. Nên toàn bộ Ngày 1–3 là môi trường rehearsal, **tốn $0**.

Sau go-live, mọi deploy là production deploy.

---

## 10. Scope — cắt gì, giữ gì

### Trong launch

Trang chủ (11 block), `single.php`, `category.php`, `archive.php` (dùng chung cho tag/taxonomy/author/date), `search.php`, `404.php`, `page.php`, header, footer, sidebar. 3 JS module. 2.000 bài + redirect.

### Hoãn sang RUN

`single-video.php`, `single-longform.php`, `category-media.php`, `category-vietnam-buddhism.php`, `author.php` riêng, `page-lien-he.php` + contact form, view counter, `search-suggest`, `reading-progress`, `share`, `photo-panel-slider`, `lazy-embed`, `/dev/styleguide`, taxonomy template riêng, news sitemap.

**Danh sách này là cam kết hai chiều:** không có món nào trong đây được kéo vào 3 ngày, và không có món nào bị lặng lẽ bỏ luôn. RUN là "stabilization + deferred delivery", không phải "chỉ vận hành".

---

## 11. Lịch 3 ngày

### Ngày 1 — hạ tầng + skeleton

- Terraform apply: Lightsail, static IP, firewall, S3, IAM, SES
- LEMP + PHP 8.3 + MariaDB + Redis, tuning theo ngân sách RAM mục 5.8
- WordPress core, 4 plugin, `wp-config.php` hardening
- Cloudflare: DNS zone, proxy ON, SSL Full (strict) + origin cert, 2 Cache Rule
- Theme skeleton + design token hoàn chỉnh + typography self-host
- CI/CD pipeline chạy được *(mục 12)*
- Import thử 50 bài → chốt schema

### Ngày 2 — trang chủ + template chính + import

- `front-page.php` đủ 11 block + query deterministic
- `single.php` + `category.php`
- 3 JS module
- FastCGI cache + mu-plugin purge
- **Full import 2.000 bài + xử lý ảnh** (chạy nền, chưa switch DNS)

### Ngày 3 — hoàn thiện + verify + go-live

- `archive.php`, `search.php`, `404.php`, `page.php`
- Responsive pass 2 viewport, accessibility fix (mục 2.4)
- Sinh + nạp `redirects.map`
- Backup + **test restore thật**
- Chạy toàn bộ gate mục 13
- Switch DNS Cloudflare → live

**Thời gian rất kín.** 6–9 person-day cho scope này chỉ thành lập nếu D0 đã xong và không có món nào ở mục 10 bị kéo vào.

---

## 12. CI/CD — một môi trường

Một môi trường làm CI/CD trở nên tầm thường: không promotion, không environment matrix, không approval gate.

Nó **bắt buộc** vì theme có bước build SCSS/JS. Không có CI thì hoặc commit asset đã compile (2 engineer sẽ conflict liên tục), hoặc mỗi người build tay rồi upload. Trong 3 ngày, vòng deploy chạy vài chục lần/ngày — hoàn vốn trong buổi sáng đầu.

GitHub Actions, trigger push `main`:

```
lint   → php -l trên file PHP đã đổi; SCSS/JS build phải pass
build  → compile asset (content hash trong tên file), tạo artifact
deploy → rsync wp-content/themes/pgds/ qua SSH
purge  → theo thứ tự dưới
```

**Gate cứng:** build fail → không deploy. `php -l` + SCSS compile đã chặn phần lớn thứ làm trắng trang, không cần test suite mới có giá trị.

**Chỉ deploy theme.** Không WP core, không DB, không plugin. Rollback = `git revert` + push, ~60 giây. Blast radius giới hạn trong 1 thư mục — đó là điều làm single-environment an toàn được.

### 12.1 Thứ tự purge sau deploy — bắt buộc

```
1. rsync theme
2. reload php-fpm        (reset opcache)
3. flush FastCGI cache
4. purge Cloudflare      — chỉ khi asset đổi
```

**Origin trước, edge sau.** Purge edge trước thì nó fetch lại nội dung stale từ origin rồi cache tiếp — tự tạo cache poisoning nhẹ.

Bước 4 gần như không cần nếu asset có hash trong tên. Không có bước `wp transient delete` vì không dùng transient (mục 4.4).

### 12.2 SSH

Key riêng cho deploy, để trong GitHub Secrets. Key-only auth + fail2ban. **Không** whitelist IP range GitHub Actions — dải quá rộng và thay đổi liên tục; key-only là đủ.

---

## 13. Go / No-Go gate

Chạy hết trước khi switch DNS.

**Data**
- [ ] Post count đúng, không trùng (kiểm bằng `_pgds_source_id` unique)
- [ ] Category mapping đúng trên mẫu 50 bài
- [ ] Media fail rate < 2%, danh sách fail đã review
- [ ] `redirects.map` test 20 URL cũ → 301 đúng đích

**Cache — sửa là thấy ngay**
- [ ] `curl -sI https://site/ | grep X-Cache` → `HIT` ở request thứ 2
- [ ] Sửa tiêu đề 1 bài trong admin → `curl -s https://site/ | grep "tiêu đề mới"` thấy **ngay**, không đợi
- [ ] Đăng nhập admin → mọi trang `X-Cache: BYPASS`
- [ ] `curl -sI https://site/assets/dist/main.<hash>.css | grep cf-cache-status` → `HIT`
- [ ] Trang có `?preview=` không bao giờ được cache

**Chức năng**
- [ ] Trang chủ, category, article, page, search, 404 render đúng ở 360px + 1280px
- [ ] Mobile menu mở/đóng được bằng keyboard
- [ ] Dropdown nav truy cập được bằng Tab
- [ ] Video facade click → play
- [ ] Có đúng 1 `<h1>` và 1 `<main>` mỗi trang

**Hạ tầng**
- [ ] Restore từ snapshot đã test thật, RTO đo được
- [ ] Origin không truy cập được trực tiếp bằng IP
- [ ] SES gửi được mail test
- [ ] Rich Results Test: không schema duplicate

**Phi kỹ thuật**
- [ ] Thông tin pháp lý/toà soạn ở footer có approval của stakeholder — **cần chuyên gia pháp lý/compliance Việt Nam xác nhận, không phải quyết định kỹ thuật**
- [ ] Có người chịu trách nhiệm rollback + trigger rollback đã định nghĩa

**Nếu gate không đạt:** launch với 30–50 bài curated, hoặc dời ngày. **Không** cố ép launch 2.000 bài.

---

## 14. Handover

- `RUNBOOK.md`: deploy, rollback, purge cache, restore (kèm **RTO đo thật** ở Ngày 3), resize instance, YouTube API quota, exit plan (export WXR + media **trước** khi destroy hạ tầng), ngày dự kiến decommission và người chịu trách nhiệm
- `README.md`: local setup, build asset, chạy import
- Bảng mapping category nguồn → đích
- Danh sách media import fail
- Tài khoản + credential trong password manager, không trong repo
- Training editor 1 giờ: đăng bài, set `_pgds_sapo` / featured / photo story, gắn video, hiểu "sửa là thấy ngay"

---

## 15. Rủi ro còn lại

| Rủi ro | Mức | Xử lý |
|---|---|---|
| D0 chưa xong mà vẫn bắt đầu đếm 3 ngày | **Cao** | Gate cứng, không thương lượng |
| Data nguồn bẩn hơn dự kiến | **Cao** | Dry-run ngưỡng 2%; nếu vượt → launch 30–50 bài curated |
| Xử lý ảnh đẩy instance vào swap | Trung bình | `nice` + giảm `pm.max_children`; tạm nâng 4GB |
| Trang chưa có thiết kế (article/category) không được nghiệm thu | Trung bình | Duyệt wireframe ngay Ngày 1, không đợi Ngày 3 |
| SPOF một instance | Trung bình | **Chấp nhận tường minh.** RTO 30–60 phút từ snapshot |
| Origin Cache Control (Cloudflare Free không tắt được) tương tác lạ | Thấp | Không cache HTML ở edge → vô hiệu hoá rủi ro này |
| YouTube API quota / đổi điều khoản | Thấp | Fallback dùng meta đã lưu; kiểm điều khoản ở D0 |

---

*Tài liệu này bao phủ tầng ứng dụng: theme, data model, cache, migration, CI/CD, scope, gate. Chi phí AWS chi tiết và cấu hình hạ tầng nằm ở tài liệu hạ tầng riêng.*
