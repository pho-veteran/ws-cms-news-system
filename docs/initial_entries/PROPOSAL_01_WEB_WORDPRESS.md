# PROPOSAL 01 — WordPress "Phật giáo và Đời sống"

> **Scope:** Greenfield. No existing code will be reused.
> **Staffing:** 1–2 engineers. **Timeline:** 3 days (6–9 person-days).
> **System lifetime:** ephemeral, maximum 6 months.

---

## 1. TL;DR

WordPress 6.x + PHP 8.3 + MariaDB 10.11 + Redis, running on a single Lightsail 2GB instance. The theme is a **standalone custom theme `pgds`** (classic PHP templates, no FSE, no parent theme). Gutenberg is used only for post content.

Two cache layers: Redis object cache + Nginx FastCGI page cache. Cloudflare caches static assets only; it **does not cache HTML**. Media resides on the instance disk, with no S3 offload.

The migration of 2,000 posts completes on Day 2, is verified on Day 3, then DNS is switched last.

**Prerequisites (D0, before the clock starts):** the dataset of 2,000 posts has been validated, all media files/URLs have been checked, and the five BOM decisions have been finalized. Without D0, a 3-day schedule is not viable.

---

## 2. Interface scope

The website uses one unified design system for the front page and all content templates. The launch cycle must implement:

- A front page with 11 primary content areas, header, navigation, and footer.
- `single`, `category`, `archive`, `author`, `search`, `404`, and static pages.
- JavaScript behavior for mobile navigation, the YouTube facade, and media tabs.
- Responsive behavior at 480px, 768px, 880px, and 1180px.
- Responsive images via `<picture>`/`srcset`, explicit image dimensions, and placeholders that do not cause layout shift.
- Semantic HTML, keyboard navigation, and ARIA for every interactive component.

### 2.1 Navigation (7 items)

```
1. Trang chủ
2. Tin Phật sự      → Tin Giáo hội, Sự kiện – Lễ hội
3. Sống an lành     → Ẩm thực chay, Lối sống xanh
4. Phật tích        → Chùa – Am, Di tích – Danh thắng
5. Media            → Video, Infographic – Emagazine
6. Tốt đời – đẹp đạo → Người tốt việc tốt, Thiện nguyện
7. Vietnam Buddhism
```

### 2.2 Front-page areas

1. Header + logo
2. Seven-item navigation
3. Feature grid: 1 lead + 3 secondary cards + 1 "Tin ảnh" panel
4. SVG divider
5. Media block (dark): 3 tabs + 1 featured item + 4 thumbnails + 3 bullets
6. Content grid 1: "Tin Phật sự" 3 cards + 7 list items
7. Sidebar: "Đọc nhiều nhất" (5 items) + "Lịch Vạn Niên"
8. Divider
9. Three-category block: 3 columns, each with 1 feature + 2 mini items
10. Content grid 2: 5 mixed posts + "Vietnam Buddhism" (3 posts) + "Lời Phật dạy" sidebar (4 items)
11. Four-column footer

### 2.3 Semantic and accessibility standards

- Each page has exactly one `<main>` and one contextually appropriate `<h1>`.
- Search is a complete `<form>` with a label, `name`, and submit control.
- Dropdowns and mobile navigation are usable by keyboard, manage focus, and expose ARIA state.
- "Xem thêm" and every navigable card title use `<a>`.
- The logo and content images declare `width`/`height` to prevent CLS.
- Text clamping must not hide content at 200% zoom.
- Legal content and editorial-office information must be approved before go-live.

---

## 3. Theme `pgds`

**Final decision: standalone custom theme.** No child theme, GeneratePress, or parent theme. Rationale: the required structure is already a complete theme; adding a parent only adds CSS that must be overridden and an external dependency.

### 3.1 Design tokens — complete

A complete production token inventory.

#### 3.1.1 Color

All production colors use the tokens below; components must not use hard-coded colors.

