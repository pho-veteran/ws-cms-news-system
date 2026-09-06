# Product — Phật giáo và Đời sống

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Readers:** Vietnamese-speaking lay Buddhists and culturally interested general readers across a wide age range. They are *assumed* to be predominantly mobile visitors, often reading on mid-range Android devices over mobile data. Some read short updates; others read long-form features.
- **Editors:** A small newsroom using WordPress administration to publish news, select the front-page lead, mark photo stories, and attach one canonical video per post. They need published edits to appear immediately for anonymous readers.

The reading context is frequently a bright mobile screen in daylight or indoors. Body text must remain comfortable at 200% zoom.

## Product Purpose

Phật giáo và Đời sống is a Vietnamese-language Buddhist electronic news publication covering Buddhist affairs, mindful living, heritage sites, media, and community good works.

It exists to make these subjects accessible through a coherent news experience. Reader success is finding, understanding, and continuing to read relevant coverage; editorial success is publishing and correcting material without stale public pages.

## Positioning

The product uses the familiar density and scanability of a Vietnamese general-news portal while concentrating on Buddhist and cultural life rather than the daily general-news cycle. The article-reading experience is the product’s primary value, not a secondary destination behind decorative presentation.

## Operating Context

- A dense, multi-block front page directs readers to category archives and article pages.
- Editors work through a WordPress 6.x classic-theme workflow.
- Content includes text articles, photography, photo stories, and posts with one canonical YouTube video.
- Production runs on AWS Lightsail with Nginx FastCGI page caching and a Redis object cache.
- The launch includes approximately 2,000 migrated posts and 25–40 GB of media, with an expected volume of roughly 300,000 page views per month.
- The service has a planned maximum lifetime of six months and requires export before decommissioning. Deliberately deferred infrastructure includes high availability and media offload.

## Capabilities and Constraints

- The repository contains a WordPress classic theme named `pgds`; it has no full-site editing, parent theme, ACF, page builder, or WordPress core checkout.
- The theme uses token-first ITCSS SCSS and vanilla ES2020 JavaScript. It must not add a JavaScript framework or `@wordpress/*` package.
- Runtime colour values are CSS custom properties. Spacing, type, radii, and breakpoints are SCSS variables; components must use the established tokens rather than hard-coded replacements.
- Required responsive breakpoints are 480px, 768px, 880px, and 1180px; the maximum content container is 1180px.
- Every image frame reserves its aspect ratio to avoid layout shift.
- A post can have only one canonical video, stored in `_pgds_youtube_id`. Video embeds use a click-to-load `youtube-nocookie.com` facade; unavailable videos are hidden and omitted from schema.
- Scheduled YouTube metadata requests must batch at most 50 IDs and preserve stored metadata when the API returns empty values.
- Content imports are idempotent through `_pgds_source_id`; a dry run with more than 2% errors must not proceed.
- Published content changes purge the origin FastCGI cache through the cache-flush mu-plugin. The edge does not cache HTML.
- All technical documentation, source code, comments, and developer-facing output must be English. Vietnamese is reserved for reader-facing copy and content.
- Production domain selection remains undecided. SES and Cloudflare configuration currently use a placeholder pending that decision.

## Brand Commitments

- The publication’s name is **Phật giáo và Đời sống**.
- The client-approved layout is binding: preserve its block order, widgets, and colour-token structure.
- Vietnamese language rendering, including correct diacritics and localized weekday and month names, is mandatory.
- The approved reference layouts are available in `docs/expected_design/` and the original proposal and approved demo are available in `docs/initial_entries/`.

## Evidence on Hand

- Product and implementation proposal: `docs/initial_entries/PROPOSAL_01_WEB_WORDPRESS.md`
- Infrastructure and cost proposal: `docs/initial_entries/PROPOSAL_02_AWS_INFRA_COST.md`
- Approved expected-layout references: `docs/expected_design/`
- Operational procedures: `RUNBOOK.md`
- Editorial training material: `docs/EDITOR_TRAINING.md`
- Existing product and design records: `PRODUCT.md` and `DESIGN.md`

Do not invent testimonials, readership claims beyond the stated estimate, editorial endorsements, external partnerships, or other proof not present in these materials.

## Product Principles

1. **Reading comes first.** Typography, hierarchy, and a stable article flow outrank decoration.
2. **Editorial truth reaches readers quickly.** Corrections and updates must invalidate origin-cached pages reliably.
3. **Respect the approved editorial structure.** Preserve the client’s information architecture and only refine within its documented constraints.
4. **Make every route usable inclusively.** Vietnamese text, responsive behavior, keyboard access, and assistive-technology semantics are product requirements.
5. **Fit the project’s finite lifetime.** Prefer maintainable, proportionate solutions over infrastructure and features that do not serve the scheduled six-month operation.

## Accessibility & Inclusion

- Meet the project’s documented keyboard and screen-reader requirements: one `<main>` and one contextually correct `<h1>` per route; managed focus and ARIA state for navigation and media tabs; and a visible focus indicator.
- Interactive targets must meet the WCAG 2.2 24×24 CSS-pixel minimum.
- Include a functional, labeled search form and a keyboard-accessible skip link.
- Ensure Vietnamese language text and diacritics render correctly in all selected fonts.
- Preserve accessible contrast and make body reading viable at 200% zoom.
