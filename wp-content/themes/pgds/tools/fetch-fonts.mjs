/**
 * Fetch the self-hosted .woff2 font files the theme expects in assets/fonts/.
 *
 * The theme self-hosts fonts (proposal §3.1.2) to remove a third-party origin and
 * control LCP. Google Fonts splits each family into per-subset files, so this
 * script requests the CSS with a browser User-Agent (which yields woff2), then
 * picks the file whose `unicode-range` covers Vietnamese for each family/weight.
 *
 * Newsreader ships as a VARIABLE font: one file per subset spans the whole 400..700
 * axis, so the same URL is saved under both the 400 and 700 filenames the SCSS
 * references. That is intentional, not a copy/paste error -- the @font-face rules
 * in src/scss/03-elements/_fonts.scss declare static weights and each needs a file.
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

// The Vietnamese subset is identifiable by these codepoints, which appear in its
// unicode-range and in no other Latin subset: U+1EA0..U+1EF9 (precomposed
// Vietnamese vowels) and U+20AB (the dong sign).
const VIETNAMESE_MARKERS = ['U+1EA0', 'U+20AB'];

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

function isVietnamese(face) {
  return VIETNAMESE_MARKERS.some((m) => face.unicodeRange.includes(m));
}

/** Pick the Vietnamese-subset URL for one family/weight. */
function pick(faces, family, weight) {
  const forFamily = faces.filter((f) => f.family === family);
  if (forFamily.length === 0) {
    throw new Error(`No @font-face blocks found for "${family}".`);
  }

  // Variable fonts (Newsreader) report a single weight covering the whole axis, so an
  // exact weight match can legitimately be absent. Fall back to any weight of the
  // family rather than failing -- the variable file renders every weight correctly.
  const exact = forFamily.filter((f) => f.weight === weight);
  const candidates = exact.length > 0 ? exact : forFamily;

  const viet = candidates.find(isVietnamese);
  if (viet) return { url: viet.url, subset: 'vietnamese' };

  // No Vietnamese subset for this family: fall back to the widest Latin subset so
  // the family still loads. Report it so the omission is visible, not silent.
  return { url: candidates[0].url, subset: 'latin (no Vietnamese subset offered)' };
}

const TARGETS = [
  { file: 'be-vietnam-pro-400.woff2', family: 'Be Vietnam Pro', weight: 400 },
  { file: 'be-vietnam-pro-600.woff2', family: 'Be Vietnam Pro', weight: 600 },
  { file: 'be-vietnam-pro-700.woff2', family: 'Be Vietnam Pro', weight: 700 },
  { file: 'newsreader-400.woff2', family: 'Newsreader', weight: 400 },
  { file: 'newsreader-700.woff2', family: 'Newsreader', weight: 700 },
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

for (const target of TARGETS) {
  const { url, subset } = pick(faces, target.family, target.weight);
  const res = await fetch(url, { headers: { 'User-Agent': UA } });
  if (!res.ok) {
    throw new Error(`Download failed for ${target.file}: ${res.status} ${res.statusText}`);
  }
  const buf = Buffer.from(await res.arrayBuffer());

  // Guard against saving an HTML error page under a .woff2 name. wOF2 files start
  // with the ASCII signature "wOF2"; anything else means the response was not a font.
  if (buf.subarray(0, 4).toString('latin1') !== 'wOF2') {
    throw new Error(`${target.file} is not a woff2 file (bad signature). Aborting.`);
  }

  writeFileSync(join(FONT_DIR, target.file), buf);
  const kb = (buf.length / 1024).toFixed(1);
  console.log(`  ${target.file.padEnd(28)} ${String(kb).padStart(6)} KB  [${subset}]`);
}

console.log(`\n[pgds fonts] ${TARGETS.length} files written to assets/fonts/`);
if (!existsSync(join(FONT_DIR, 'be-vietnam-pro-400.woff2'))) {
  throw new Error('Expected font files are missing after the run.');
}