```css
:root{
  /* ── Brand ─────────────────────────────────────────────────── */
  --pgds-ink:         #2A211B;  /* primary text */
  --pgds-paper:       #F4EEDF;  /* page background */
  --pgds-paper-deep:  #EAE1CC;  /* emphasized-area background */
  --pgds-robe:        #5C3A2E;  /* saffron-robes brown */
  --pgds-robe-deep:   #452A21;  /* dark brown — header, media block */
  --pgds-gilt:        #A9812F;  /* accent gold */
  --pgds-gilt-light:  #C9A24E;  /* light gold — links, hover */
  --pgds-moss:        #3F5D4E;  /* moss green — badges */
  --pgds-line:        #D9CDAE;  /* border */

  /* ── Surface ───────────────────────────────────────────────── */
  --pgds-white:       #fff;
  --pgds-surface-1:   #fefdfa;  /* brightest card background */
  --pgds-surface-2:   #faf8f2;  /* secondary card background */
  --pgds-surface-3:   #EFE6CF;  /* sidebar area */

  /* ── Secondary text ─────────────────────────────────────────── */
  --pgds-text-muted:  #8a7f68;  /* metadata, dates */
  --pgds-text-soft:   #6b5f4a;  /* description */
  --pgds-text-dim:    #9B8F79;  /* most subdued text */
  --pgds-text-alt:    #5c5243;  /* secondary text on light backgrounds */

  /* ── Accent on dark backgrounds ─────────────────────────────── */
  --pgds-gilt-bright: #E8C468;  /* media-block accent */
  --pgds-gilt-pale:   #E4D6AE;
  --pgds-line-dark:   #5a4234;  /* border on dark backgrounds */
  --pgds-line-soft:   #C4B79B;  /* subtle border */
  --pgds-line-mid:    #8C7E68;

  /* ── Alpha ─────────────────────────────────────────────────── */
  --pgds-shadow-sm:   rgba(42,33,27,.15);
  --pgds-shadow-robe: rgba(92,58,46,.12);
  --pgds-shadow-flat: rgba(0,0,0,.10);
  --pgds-overlay:     rgba(0,0,0,.72);   /* image gradient */
  --pgds-overlay-hi:  rgba(0,0,0,.88);
  --pgds-on-dark-35:  rgba(255,255,255,.35);
  --pgds-on-dark-25:  rgba(255,255,255,.25);
  --pgds-on-dark-22:  rgba(255,255,255,.22);
}
```

Two decisions must be finalized on D1:
- **`--lotus: #E3A8AA`** is declared but unused. Make a final decision to retain it as a reserve accent or remove it.
- The similar color pairs (`#8a7f68` / `#8C7E68`, `#6b5f4a` / `#5c5243`) may be unintended variants. Decide whether to merge or retain them separately during normalization.

#### 3.1.2 Typography

```scss
// Font family
$font-body:    'Be Vietnam Pro', system-ui, sans-serif;  // 400/500/600/700
$font-display: 'Fraunces', Georgia, serif;               // opsz 9..144, 400/700

// Scale — 9 levels
$fs-hero:   44px;   // front-page lead title
$fs-xl:     20px;
$fs-lg:     19px;   // section heading
$fs-md:     16px;
$fs-base:   15px;   // body text
$fs-sm:     14px;
$fs-xs:     13px;   // DEFAULT, used most often
$fs-2xs:    12px;   // metadata
$fs-3xs:    11.5px; // labels, badges

// Line-height
$lh-tight:  1.35;   // card title
$lh-snug:   1.4;
$lh-normal: 1.45;
$lh-relax:  1.55;   // body text
$lh-loose:  1.6;
$lh-none:   1;      // badge, number

// Letter-spacing
$ls-wide:   .04em;
$ls-wider:  .06em;
$ls-widest: .08em;  // uppercase labels
```

Do not add font sizes outside the scale; intermediate values must be consolidated into the nearest level.

Production **self-hosts `.woff2`**, uses `font-display: swap`, and preloads the two critical files to eliminate a third-party font origin and control LCP.

#### 3.1.3 Spacing & layout

