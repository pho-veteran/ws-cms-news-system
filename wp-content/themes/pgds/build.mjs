/**
 * Asset build for the pgds theme.
 *
 * Why we don't use @wordpress/scripts (decided by the BOM proposal):
 *  - The cache strategy (proposal §5.5) requires filenames WITH a content hash + immutable.
 *    @wordpress/scripts busts cache via a version query string through *.asset.php,
 *    and does NOT change the filename -> can't safely use `Cache-Control: immutable`.
 *  - The theme's JS is vanilla ES2020 and imports NO @wordpress/* packages,
 *    so the main benefit of @wordpress/scripts (dependency extraction) = 0.
 *  => We use sass (dart-sass) + esbuild, emitting filenames with [contenthash] +
 *     a manifest.json for PHP to read at enqueue time. Lighter, and verified to actually run.
 *
 * Output:
 *   assets/dist/main.<hash>.css
 *   assets/dist/app.<hash>.js
 *   assets/dist/manifest.json   { "main.css": "main.<hash>.css", "app.js": "app.<hash>.js" }
 */

import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as sass from 'sass';
import { build as esbuild, context as esContext } from 'esbuild';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SRC = join(__dirname, 'src');
const DIST = join(__dirname, 'assets', 'dist');
const SCSS_ENTRY = join(SRC, 'scss', 'main.scss');
const JS_ENTRY = join(SRC, 'js', 'index.js');

const args = process.argv.slice(2);
const WATCH = args.includes('--watch');
const CLEAN_ONLY = args.includes('--clean');

const hash8 = (buf) => createHash('sha256').update(buf).digest('hex').slice(0, 8);

function cleanDist() {
  if (existsSync(DIST)) {
    for (const f of readdirSync(DIST)) rmSync(join(DIST, f), { force: true });
  } else {
    mkdirSync(DIST, { recursive: true });
  }
}

function writeHashed(baseName, ext, content) {
  const h = hash8(content);
  const fileName = `${baseName}.${h}.${ext}`;
  writeFileSync(join(DIST, fileName), content);
  return fileName;
}

function buildScss(dev = false) {
  const result = sass.compile(SCSS_ENTRY, {
    style: dev ? 'expanded' : 'compressed',
    loadPaths: [join(SRC, 'scss')],
    quietDeps: true,
  });
  return writeHashed('main', 'css', result.css);
}

async function buildJs(dev = false) {
  const result = await esbuild({
    entryPoints: [JS_ENTRY],
    bundle: true,
    format: 'iife',
    target: ['es2020'],
    minify: !dev,
    sourcemap: false,
    write: false,
    legalComments: 'none',
  });
  const out = result.outputFiles[0].text;
  return writeHashed('app', 'js', out);
}

async function buildOnce(dev = false) {
  mkdirSync(DIST, { recursive: true });
  cleanDist();
  const cssFile = buildScss(dev);
  const jsFile = await buildJs(dev);
  const manifest = { 'main.css': cssFile, 'app.js': jsFile };
  writeFileSync(join(DIST, 'manifest.json'), JSON.stringify(manifest, null, 2));
  const stamp = new Date().toISOString();
  console.log(`[pgds build] ${stamp}`);
  console.log(`  CSS -> assets/dist/${cssFile}`);
  console.log(`  JS  -> assets/dist/${jsFile}`);
  console.log(`  manifest -> assets/dist/manifest.json`);
}

if (CLEAN_ONLY) {
  cleanDist();
  console.log('[pgds build] dist cleaned');
  process.exit(0);
}

if (WATCH) {
  // Watch mode: sass has no built-in polling for SCSS -> watch manually via fs.
  const { watch } = await import('node:fs');
  const jsCtx = await esContext({
    entryPoints: [JS_ENTRY],
    bundle: true,
    format: 'iife',
    target: ['es2020'],
    write: false,
  });
  let building = false;
  const rebuild = async (why) => {
    if (building) return;
    building = true;
    try {
      await buildOnce(true);
      console.log(`  (rebuild: ${why})`);
    } catch (e) {
      console.error('[pgds build] error:', e.message);
    } finally {
      building = false;
    }
  };
  await rebuild('initial');
  watch(join(SRC, 'scss'), { recursive: true }, () => rebuild('scss'));
  watch(join(SRC, 'js'), { recursive: true }, () => rebuild('js'));
  console.log('[pgds build] watching src/scss and src/js ... Ctrl+C to stop');
  await jsCtx.dispose;
} else {
  await buildOnce(false);
}
