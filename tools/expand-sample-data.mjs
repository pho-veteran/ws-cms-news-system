/**
 * Expand the 24-record sample dataset into a volume that actually exercises the
 * front page.
 *
 * Why this is needed: the front page's 11 blocks request roughly 35 distinct posts,
 * and the dedup pass (proposal §4.4) guarantees no post appears twice. With only 24
 * posts every block after the first few renders empty, so the layout cannot be
 * reviewed and the deduplication logic cannot be verified. Production imports 2,000
 * posts, where this never occurs.
 *
 * The generated records reuse the real titles, sapos, and bodies from the sample
 * file, recombined across the 13 target categories with staggered publish dates.
 * They are LOCAL FIXTURES for layout verification, not editorial content, and they
 * carry a distinct `_pgds_source_id` prefix so an accidental production import is
 * identifiable and reversible.
 *
 * Run: node tools/expand-sample-data.mjs [outfile] [count]
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SRC = join(__dirname, 'sample-data', 'data.sample.json');
const outfile = process.argv[2] || join(__dirname, 'sample-data', 'data.expanded.json');
const target = Number(process.argv[3] || 90);

const base = JSON.parse(readFileSync(SRC, 'utf8'));
const records = Array.isArray(base) ? base : base.posts || Object.values(base)[0];

// The 13 launch categories (proposal §4.1). Leaf categories are paired with their
// parent so each generated post lands in a realistic parent/child pair.
const CATEGORY_PAIRS = [
  ['tin-phat-su', 'tin-giao-hoi'],
  ['tin-phat-su', 'su-kien-le-hoi'],
  ['song-an-lanh', 'am-thuc-chay'],
  ['song-an-lanh', 'loi-song-xanh'],
  ['phat-tich', 'chua-am'],
  ['phat-tich', 'di-tich-danh-thang'],
  ['media', 'video'],
  ['media', 'infographic-emagazine'],
  ['tot-doi-dep-dao', 'nguoi-tot-viec-tot'],
  ['tot-doi-dep-dao', 'thien-nguyen'],
  ['vietnam-buddhism', null],
];

/** Deterministic PRNG so repeated runs produce an identical file. */
function rng(seed) {
  let s = (seed * 9301 + 49297) % 233280;
  return () => {
    s = (s * 9301 + 49297) % 233280;
    return s / 233280;
  };
}

const out = [...records];
const rand = rng(7);

// Publish dates walk backwards from the newest existing record so relative-time
// rendering ("3 giờ trước" vs "18/08/2026") is exercised across all its branches.
const newest = records
  .map((r) => new Date(r.published_at.replace(' ', 'T') + 'Z').getTime())
  .sort((a, b) => b - a)[0];

let cursor = newest;
let i = 0;

while (out.length < target) {
  const src = records[i % records.length];
  const [parent, child] = CATEGORY_PAIRS[i % CATEGORY_PAIRS.length];
  const gen = out.length - records.length + 1;

  // Step back between 40 minutes and ~14 hours so the set spans "minutes ago"
  // through "weeks ago".
  cursor -= Math.floor((40 + rand() * 800) * 60 * 1000);
  const published = new Date(cursor).toISOString().slice(0, 19).replace('T', ' ');

  const cats = child ? [parent, child] : [parent];

  out.push({
    ...src,
    // Prefixed so these fixtures are identifiable and removable; the importer keys
    // idempotency on _pgds_source_id (§9.2), so re-running never duplicates them.
    source_id: `fixture-${String(gen).padStart(4, '0')}`,
    title: `${src.title} (${gen})`,
    slug: `${src.slug}-fixture-${gen}`,
    primary_cat: child || parent,
    cats,
    published_at: published,
    // Only the 'video' category keeps a YouTube ID, so the facade and the duration
    // badge appear where they belong and nowhere else.
    youtube_url: child === 'video' ? src.youtube_url : '',
    old_url: '',
  });

  i++;
}

writeFileSync(outfile, JSON.stringify(out, null, 2));
console.log(`[pgds expand] ${records.length} real + ${out.length - records.length} fixtures = ${out.length} records`);
console.log(`  -> ${outfile}`);
console.log('Fixtures use the source_id prefix "fixture-"; they are local layout data only.');