```scss
// Container
$container-max: 1180px;

// 4px-step spacing scale
$sp-1:  4px;
$sp-2:  8px;
$sp-3:  12px;
$sp-4:  16px;
$sp-5:  20px;
$sp-6:  24px;
$sp-7:  28px;   // default spacing between sections
$sp-8:  32px;
$sp-10: 40px;
$sp-12: 48px;
```

Do not add spacing outside the scale; round intermediate values to the nearest step.

#### 3.1.4 Radius & shadow

```scss
$radius-xs:   2px;
$radius-sm:   4px;
$radius-md:   6px;    // default for cards
$radius-lg:   8px;
$radius-xl:   10px;
$radius-2xl:  14px;   // large panels
$radius-full: 50%;    // avatars, dots

$shadow-sm: 0 1px 3px var(--pgds-shadow-flat);
$shadow-md: 0 2px 8px var(--pgds-shadow-sm);
$shadow-lg: 0 4px 16px var(--pgds-shadow-robe);
```

Do not add radii outside the scale; consolidate intermediate values into the nearest level.

#### 3.1.5 Breakpoints

Production uses four breakpoints:

```scss
$bp-sm: 480px;
$bp-md: 768px;
$bp-lg: 880px;
$bp-xl: 1180px;  // matches $container-max
```

Every template must be tested at all four thresholds, not just the front page.

#### 3.1.6 Z-index

Use a fixed z-index scale to avoid conflicts with the admin bar and plugins:

```scss
$z-base:     1;
$z-dropdown: 100;
$z-sticky:   200;
$z-overlay:  900;
$z-modal:    1000;
```

#### 3.1.7 Acceptance criteria

Accept via visual regression at a minimum of 360px and 1280px. Tokens are a means; visual consistency, no overflow, and no layout shift are the goals.

### 3.2 CSS

SCSS, ITCSS layers (`settings → tools → generic → elements → objects → components → utilities`), BEM-lite (`.pgds-card`, `.pgds-card__title`, `.pgds-card--lead`). Build to one CSS file with a content hash in its name.

Color tokens are **CSS custom properties** (runtime, allowing future theme changes). Spacing/typography/radius/breakpoint tokens are **SCSS variables** (compile-time; runtime changes are unnecessary).

### 3.3 Structure

```
wp-content/themes/pgds/
├── style.css
├── functions.php
├── inc/
│   ├── setup.php          # theme support, navigation menus, image sizes
│   ├── enqueue.php        # assets + hash versioning
│   ├── cpt-tax.php        # CPT + taxonomy
│   ├── meta-fields.php    # custom fields + meta boxes
│   ├── query-blocks.php   # queries for 11 front-page blocks + deduplication
│   ├── template-tags.php
│   ├── seo-schema.php     # VideoObject + NewsMediaOrganization only
│   └── admin-ux.php
├── template-parts/
│   ├── card-lead.php  card-secondary.php  card-mini.php
│   ├── list-item.php  sidebar-popular.php  sidebar-lunar.php
│   └── video-facade.php
├── front-page.php
├── single.php
├── category.php
├── archive.php          # shared by tag, taxonomy, author, date
├── search.php
├── 404.php
├── page.php
├── header.php  footer.php  sidebar.php
└── assets/{scss,js,fonts,dist}
```

**Not included at launch:** `single-video.php`, `single-longform.php`, `category-media.php`, `category-vietnam-buddhism.php`, `author.php`, `page-lien-he.php`, `taxonomy-pgds_topic.php`. All fall back to `single.php` / `category.php` / `archive.php`. See section 10.

### 3.4 JavaScript

Vanilla ES2020, no framework. **Build tool: `@wordpress/scripts`** (final decision, not Vite—it already handles WordPress asset versioning and dependencies).

Launch has only three modules:

| Module | Why it is required |
|---|---|
| `nav-mobile` | Navigation must be usable on narrow screens and by keyboard. |
| `youtube-facade` | Avoid loading iframes before interaction and protect LCP on pages with video. |
| `media-tabs` | Controls content groups in the media block. |

