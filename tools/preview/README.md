# Development preview articles

This directory contains a **development-only set of 40 article fixtures** for the local WordPress stack. It is deliberately separate from production imports and contains synthetic Vietnamese editorial copy plus the checked-in photographs used by those articles.

The package is an article layer, not a second site bootstrap. Run the canonical local setup first. Site identity, header, footer, category registration, static pages, navigation, supporting post types, lunar/sidebar content, and homepage ordering remain under the canonical setup and WordPress CMS.

## Safety boundary

- Never use these fixtures for a production migration or deploy.
- Every preview article uses an `_pgds_source_id` prefixed with `preview-2026-`.
- The checked-in photographs are development fixtures, not newsroom media.
- The preview seed does not call `wp pgds yt-sync`, connect to production, or require a YouTube API key.

## CMS-managed article media

`preview-media.json` is the article-media manifest. It records provenance, attribution, license, alt text, checksum, and the featured, inline, gallery, and poster relationships belonging to the articles. The source JPEGs live in `media/`, so the fixture package is deterministic and requires no download step.

`seed.sh` imports the JPEGs as normal WordPress attachments and assigns them through native CMS fields and editor content. After import, WordPress attachment IDs, post metadata, and Gutenberg content own every relationship. Developers can replace, edit, caption, or reassign them in **Media > Library** and the post editor.

The files are reusable under the licenses recorded in the manifest and `media/ATTRIBUTION.json`, but their inclusion here does not make them real PGDS editorial media. Keep the fixtures development-only and preserve attribution if they are reused under their source licenses.

## Article fixture contract

- Exactly 40 substantive articles with stable IDs `preview-2026-0001` through `preview-2026-0040`.
- Every article has at least 300 words, two semantic sections, a pull quote, a list, and an explicit byline. Eight reference-grade fixtures (`0001`, `0002`, `0007`, `0012`, `0018`, `0023`, `0026`, and `0033`) contain approximately 600–900 words.
- Twenty-five CMS-managed Media Library assets: 21 licensed article photographs, four local YouTube poster fixtures, 39 featured-image assignments, seven native Gutenberg inline-image assignments, and one two-image gallery.
- `preview-2026-0026` deliberately has no article imagery.
- Available and unavailable video states use frozen duration, title, and CMS-managed poster metadata. Each available fixture has a checked-in local thumbnail that matches its YouTube ID rather than reusing the article's featured image. The facade creates the `youtube-nocookie.com` embed only after the visitor clicks play.
- Article-owned source, display-author, category, tag, caption, and related-content data.

The package intentionally does **not** create or replace site options, header/footer/category content, static pages, menus, menu locations, topics, teachings, lunar notes, or homepage feature/photo-story metadata.

## Seed from the repository root

```bash
cd infra/local
docker compose up -d
./sync.sh

# Establish the canonical local WordPress site first.
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-scripts/setup.sh'

# Add only the 40 preview articles and their CMS-managed media.
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/preview/seed.sh'
```

The article seed is safe to rerun: article identity is keyed by source ID and Media Library attachments use stable local asset keys. Because the generic content importer intentionally skips existing source IDs, reset the disposable local volumes when testing changes to `preview-content.json`.

For an exact article-only corpus, run the canonical setup in a fresh local stack, remove only the baseline `post` records imported from `tools/sample-data/data.sample.json`, and then run `seed.sh`. Keep baseline site identity, pages, categories, navigation, teachings, lunar/sidebar records, and all other invariant CMS content intact.

## Verify

```bash
cd infra/local
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/preview/verify.sh'
```

The verifier checks source-ID uniqueness, article richness, attributed Media Library coverage, editable featured/inline/gallery/poster relationships, the intentional no-image state, category coverage, frozen video metadata, and the available/unavailable article routes. It also rejects preview-owned homepage promotion metadata. Baseline site records and unrelated Media Library items may coexist.