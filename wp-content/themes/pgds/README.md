# Theme `pgds` — Phật giáo và Đời sống

Theme WordPress classic (không FSE, không parent) cho chuyên trang tin tức Phật giáo.
Token-first SCSS (ITCSS), vanilla JS, cache FastCGI + Redis. Xây theo `docs/PROPOSAL_01_WEB_WORDPRESS.md`.

## Yêu cầu

- WordPress 6.4+, PHP 8.1+ (khuyến nghị 8.3), MariaDB 10.11, Redis.
- Node 18+ để build asset (đã test trên Node 24).

## Cài đặt local (dev)

```bash
# 1. Đặt theme vào wp-content/themes/pgds (repo này đã mirror cấu trúc WP)
# 2. Build asset
cd wp-content/themes/pgds
npm install
npm run build          # -> assets/dist/main.<hash>.css + app.<hash>.js + manifest.json
npm run watch          # chế độ theo dõi khi phát triển

# 3. Kích hoạt theme (tự seed 13 category theo nav)
wp theme activate pgds

# 4. Menu: gán menu vào vị trí "primary" HOẶC để trống -> fallback tự dựng từ category
```

> **Bắt buộc build trước khi dùng.** `assets/dist/` bị `.gitignore`; nếu chưa build,
> theme không nạp CSS/JS (enqueue đọc `manifest.json`).

## Build tooling — vì sao không phải `@wordpress/scripts`

BOM proposal §8 chốt `@wordpress/scripts`, nhưng:
- Cache §5.5 yêu cầu **filename có content hash + immutable**; `@wordpress/scripts`
  bust cache bằng version query-string qua `*.asset.php`, không đổi filename.
- JS theme là vanilla ES2020, **không** import package `@wordpress/*` → lợi ích chính
  của `@wordpress/scripts` (dependency extraction) = 0.

→ Dùng `sass` (dart-sass) + `esbuild`, xuất filename `[contenthash]` + `manifest.json`.
Nhẹ hơn, build verify chạy được. Xem `build.mjs`. **Đây là sai lệch có chủ đích so với BOM.**

## Cấu trúc

```
inc/            module PHP (setup, enqueue, cpt-tax, meta-fields, query-blocks,
                template-tags, nav-walker, seo-schema, admin-ux, cli-import)
template-parts/ card-lead, card-secondary, card-mini, list-item,
                sidebar-popular, sidebar-lunar, video-facade
src/scss/       ITCSS: _tokens _mixins _config main + 02..06 layer
src/js/         index + modules/{nav-mobile, youtube-facade, media-tabs}
assets/dist/    build output (content hash) — KHÔNG commit
front-page.php  trang chủ 11 block
single/category/archive/search/404/page/index/sidebar.php
```

## Import 2.000 bài (WP-CLI)

```bash
wp pgds import --file=tools/sample-data/data.sample.json --batch=200 --dry-run
wp pgds import --file=tools/sample-data/data.sample.json --batch=200
wp pgds media-variants --regenerate      # chạy nice -n 19, giảm pm.max_children=2
wp pgds build-redirects --out=/etc/nginx/redirects.map
```

- Idempotent: khoá theo meta `_pgds_source_id` — chạy lại không tạo trùng.
- Ngưỡng dừng: dry-run lỗi > 2% → dừng, không import.

## Video YouTube (yêu cầu #1)

- 1 video canonical/bài: meta `_pgds_youtube_id` (tự tách ID từ URL khi lưu).
- Facade lazy: chỉ nạp iframe `youtube-nocookie.com` khi bấm play.
- Poster local kỳ vọng ở `wp-content/uploads/yt/<id>-640.webp` (cron sinh — scope RUN).

## Schema (SEO)

- Theme xuất: `VideoObject`, `NewsMediaOrganization`, video sitemap `/video-sitemap.xml`.
- Plugin SEO xuất: `NewsArticle`, `BreadcrumbList`, `WebSite`, sitemap chính.
- Không cài plugin SEO? đặt `define('PGDS_EMIT_ARTICLE_SCHEMA', true)` để theme lo NewsArticle.
- Sau khi đổi rewrite (`/video-sitemap.xml`): `wp rewrite flush`.

## Hạ tầng liên quan (ngoài theme)

- `wp-content/mu-plugins/pgds-cache-flush.php` — flush FastCGI khi nội dung đổi.
- `infra/nginx/` — server block, http snippet (cache zone), redirects.map mẫu.
- `infra/wp-config-hardening.sample.php` — hardening bổ sung.