**Deferred:** `search-suggest`, `reading-progress`, `share`, `photo-panel-slider`, `lazy-embed`.

All three launch modules must support keyboard interaction, manage focus, and expose correct ARIA state.

---

## 4. Data model

### 4.1 Categories (matching the navigation)

```
tin-phat-su/{tin-giao-hoi, su-kien-le-hoi}
song-an-lanh/{am-thuc-chay, loi-song-xanh}
phat-tich/{chua-am, di-tich-danh-thang}
media/{video, infographic-emagazine}
tot-doi-dep-dao/{nguoi-tot-viec-tot, thien-nguyen}
vietnam-buddhism
```

### 4.2 CPT & taxonomy

| | Slug | Notes |
|---|---|---|
| CPT | `pgds_teaching` | "Lời Phật dạy" — sidebar block #11 |
| CPT | `pgds_lunar_note` | "Lịch Vạn Niên" — sidebar block #8 |
| Taxonomy | `pgds_topic` | topic that cuts across categories |

Do not create `pgds_author_role` at launch—use built-in WordPress roles.

### 4.3 Post meta

| Key | Type | Used in |
|---|---|---|
| `_pgds_sapo` | text | card + article lead |
| `_pgds_primary_cat` | int (term_id) | breadcrumb, schema, canonical category |
| `_pgds_youtube_id` | string | **1 canonical video/post** — see 6.1 |
| `_pgds_youtube_dur` | int (seconds) | duration badge |
| `_pgds_is_featured` | bool | front-page lead slot |
| `_pgds_feature_rank` | int | slot order |
| `_pgds_photo_story` | bool | "Tin ảnh" panel |
| `_pgds_source` | text | news source |

Remove `_pgds_reading_time`—calculate it at runtime from the word count; storage is unnecessary.

### 4.4 Queries for 11 blocks — deterministic, no transients

The front page has 11 content areas; a post must not appear twice. Approach:

1. **Curated slots first:** lead, featured, and photo panel use `_pgds_is_featured` + `_pgds_feature_rank` (editor-controlled).
2. **Then query categories in batches**, excluding IDs already used.
3. **Deterministic fallback** for an empty curated slot: the newest post in the corresponding category.
4. **Explicit deduplication order**, documented in `inc/query-blocks.php`: curated slots → media block → content grid 1 → three-category block → content grid 2 → sidebar.

**Do not use transient caching per block.** The entire front page is already FastCGI-cached; adding an inner cache is redundant, and `$used_ids` shared across blocks with separate TTLs creates inconsistent state. On a cache MISS, ~11 `WP_Query` calls with Redis object cache take approximately 50–150ms—once after each purge, which is acceptable.

---

## 5. Cache — prioritize “edits are visible immediately”

### 5.1 Principle

**A person editing a post always sees changes immediately**, because the `wordpress_logged_in_` cookie bypasses every layer. The “immediately reflected” requirement applies only to **anonymous visitors**—and is solved through explicit purges, not short TTLs.

### 5.2 Two layers, not three

| Layer | Cached content | Invalidation |
|---|---|---|
| Redis object cache | queries, options, metadata | WordPress core handles it; no manual purge |
| Nginx FastCGI | anonymous HTML | flush when content changes |
| Cloudflare | **static assets only** | hashed filenames |

**Cloudflare does not cache HTML.** This is intentional:
- Eliminates the stale window at the edge → once the origin is purged, the next request is fresh.
- Reduces two purge targets to one, eliminating “origin first, edge second” ordering.
- Eliminates interaction with Origin Cache Control (which cannot be disabled on the Free plan).
- The Free plan only reliably offers Purge Everything + Purge by URL → do not depend on precise edge purging.

Cost: the origin receives all HTML requests. At 300k PV/month, this is ≈ 0.12 req/s on average, with a peak of ~5–10 req/s. FastCGI serves from disk. This is negligible for 2 vCPUs.

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

