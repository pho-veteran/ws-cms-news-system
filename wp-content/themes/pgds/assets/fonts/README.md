# Fonts (self-hosted)

The theme self-hosts its two families so production has no third-party font origin
and LCP stays under our control (proposal §3.1.2). Google Fonts publishes each family
as **one file per subset**, and there is no combined file to request, so this
directory holds one file per family, weight, and subset:

```
be-vietnam-pro-{400,600,700}-{latin,latin-ext,vietnamese}.woff2
newsreader-{400,700}-{latin,latin-ext,vietnamese}.woff2
```

Every subset is required. `vietnamese` carries the precomposed vowels
(U+1EA0..U+1EF9) and the dong sign; the ordinary letters, digits, and punctuation
live in `latin`. Shipping only the Vietnamese subset gives you a font with no glyph
for `a`, and the browser then falls back per character: diacritics from the webfont,
plain letters from a system face, inside the same word. That reads as a broken font
rather than a missing one, which is why the failure is easy to miss.

Each file is declared with its own `unicode-range` in
`src/scss/03-elements/_fonts.scss`, so the browser downloads only the subsets a page
actually needs.

## Fetching them

```bash
npm run fonts     # from wp-content/themes/pgds
```

`tools/fetch-fonts.mjs` requests the Google Fonts CSS with a browser User-Agent
(required — the default UA yields `ttf`, not `woff2`), identifies each subset by
marker codepoints in its `unicode-range`, and verifies the `wOF2` signature so an
HTML error page can never be saved under a `.woff2` name.

Newsreader is a **variable** font: one file per subset spans the whole 400–700 axis,
so the same source URL is saved under both the 400 and 700 filenames. The
`@font-face` rules declare static weights and each needs a file present, so the
duplication is deliberate.

## If the files are absent

The theme falls back to `system-ui` and `Georgia` and still renders correctly, but
the typographic identity is lost — the display face carries the brand. Treat missing
font files as a release blocker, not a cosmetic gap.

`inc/enqueue.php` preloads the latin and vietnamese subsets of weights 400 and 700
(Vietnamese body copy needs both files for one sentence) and skips the preload when a
file is unreadable.

The files are gitignored as fetched artifacts; run `npm run fonts` after a fresh
clone, and in CI before the asset build.
