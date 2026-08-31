# Fonts (self-hosted)

The theme self-hosts its two families so production has no third-party font origin
and LCP stays under our control (proposal §3.1.2). These `.woff2` files, subset to
`vietnamese`, belong in this directory:

```
be-vietnam-pro-400.woff2
be-vietnam-pro-600.woff2
be-vietnam-pro-700.woff2
newsreader-400.woff2
newsreader-700.woff2
```

## Fetching them

```bash
npm run fonts     # from wp-content/themes/pgds
```

`tools/fetch-fonts.mjs` requests the Google Fonts CSS with a browser User-Agent
(required — the default UA yields `ttf`, not `woff2`), then picks the file whose
`unicode-range` covers Vietnamese for each family and weight.

Newsreader is a **variable** font: one file per subset spans the whole 400–700 axis,
so the same source URL is saved under both `newsreader-400.woff2` and
`newsreader-700.woff2`. The `@font-face` rules declare static weights and each needs
a file present, so the duplication is deliberate.

## If the files are absent

The theme falls back to `system-ui` and `Georgia` and still renders correctly, but
the typographic identity is lost — the display face carries the brand. Treat missing
font files as a release blocker, not a cosmetic gap.

`@font-face` is declared in `src/scss/03-elements/_fonts.scss`. `inc/enqueue.php`
preloads the two critical files and skips the preload when a file is unreadable.

The files are gitignored as fetched artifacts; run `npm run fonts` after a fresh
clone, and in CI before the asset build.