Notes:
- **TTL is 12h**, not 1h. Because all changes are explicitly purged, TTL is no longer a correctness mechanism—it is only a hit-rate mechanism.
- `fastcgi_cache_lock` prevents a thundering herd: only one PHP render per URL despite many concurrent requests.
- **Do not use** `use_stale updating` / `background_update`. After a complete flush, there is no old version to serve; these directives only matter for natural expiration.
- **Do not skip every query string.** Social URLs with `utm_*` must be cached. Skip only `s=` and `preview=`.
- **Cache sitemaps**; do not skip them—they are expensive to generate.
- **Keep the cache on disk, not tmpfs.** tmpfs directly consumes the RAM of the 2GB instance.

### 5.4 Purge: complete flush, no path mapping

The complicated approach is to infer every path affected when one post is edited: the post URL, front page, categories, tags, author, feeds, sitemap, and monthly archives. It is easy to miss one, which becomes a “why is the edit still not visible?” bug.

At this traffic level, **flush everything** is correct: one command, nothing missed, no mapping logic.

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

- No `ngx_cache_purge` module is needed (it requires a custom Nginx build)—deleting files directly is sufficient.
- **Requirement:** php-fpm must be able to write to the cache directory. Put Nginx and php-fpm in the same group, with group-write access to the directory.
- Hook only `save_post`, not autosaves/revisions.
- **When to move to precise purging:** above ~500k–1M PV/month. Do not build it before then.

### 5.5 Browser cache — an easy omission

If a visitor’s browser caches HTML, a server purge is meaningless to that visitor.

```nginx
location / {
    add_header Cache-Control "no-cache" always;
}
location ~* \.(css|js|woff2|webp|jpg|jpeg|png|svg)$ {
    add_header Cache-Control "public, max-age=31536000, immutable";
}
```

`no-cache` means “cache but must revalidate,” not “do not cache”—with ETag, a 304 is very lightweight. Static assets use 1 year + `immutable`; change content by changing the filename.

### 5.6 Cloudflare — 2 Cache Rules (10 available on Free)

```
Rule 1  bypass : URI path contains /wp-admin/, /wp-login.php, /wp-json/,
                 /wp-cron.php, /xmlrpc.php          → Bypass cache
Rule 2  static : extension css|js|woff2|webp|jpg|png|svg
                 → Eligible for cache, Edge TTL 1 month
```

No cookie-based bypass rule is needed because HTML is not cached at the edge. Use 2/10 slots.

### 5.7 Redis

Drop-in `object-cache.php`. `maxmemory 160mb`, `maxmemory-policy allkeys-lru`. Object cache only; no HTML involvement.

### 5.8 RAM budget — the cache must fit a 2GB machine

Cache is useful only if it does not push the machine into swap. On a 2GB instance, budget first, then select the PHP worker count:

| Component | RAM |
|---|---|
| OS + nginx | ~250 MB |
| MariaDB (`innodb_buffer_pool_size=256M`) | ~400 MB |
| Redis (`maxmemory 160mb`) | ~160 MB |
| PHP-FPM: `pm=ondemand`, `pm.max_children=6` × ~60MB | ~360 MB |
| **Total** | **~1.17 GB** |
| **Remaining for OS page cache** | **~850 MB** |

- **`pm.max_children=6`** is the starting point; tune from real observations. Increase only when logs show `max_children reached`.
- **2GB swap** is insurance against OOM, not capacity. If swap is used regularly, the configuration is wrong.
- **Keep FastCGI cache on disk, NOT tmpfs.** tmpfs directly consumes 2GB RAM; let the OS page cache manage hot content.
- During image imports: reduce `pm.max_children` to 2 and run `nice -n 19`.

---

## 6. YouTube

### 6.1 Data model — final decision

**1 canonical video/post** is stored in `_pgds_youtube_id` + `_pgds_youtube_dur`. This video is used for the duration badge on cards, thumbnail, `VideoObject` schema, and video sitemap.

Secondary videos in the post body use normal embed blocks, are **not** stored in metadata, and are **not** included in schema. Rationale: `_pgds_youtube_id` is single metadata and cannot model multiple videos without creating ambiguity over which one is canonical.

### 6.2 Required facade

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

