/**
 * Build asset cho theme pgds.
 *
 * Vi sao khong dung @wordpress/scripts (BOM proposal chot no):
 *  - Cache strategy (proposal §5.5) yeu cau filename CO content hash + immutable.
 *    @wordpress/scripts bust cache bang version query-string qua *.asset.php,
 *    KHONG doi filename -> khong dung duoc `Cache-Control: immutable` an toan.
 *  - JS cua theme la vanilla ES2020, KHONG import package @wordpress/* nao,
 *    nen loi ich chinh cua @wordpress/scripts (dependency extraction) = 0.
 *  => Dung sass (dart-sass) + esbuild, xuat filename co [contenthash] +
 *     manifest.json de PHP doc luc enqueue. Nhe hon, verify chay duoc that.
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
  // Watch mode: SCSS qua polling cua sass khong co san -> tu watch bang fs.
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
  console.log('[pgds build] watching src/scss and src/js ... Ctrl+C de dung');
  await jsCtx.dispose;
} else {
  await buildOnce(false);
}
