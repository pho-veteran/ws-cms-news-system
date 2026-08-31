/**
 * Generate placeholder photography (PNG) for the local verification stack.
 *
 * The sample dataset ships with `featured_image_url: ""` for every record, so every
 * card renders the empty-state frame. A photo-led news layout cannot be judged that
 * way: hierarchy, contrast of text over images, caption legibility, and `srcset`
 * behaviour all depend on real image content being present.
 *
 * PNG rather than SVG, deliberately:
 *  - WordPress blocks SVG uploads for unprivileged users, so SVG fixtures would not
 *    represent how real uploads behave.
 *  - Registered image sizes and `srcset` are only generated for raster images, so
 *    SVG would silently skip the entire responsive-image path this theme relies on.
 *
 * No raster encoder (cwebp/magick/sharp) exists in this environment, so the PNG is
 * encoded here from scratch with zlib -- the only dependency is Node's stdlib.
 *
 * These are ABSTRACT COMPOSITIONS in the theme palette. They do not depict Buddhist
 * subjects, people, or places. They exist to exercise the layout locally, are not
 * editorial assets, and must never reach production: real photography is the
 * newsroom's to supply.
 *
 * Run: node tools/seed-images.mjs [outdir] [count]
 */

import { deflateSync } from 'node:zlib';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const outdir = process.argv[2] || '/tmp/pgds-seed-images';
const count = Number(process.argv[3] || 24);
const W = 1280;
const H = 800;

// ---------------------------------------------------------------------------
// Minimal PNG encoder (truecolour, 8-bit, no alpha).
// ---------------------------------------------------------------------------

const CRC_TABLE = (() => {
  const t = new Int32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c;
  }
  return t;
})();

function crc32(buf) {
  let c = -1;
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ -1) >>> 0;
}

function chunk(type, data) {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length);
  const typeBuf = Buffer.from(type, 'latin1');
  const body = Buffer.concat([typeBuf, data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(body));
  return Buffer.concat([len, body, crc]);
}

/** @param {Uint8Array} rgb Packed RGB, W*H*3 bytes. */
function encodePng(rgb, width, height) {
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(width, 0);
  ihdr.writeUInt32BE(height, 4);
  ihdr[8] = 8; // bit depth
  ihdr[9] = 2; // colour type 2 = truecolour RGB
  // 10..12 = compression, filter, interlace: all 0.

  // Each scanline is prefixed with its filter byte (0 = None).
  const stride = width * 3;
  const raw = Buffer.alloc((stride + 1) * height);
  for (let y = 0; y < height; y++) {
    raw[y * (stride + 1)] = 0;
    Buffer.from(rgb.buffer, rgb.byteOffset + y * stride, stride).copy(
      raw,
      y * (stride + 1) + 1
    );
  }

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

// ---------------------------------------------------------------------------
// Composition
// ---------------------------------------------------------------------------

// Palette drawn from _tokens.scss so seeded images sit inside the design world
// rather than fighting it.
const PALETTES = [
  ['#5C3A2E', '#A9812F', '#EAE1CC'], // robe / gilt / paper-deep
  ['#452A21', '#C9A24E', '#F4EEDF'], // robe-deep / gilt-light / paper
  ['#3F5D4E', '#C9A24E', '#EFE6CF'], // moss / gilt-light / surface-3
  ['#7A5230', '#E8C468', '#EAE1CC'], // warm brown / gilt-bright / paper-deep
  ['#2F5D7A', '#C9A24E', '#EFE6CF'], // vietnam-buddhism blue / gilt / surface-3
];

const hex = (h) => [
  parseInt(h.slice(1, 3), 16),
  parseInt(h.slice(3, 5), 16),
  parseInt(h.slice(5, 7), 16),
];

const mix = (a, b, t) => [
  Math.round(a[0] + (b[0] - a[0]) * t),
  Math.round(a[1] + (b[1] - a[1]) * t),
  Math.round(a[2] + (b[2] - a[2]) * t),
];

/** Deterministic pseudo-random so re-runs produce byte-identical files. */
function rng(seed) {
  let s = (seed * 9301 + 49297) % 233280;
  return () => {
    s = (s * 9301 + 49297) % 233280;
    return s / 233280;
  };
}

/**
 * Abstract composition: a graded sky, a horizon, soft arcs, and a vertical rhythm
 * suggesting architecture -- enough structure to test text-over-image contrast and
 * focal placement without depicting a subject.
 */
function compose(index) {
  const rand = rng(index + 1);
  const [darkHex, accentHex, lightHex] = PALETTES[index % PALETTES.length];
  const dark = hex(darkHex);
  const accent = hex(accentHex);
  const light = hex(lightHex);

  const horizon = Math.round(H * (0.55 + rand() * 0.18));
  const sunX = Math.round(W * (0.55 + rand() * 0.3));
  const sunY = Math.round(horizon * (0.32 + rand() * 0.25));
  const sunR = Math.round(H * (0.07 + rand() * 0.05));

  const arcs = Array.from({ length: 2 + Math.floor(rand() * 3) }, () => ({
    cx: W * (0.15 + rand() * 0.7),
    cy: horizon,
    r: H * (0.2 + rand() * 0.3),
    op: 0.1 + rand() * 0.18,
  }));

  const barCount = 3 + Math.floor(rand() * 5);
  const bars = Array.from({ length: barCount }, (_, i) => {
    const h = H * (0.18 + rand() * 0.34);
    return {
      x0: (W / (barCount + 1)) * (i + 1) - 15,
      x1: (W / (barCount + 1)) * (i + 1) + 15,
      top: horizon - h,
      op: 0.16 + rand() * 0.2,
    };
  });

  const rgb = new Uint8Array(W * H * 3);

  for (let y = 0; y < H; y++) {
    for (let x = 0; x < W; x++) {
      let px;

      if (y < horizon) {
        // Sky: light at the top easing into the accent at the horizon.
        px = mix(light, accent, (y / horizon) * 0.62);

        // Sun/moon disc with a soft edge.
        const d = Math.hypot(x - sunX, y - sunY);
        if (d < sunR * 1.5) {
          const t = Math.max(0, 1 - Math.max(0, d - sunR) / (sunR * 0.5));
          px = mix(px, light, Math.min(1, t) * 0.85);
        }

        for (const a of arcs) {
          const d2 = Math.hypot(x - a.cx, y - a.cy);
          if (d2 < a.r) px = mix(px, accent, a.op);
        }

        for (const b of bars) {
          if (x >= b.x0 && x <= b.x1 && y >= b.top) px = mix(px, dark, b.op);
        }
      } else {
        // Ground: a solid graded mass, dark enough that overlaid white captions
        // clear 4.5:1 wherever a gradient scrim sits on top of it.
        px = mix(mix(dark, accent, 0.12), dark, (y - horizon) / (H - horizon));
      }

      const o = (y * W + x) * 3;
      rgb[o] = px[0];
      rgb[o + 1] = px[1];
      rgb[o + 2] = px[2];
    }
  }

  return encodePng(rgb, W, H);
}

mkdirSync(outdir, { recursive: true });

let total = 0;
for (let i = 0; i < count; i++) {
  const name = `pgds-seed-${String(i + 1).padStart(2, '0')}.png`;
  const png = compose(i);
  writeFileSync(join(outdir, name), png);
  total += png.length;
}

console.log(
  `[pgds seed-images] ${count} PNG files (${(total / 1024 / 1024).toFixed(1)} MB) -> ${outdir}`
);
console.log('Local layout fixtures, NOT editorial assets. Do not ship them.');