Click → inject a `youtube-nocookie.com` iframe with `autoplay=1`. Store thumbnails locally (on the instance disk); do not hotlink.

### 6.3 Required fallback definition

| State | Behavior |
|---|---|
| Video private / removed / age-restricted | Hide the facade, show the text "Video không còn khả dụng", and **do not** emit `VideoObject` |
| API failure / quota exhausted | Use last-saved metadata; do not overwrite it with empty values |
| No local thumbnail yet | Use a correctly proportioned gradient placeholder; do not cause layout shift |

### 6.4 API synchronization

Run a daily cron job to fetch `duration` + `title` for posts with `_pgds_youtube_id`. YouTube Data API v3 quota is calculated by **units/endpoint**, not “10,000 requests/day”—`videos.list` costs 1 unit/call and batches up to 50 IDs/call. State this clearly in the runbook.

Review YouTube terms covering thumbnail download and storage before implementation.

### 6.5 Performance expectations

Replacing direct iframes with a facade is a broadly proven improvement, but **a specific figure is a measurement target, not a commitment**. Measure before/after with Lighthouse on the actual video page and report real numbers.

---

## 7. SEO — finalize output ownership

**Plugin: The SEO Framework** (final decision, not Rank Math—lighter, less admin bloat, sufficient for a news site).

Divide ownership to prevent duplicates:

| Output | Owner |
|---|---|
| `<title>`, meta description | Plugin |
| Canonical | Plugin |
| Open Graph, Twitter Card | Plugin |
| `NewsArticle`, `BreadcrumbList`, `WebSite` | Plugin |
| Primary XML sitemap | Plugin |
| **`VideoObject`** | **Theme** (`inc/seo-schema.php`) |
| **`NewsMediaOrganization`** | **Theme** |
| **Video sitemap** | **Theme** |
| News sitemap | Plugin if available; otherwise defer |

**Required gate:** run Rich Results Test on one regular post + one video post and confirm that **no schema is emitted twice**.

---

## 8. Bill of materials — finalize all five

A 3-day build cannot leave decisions open.

| Item | Final decision | Exclude |
|---|---|---|
| SEO | The SEO Framework | Rank Math |
| Page cache | **Nginx FastCGI** (manual configuration, no plugin) | W3 Total Cache |
| Media offload | **Do not use** — media on disk | WP Offload Media, Media Cloud |
| Custom fields | **Custom meta boxes** (`inc/meta-fields.php`) | ACF |
| Build tool | `@wordpress/scripts` | Vite |

Final plugin list: The SEO Framework, Redis Object Cache, WP Mail SMTP (SES), Two Factor. **Four plugins.** Everything else is theme code.

Exclude ACF because: 8 simple fields, an added dependency + a paid Pro edition + difficult data migration. Manual meta boxes take ~2h.

---

## 9. Migration of 2,000 posts

### 9.1 D0 — prerequisites

**Do not start counting the 3 days** until D0 is complete:

- [ ] Complete source dump/export of 2,000 posts, readable by a script
- [ ] Source-category → 13 target-category mapping (an explicit table)
- [ ] Every media URL returns HTTP 200; total size known (expected 25–40 GB)
- [ ] Actual throughput measured: import 50 posts + process images, then extrapolate time for 2,000

### 9.2 Script

WP-CLI command, **idempotent** (re-running does not create duplicates—keyed by `_pgds_source_id`):

```bash
wp pgds import --file=data.json --batch=200 --dry-run
wp pgds import --file=data.json --batch=200
wp pgds media-variants --regenerate
wp pgds build-redirects --out=/etc/nginx/redirects.map
```

Stop threshold: dry run failure rate > 2% means **stop; do not import**.

### 9.3 Images

Generate variants + WebP. **Run `nice -n 19` and reduce `pm.max_children` to 2 during execution**—image processing is most likely to push the 2GB instance into swap.

If throughput measured on D0 proves too slow: temporarily upgrade to a 4GB instance on the import day (~$0.40/day), then downgrade afterward.

