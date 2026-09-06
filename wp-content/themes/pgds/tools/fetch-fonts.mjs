/**
 * Fetch the self-hosted .woff2 font files the theme expects in assets/fonts/.
 *
 * The theme self-hosts fonts (proposal §3.1.2) to remove a third-party origin and
 * control LCP. Google Fonts splits each family into per-subset files, and there is
 * no combined file to request: the `vietnamese` subset carries the precomposed
 * Vietnamese vowels and the dong sign, while ordinary letters, digits, and
 * punctuation live in `latin`. So every subset a Vietnamese news site renders has
 * to be downloaded, and each has to be declared with its own `unicode-range`.
 *
 * Saving only the Vietnamese subset (as this script previously did) produces a
 * font that has no glyph for "a": the browser silently falls back per character,
 * so Latin text renders in a system face while diacritics come from the webfont,
 * and the result looks broken in exactly the way a missing font does not.
 *
 * The emitted filenames are `<family>-<weight>-<subset>.woff2`, which is what the
 * @font-face rules in src/scss/03-elements/_fonts.scss reference.
 *
 * Newsreader ships as a VARIABLE font: one file per subset spans the whole 400..700
 * axis, so the same URL is saved under both the 400 and 700 filenames the SCSS
 * references. That is intentional, not a copy/paste error -- the @font-face rules
 * declare static weights and each needs a file.
 *
 * Run: npm run fonts   (from wp-content/themes/pgds)
 */

import { mkdirSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const FONT_DIR = join(__dirname, '..', 'assets', 'fonts');

// A browser UA is required: with curl's default UA, Google Fonts returns ttf.
const UA =
  'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

const CSS_URL =
  'https://fonts.googleapis.com/css2' +
  '?family=Be+Vietnam+Pro:wght@400;600;700' +
  '&family=Newsreader:opsz,wght@6..72,400;6..72,700' +
  '&display=swap';

/*
 * Subsets are identified by codepoints that appear in exactly one of them:
 *   vietnamese  U+1EA0..U+1EF9 (precomposed vowels) and U+20AB (dong sign)
 *   latin-ext   U+1E00..U+1E9F, without the Vietnamese markers
 *   latin       U+0000..U+00FF (basic Latin: the letters, digits, punctuation)
 * Order matters: the checks run most specific first.
 */
const SUBSETS = [
  { name: 'vietnamese', match: (r) => r.includes('U+1EA0') || r.includes('U+20AB') },
  { name: 'latin-ext', match: (r) => r.includes('U+1E00') },
  { name: 'latin', match: (r) => r.includes('U+0000-00FF') },
];

/** Parse the Google Fonts CSS into { family, weight, unicodeRange, url } records. */
function parseFaces(css) {
  const faces = [];
  const blocks = css.split('@font-face').slice(1);
  for (const block of blocks) {
    const family = /font-family:\s*'([^']+)'/.exec(block)?.[1];
    const weight = /font-weight:\s*(?:[\d.]+\s+)?(\d+)/.exec(block)?.[1];
    const unicodeRange = /unicode-range:\s*([^;]+);/.exec(block)?.[1] ?? '';
    const url = /url\((https:\/\/[^)]+\.woff2)\)/.exec(block)?.[1];
    if (family && weight && url) {
      faces.push({ family, weight: Number(weight), unicodeRange, url });
    }
  }
  return faces;
}

/** Pick the URL for one family/weight/subset. */
function pick(faces, family, weight, subset) {
  const forFamily = faces.filter((f) => f.family === family);
  if (forFamily.length === 0) {
    throw new Error(`No @font-face blocks found for "${family}".`);
  }

  // Variable fonts (Newsreader) report a single weight covering the whole axis, so an
  // exact weight match can legitimately be absent. Fall back to any weight of the
  // family rather than failing -- the variable file renders every weight correctly.
  const exact = forFamily.filter((f) => f.weight === weight);
  const candidates = exact.length > 0 ? exact : forFamily;

  const rule = SUBSETS.find((s) => s.name === subset);
  const hit = candidates.find((f) => rule.match(f.unicodeRange));
  if (!hit) {
    throw new Error(`"${family}" ${weight} offers no "${subset}" subset.`);
  }
  return hit.url;
}

const FAMILIES = [
  { slug: 'be-vietnam-pro', family: 'Be Vietnam Pro', weights: [400, 600, 700] },
  { slug: 'newsreader', family: 'Newsreader', weights: [400, 700] },
];

const cssRes = await fetch(CSS_URL, { headers: { 'User-Agent': UA } });
if (!cssRes.ok) {
  throw new Error(`Google Fonts CSS request failed: ${cssRes.status} ${cssRes.statusText}`);
}
const css = await cssRes.text();
const faces = parseFaces(css);
if (faces.length === 0) {
  throw new Error('Could not parse any @font-face blocks from the Google Fonts response.');
}

mkdirSync(FONT_DIR, { recursive: true });

let written = 0;
for (const { slug, family, weights } of FAMILIES) {
  for (const weight of weights) {
    for (const { name: subset } of SUBSETS) {
      const file = `${slug}-${weight}-${subset}.woff2`;
      const url = pick(faces, family, weight, subset);
      const res = await fetch(url, { headers: { 'User-Agent': UA } });
      if (!res.ok) {
        throw new Error(`Download failed for ${file}: ${res.status} ${res.statusText}`);
      }
      const buf = Buffer.from(await res.arrayBuffer());

      // Guard against saving an HTML error page under a .woff2 name. wOF2 files start
      // with the ASCII signature "wOF2"; anything else means the response was not a font.
      if (buf.subarray(0, 4).toString('latin1') !== 'wOF2') {
        throw new Error(`${file} is not a woff2 file (bad signature). Aborting.`);
      }

      writeFileSync(join(FONT_DIR, file), buf);
      written++;
      const kb = (buf.length / 1024).toFixed(1);
      console.log(`  ${file.padEnd(36)} ${String(kb).padStart(6)} KB`);
    }
  }
}

console.log(`\n[pgds fonts] ${written} files written to assets/fonts/`);
// The Latin subset is the one that renders ordinary text; its absence is the failure
// mode that looks like a broken font rather than a missing one.
if (!existsSync(join(FONT_DIR, 'be-vietnam-pro-400-latin.woff2'))) {
  throw new Error('Expected font files are missing after the run.');
}
