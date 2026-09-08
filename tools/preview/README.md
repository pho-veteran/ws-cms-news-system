# Development preview dataset

This directory contains the deterministic, development-only content corpus used to review the PGDS WordPress CMS after the editorial-surface migration. It is never production content.

## Dataset contract

- Exactly 180 published `post` records: 20 for each canonical editorial primary category.
- Article: `tin-phat-su`, `song-an-lanh`, `am-thuc-chay`, `loi-song-xanh`, `phat-tich`, and `tot-doi-dep-dao`.
- Specialized surfaces: `emagazine`, `video`, and `vietnam-buddhism`.
- `media` and `song-an-lanh` remain assigned as parent categories where appropriate; `media` is never primary.
- Article fixtures contain 500–900 words with category-specific reporting context, at least three semantic sections, a pull quote, a list, a source and an explicit display author.
- E-magazine fixtures contain at least 800 words, chapter-style sections, a pull quote, an inline image and a two-image gallery.
- Video fixtures intentionally contain only 80–180 words: the valid YouTube ID, synchronized title/duration metadata and CMS-managed poster are the primary content. The poster is also the featured image.
- Vietnam Buddhism is English-only across title, sapo, body, source, author and its dedicated English Media Library records.
- Forty-eight non-Video articles contain a native Gutenberg inline image.
- Four articles occupy the homepage Featured positions and six Article fixtures are Photo stories.
- Exactly eight published `pgds_teaching` records with at least 150 words, a practical exercise and a featured image.
- Thirty-five attributed Media Library records backed by 25 checked-in fixtures. Ten records provide English metadata for Vietnam Buddhism while reusing the same licensed source files.

Stable article IDs use `preview-2026-<primary-category>-01` through `-20`. Teaching IDs use `teaching-01` through `teaching-08` in `_pgds_preview_teaching_id`.

## Files

- `generate-dataset.mjs`: source definitions and deterministic generator for article records and media relationships.
- `preview-content.json`: generated article import dataset.
- `preview-media.json`: attributed media catalog and generated featured/inline/gallery/poster relationships.
- `preview-teachings.json`: the eight teaching fixtures.
- `reset.sh`: removes local articles, teachings and non-logo attachments while preserving users, settings, pages, taxonomy, navigation and the lunar fallback.
- `seed.sh`: imports the complete corpus into WordPress.
- `verify.sh`: verifies identity, category distribution, classification, body richness, editable media relationships, Video metadata, curation, teaching content and representative frontend routes.

The checked-in JPEGs are development fixtures, not newsroom media. Preserve the attribution recorded in `preview-media.json` and `media/ATTRIBUTION.json`.

## Regenerate after editing source definitions

From the repository root:

```bash
node tools/preview/generate-dataset.mjs
```

The command rewrites `preview-content.json` and the assignment section of `preview-media.json`.

## Replace local editorial content

Run the canonical local setup once, then replace only the disposable editorial corpus:

```bash
cd infra/local
docker compose up -d
./sync.sh
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-scripts/setup.sh'
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/preview/reset.sh'
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/preview/seed.sh'
docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/preview/verify.sh'
```

`reset.sh` is intentionally destructive only to the disposable local article, teaching and non-logo media corpus. It does not delete static pages, users, options, categories, navigation or the lunar-calendar fallback.