Note: Lightsail resize is **not** in-place. The process is snapshot → create a new instance with the larger bundle → detach/attach the static IP → verify → cut over → delete the old instance. There is brief downtime during the IP change; therefore do this only after a snapshot exists and before the DNS switch.

### 9.4 Redirects

Generate `redirects.map` for Nginx (`map` directive); do not use a redirect plugin—2,000 rules in the database burden every request.

```nginx
map $request_uri $pgds_redirect { include /etc/nginx/redirects.map; default ""; }
if ($pgds_redirect != "") { return 301 $pgds_redirect; }
```

### 9.5 One environment — the pre-cutover window is the rehearsal

A separate staging instance is unnecessary. The site is not live until Cloudflare DNS points to it. Therefore all of Days 1–3 are the rehearsal environment, at **$0** additional cost.

After go-live, every deployment is a production deployment.

---

## 10. Scope — what is cut and what remains

### Included at launch

Front page (11 blocks), `single.php`, `category.php`, `archive.php` (shared by tag/taxonomy/author/date), `search.php`, `404.php`, `page.php`, header, footer, sidebar. Three JS modules. 2,000 posts + redirects.

### Deferred to RUN

`single-video.php`, `single-longform.php`, `category-media.php`, `category-vietnam-buddhism.php`, a dedicated `author.php`, `page-lien-he.php` + contact form, view counter, `search-suggest`, `reading-progress`, `share`, `photo-panel-slider`, `lazy-embed`, `/dev/styleguide`, a dedicated taxonomy template, news sitemap.

**This list is a two-way commitment:** nothing in it may be pulled into the three days, and nothing may be silently dropped altogether. RUN means “stabilization + deferred delivery,” not “operations only.”

---

## 11. Three-day schedule

### Day 1 — infrastructure + skeleton

- Terraform apply: Lightsail, static IP, firewall, S3, IAM, SES
- LEMP + PHP 8.3 + MariaDB + Redis, tuned to the RAM budget in section 5.8
- WordPress core, 4 plugins, `wp-config.php` hardening
- Cloudflare: DNS zone, proxy ON, SSL Full (strict) + origin certificate, 2 Cache Rules
- Theme skeleton + complete design tokens + self-hosted typography
- Working CI/CD pipeline *(section 12)*
- Trial import of 50 posts → finalize the schema

### Day 2 — front page + primary templates + import

- Complete `front-page.php` with all 11 blocks + deterministic queries
- `single.php` + `category.php`
- Three JS modules
- FastCGI cache + mu-plugin purge
- **Full import of 2,000 posts + image processing** (runs in background; DNS not switched yet)

### Day 3 — complete + verify + go-live

- `archive.php`, `search.php`, `404.php`, `page.php`
- Responsive pass at 2 viewports, accessibility fixes (section 2.4)
- Generate + load `redirects.map`
- Backup + **real restore test**
- Run every gate in section 13
- Switch Cloudflare DNS → live

**The timeline is extremely tight.** 6–9 person-days for this scope are viable only if D0 is complete and nothing in section 10 is pulled in.

---

## 12. CI/CD — one environment

One environment makes CI/CD straightforward: no promotion, no environment matrix, no approval gate.

It is **mandatory** because the theme has SCSS/JS build steps. Without CI, either compiled assets are committed (two engineers will constantly conflict), or each person manually builds and uploads. In 3 days, the deployment loop runs dozens of times/day—it pays for itself on the first morning.

GitHub Actions, triggered by a push to `main`:

```
lint   → php -l on changed PHP files; SCSS/JS build must pass
build  → compile assets (content hash in filenames), create artifact
deploy → rsync wp-content/themes/pgds/ over SSH
purge  → in the order below
```

**Hard gate:** if the build fails, do not deploy. `php -l` + SCSS compilation catch most causes of a blank page; a new test suite is not needed to be valuable.

**Deploy the theme only.** Do not deploy WordPress core, database, or plugins. Rollback = `git revert` + push, ~60 seconds. The blast radius is limited to one directory—that is what makes a single environment safe.

### 12.1 Required purge order after deployment

