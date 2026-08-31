# PRODUCT.md — Phật giáo và Đời sống

Durable product context for design work. Derived from the two proposals in
`docs/initial_entries/` and the client's approved layout
`Demo_layout_v12___Phật_giáo_và_Đời_sống.html`. Assumptions are labelled.

## What it is

A Vietnamese-language Buddhist news site — a **chuyên trang tin điện tử** covering
Buddhist affairs, mindful living, heritage sites, media, and community good works.
Structurally it is a mainstream news portal in the VnExpress mould: a dense
multi-block front page, category archives, and article pages. Editorially it is
narrower and slower than general news — the subject is a religious and cultural
tradition, not the day's events.

Platform: WordPress 6.x classic theme (`pgds`), PHP 8.3, MariaDB, Redis object cache,
Nginx FastCGI page cache. No FSE, no page builder, no parent theme.

## Who it serves

- **Readers** — Vietnamese-speaking lay Buddhists and culturally interested general
  readers, spanning a wide age range. Mobile-majority traffic, often on mid-range
  Android over mobile data. Many read in short sessions; some read long features.
- **Editors** — a small newsroom publishing through the WordPress admin. They set the
  front-page lead, mark photo stories, and attach one canonical video per post. They
  need edits to appear immediately, which the cache design guarantees.

The reading scene matters for design decisions: a bright screen in daylight, a warm
paper-toned palette, and body text that survives 200% zoom.

## Scale and lifetime

- 2,000 migrated posts at launch; 25–40 GB of media.
- ~300k page views/month expected (≈0.12 req/s average, 5–10 req/s peak).
- **Ephemeral: maximum 6 months.** There is a scheduled decommission date and a
  mandatory export-before-destroy exit plan. This shapes what is worth building —
  precise cache purging, HA, and media offload are all deliberately deferred.
- Infrastructure budget: ~USD 85 gross for the whole lifetime.

## What matters most

1. **Editorial changes are visible immediately** to anonymous readers. Solved by
   explicit cache purges, not short TTLs.
2. **Reading is the product.** Article typography, measure, and hierarchy outrank
   decoration.
3. **No layout shift.** Every image frame reserves its aspect ratio; the logo and
   content images declare dimensions.
4. **Accessible by keyboard and screen reader.** One `<main>` and one contextually
   correct `<h1>` per page; dropdowns, mobile nav, and media tabs manage focus and
   expose ARIA state.
5. **Vietnamese renders correctly** — including diacritics in the display face, and
   Vietnamese weekday and month names regardless of the WordPress locale.

## Constraints that bind design

- **The client's layout is approved and pinned.** Its block order, widgets, and colour
  tokens are given. Fonts and finer craft decisions are ours to choose.
- Four breakpoints: 480, 768, 880, 1180px. Container max 1180px.
- Colour is CSS custom properties (runtime themeable); spacing, type, radius, and
  breakpoints are SCSS variables. Components must not hard-code either.
- Vanilla ES2020, three JS modules only. No framework, no `@wordpress/*` packages.
- Vietnamese appears only in reader-facing copy. All code, comments, and docs are
  English.

## Deliberately out of scope at launch

Dedicated `author.php`, `single-video.php`, `single-longform.php`, per-category
templates, contact form, view counter, search suggestions, reading progress, share
buttons, photo-panel slider, and a news sitemap. These are RUN-phase commitments, not
silent drops.

## Assumptions

- *Assumed:* traffic is mobile-majority. The proposals do not state a device split;
  this follows from the Vietnamese consumer-news market.
- *Assumed:* the reading scene is daylight/indoor on a bright screen, which is why the
  palette stays light rather than offering a dark theme.
- *Not yet decided by the client:* the production domain. SES and Cloudflare are
  configured against a placeholder until one exists.
