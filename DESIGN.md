# DESIGN.md — Phật giáo và Đời sống

The visual world of the `pgds` theme. The client's `Demo_layout_v12` is the approved
authority for layout, widgets, and colour; this document records that world as built,
plus the decisions that were ours to make.

## The world in one line

Vietnamese Buddhist print culture rendered for the screen: warm sutra-paper grounds,
saffron-robe browns, gilt accents, and a news serif set at editorial weight — dense
and scannable like a broadsheet, never austere.

## Mode

**Read**, with a Persuade front page. The article page is the product and is optimised
for comprehension: one column, a generous measure, clear hierarchy. The front page must
earn a click across 11 competing blocks, so it is denser and more hierarchical — but it
is still a table of contents, not a campaign.

## Colour

Warm, paper-based, low-glare. Every value is a CSS custom property emitted from
`_tokens.scss`; components never hard-code colour.

| Role | Token | Value |
|---|---|---|
| Page ground | `--pgds-paper` | `#F4EEDF` |
| Emphasised ground | `--pgds-paper-deep` | `#EAE1CC` |
| Primary text | `--pgds-ink` | `#2A211B` |
| Brand / nav | `--pgds-robe` | `#5C3A2E` |
| Dark surfaces (header strip, media block, footer) | `--pgds-robe-deep` | `#452A21` |
| Accent | `--pgds-gilt` | `#A9812F` |
| Accent on dark | `--pgds-gilt-light` / `--pgds-gilt-bright` | `#C9A24E` / `#E8C468` |
| Section / badge green | `--pgds-moss` | `#3F5D4E` |
| Rules and borders | `--pgds-line` | `#D9CDAE` |

Two decisions the proposal left open (§3.1.1):

- **`--lotus` (`#E3A8AA`) is retained** as a reserve accent, unused in production
  components. It is the only cool-warm counterpoint in the palette and is worth keeping
  available for a future badge or tag treatment.
- **`--pgds-text-muted` was raised from `#8a7f68` to `#7A6E56`** so metadata clears
  4.5:1 on the paper ground. The near-duplicate pairs were kept distinct rather than
  merged: they read as intentional steps of a secondary-text ramp.

Category identity comes from a per-section colour (`$cat-colors`), applied to the
category label only — not to whole cards, which would fragment the page.

## Typography

Two families, self-hosted as `.woff2` subset to Vietnamese, `font-display: swap`, with
the two critical files preloaded.

- **Display — Newsreader** (400/700). Chosen over the proposal's Fraunces: Fraunces is
  now heavily used across AI-generated interfaces and reads as a default, and its
  small-caps lack Vietnamese diacritics, which mangled the footer wordmark. Newsreader
  was drawn for news reading, carries the full Vietnamese subset, and has more
  editorial authority at headline sizes. **Fonts were explicitly ours to choose; layout
  and tokens were not.**
- **Body — Be Vietnam Pro** (400/500/600/700). A Vietnamese-first sans by a Vietnamese
  foundry; correct diacritic design rather than diacritics bolted onto a Latin face.

The nine-step scale from §3.1.2 is unchanged (`44 / 20 / 19 / 16 / 15 / 14 / 13 / 12 /
11.5px`), with `13px` as the default. Do not introduce sizes outside it.

Browser-owned surfaces are themed rather than left to the UA: selection, caret,
form accent, and scrollbar all draw from the palette, and dates, ranks, and calendar
numerals use `tabular-nums` so columns align.

## Layout

Container 1180px, 20px gutters. Breakpoints 480 / 768 / 880 / 1180. The main pattern is
`1fr + 320px` (content + sidebar), collapsing to one column below 880px.

The front page follows the client's 11 blocks in order: feature grid (lead + 3
secondary + photo panel) → SVG divider → dark media block with tabs → content grid 1 +
popular/calendar sidebar → rule → three-category row → content grid 2 + Vietnam
Buddhism + teachings sidebar → four-column footer.

Two robustness rules the demo did not need but production does:

- **Card grids adapt their column count to the content they actually have.** A fixed
  `repeat(3, 1fr)` holding one card reserved two empty columns and stretched one image
  across a third of the page.
- **A section with no content is not rendered at all** — a heading plus a "Xem thêm"
  link above empty space reads as a fault, not a section.

## Imagery

Photography leads. Every frame is `.pgds-art` with a fixed aspect-ratio class so
nothing shifts as images load: `16/10` for leads and cards, `16/11` for media thumbs,
`4/3` for minis, `1/1` for compact lists, `16/9` for video.

Absent an image, the frame shows a faint centred lotus mark drawn with a CSS mask, so
it reads as an intentional empty state rather than a broken image.

## Icons

One authored set on a 24×24 grid, 1.75 stroke, round caps and joins, inlined from
`inc/icons.php` and coloured by `currentColor`. Emoji and typographic glyphs are not
icons: they render in the reader's system emoji font, ignore `currentColor`, size
inconsistently, and are announced as words by screen readers. The 🎧 in the teachings
list and the `›` in "Xem thêm" were replaced accordingly.

## Motion

Restrained. Short `.15s`–`.2s` ease-out transitions on hover and focus for links,
chips, tabs, and the search field; the skip link slides in on focus. Nothing animates
on scroll or page load — on a news page that is noise, and it delays reading. All
motion is disabled under `prefers-reduced-motion`.

## Interaction and accessibility floor

- One `<main>` and one contextually correct `<h1>` per route. On the front page the
  `<h1>` is the site name, visually hidden, because no single story owns the page.
- A decorative image link that duplicates an adjacent title link is
  `aria-hidden="true" tabindex="-1"`, so screen readers hear one link, not two.
- Interactive targets clear 24×24 (WCAG 2.2). The demo's 22px tabs and chips were
  enlarged.
- Visible 2px focus ring on every focusable element; the skip link is the first tab
  stop.
- The search form has a label, a `name`, and a real submit control.
- The mobile nav toggle swaps its icon from its own `aria-expanded` state, so the glyph
  cannot disagree with what assistive tech is told.

## Deliberate deviations from the demo

| Change | Why |
|---|---|
| Fraunces → Newsreader | Overused face; its small-caps lack Vietnamese diacritics. Fonts were ours to pick. |
| Blockquote side-bar → pull quote with hanging quote mark | A 3px accent border-left is the most recognisable AI-slop tell. The display face plus hairline rules do the work. |
| Tabs/chips padding raised | The demo's 5px vertical padding produced 22px targets, under the WCAG 2.2 minimum. |
| Media block head wraps | At 360px the title plus three tabs overflowed and caused horizontal page scroll. |
| Header search widened to 250px | The added submit control was clipping the placeholder. |
| Secondary-text colour raised | Original failed 4.5:1 on the paper ground. |

The photo panel's 3px gilt `border-left` is **kept** despite the detector flagging it:
it is the client's own treatment for the "Tin ảnh" widget, and the widgets are pinned.

## Do not

- Hard-code a colour, spacing value, radius, or font size instead of using a token.
- Add a font size outside the nine-step scale.
- Use emoji or a typographic glyph where an icon belongs.
- Introduce a JS framework or an `@wordpress/*` package.
- Hand-edit `assets/dist/**` — it is generated, content-hashed output.