```
1. rsync theme
2. reload php-fpm        (reset opcache)
3. flush FastCGI cache
4. purge Cloudflare      — only when assets change
```

**Origin first, edge second.** If the edge is purged first, it fetches stale content from the origin and caches it again—creating minor cache poisoning.

Step 4 is almost unnecessary if asset names contain hashes. There is no `wp transient delete` step because transients are not used (section 4.4).

### 12.2 SSH

Use a dedicated deployment key stored in GitHub Secrets. Key-only authentication + fail2ban. **Do not** allowlist GitHub Actions IP ranges—they are too broad and change continuously; key-only is sufficient.

---

## 13. Go / No-Go gate

Run all items before switching DNS.

**Data**
- [ ] Correct post count, no duplicates (verify unique `_pgds_source_id`)
- [ ] Correct category mapping in a sample of 50 posts
- [ ] Media failure rate < 2%, failure list reviewed
- [ ] `redirects.map` tested with 20 old URLs → correct 301 target

**Cache — edits are visible immediately**
- [ ] `curl -sI https://site/ | grep X-Cache` → `HIT` on the second request
- [ ] Edit one post title in admin → `curl -s https://site/ | grep "tiêu đề mới"` finds it **immediately**, without waiting
- [ ] Log in as admin → every page returns `X-Cache: BYPASS`
- [ ] `curl -sI https://site/assets/dist/main.<hash>.css | grep cf-cache-status` → `HIT`
- [ ] A page with `?preview=` is never cached

**Functionality**
- [ ] Front page, category, article, page, search, and 404 render correctly at 360px + 1280px
- [ ] Mobile menu can be opened/closed by keyboard
- [ ] Dropdown navigation is accessible with Tab
- [ ] Video facade click → play
- [ ] Exactly 1 `<h1>` and 1 `<main>` per page

**Infrastructure**
- [ ] Restore from snapshot has been tested for real; RTO measured
- [ ] Origin is not directly accessible by IP
- [ ] SES can send a test email
- [ ] Rich Results Test: no duplicate schema

**Non-technical**
- [ ] Footer legal/editorial-office information has stakeholder approval — **requires confirmation from a Vietnamese legal/compliance specialist; this is not a technical decision**
- [ ] A rollback owner and rollback trigger are defined

**If the gate fails:** launch with 30–50 curated posts, or delay the date. **Do not** force a launch with 2,000 posts.

---

## 14. Handover

- `RUNBOOK.md`: deploy, rollback, cache purge, restore (including **actual RTO measured** on Day 3), instance resize, YouTube API quota, exit plan (export WXR + media **before** infrastructure destruction), scheduled decommission date, and responsible owner
- `README.md`: local setup, asset build, run the import
- Source-category → target-category mapping table
- List of failed media imports
- Accounts + credentials in a password manager, not in the repository
- One-hour editor training: publish a post, set `_pgds_sapo` / featured / photo story, attach a video, understand “edits are visible immediately”

---

## 15. Remaining risks

| Risk | Level | Mitigation |
|---|---|---|
| Start counting 3 days while D0 is incomplete | **High** | Hard gate, non-negotiable |
| Source data is dirtier than expected | **High** | 2% dry-run threshold; if exceeded → launch 30–50 curated posts |
| Image processing pushes the instance into swap | Medium | `nice` + reduce `pm.max_children`; temporarily upgrade to 4GB |
| Pages without designs (article/category) are not accepted | Medium | Approve wireframes on Day 1, do not wait for Day 3 |
| One-instance SPOF | Medium | **Explicitly accepted.** RTO 30–60 minutes from snapshot |
| Origin Cache Control (cannot be disabled on Cloudflare Free) has unexpected interaction | Low | Do not cache HTML at the edge → eliminates this risk |
| YouTube API quota / terms change | Low | Fallback uses saved metadata; review terms on D0 |

---

*This document covers the application layer: theme, data model, cache, migration, CI/CD, scope, and gates. Detailed AWS costs and infrastructure configuration are in the separate infrastructure document.*
