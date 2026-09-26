// Draws the TestaCBT website icons from the same motif as the app icon (an answer bubble with a check mark):
// favicon.ico, favicon-32.png, apple-touch-icon.png, icon-192.png, icon-512.png and a 1200x630 social preview.
//   node scripts/make-web-icons.mjs
// Replace the files in public/ with final artwork whenever you have it; the pages point at these names.
import { deflateSync } from 'node:zlib';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const out = join(dirname(fileURLToPath(import.meta.url)), '..', 'public');
mkdirSync(out, { recursive: true });

const GREEN = [10, 122, 74];
const DEEP = [8, 100, 61];
const WHITE = [255, 255, 255];

// ---- PNG encoding ----
const crcTable = Array.from({ length: 256 }, (_, n) => { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; return c >>> 0; });
const crc32 = (buf) => { let c = 0xffffffff; for (const b of buf) c = crcTable[(c ^ b) & 0xff] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };

function chunk(type, data) {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type), data]);
  const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(body));
  return Buffer.concat([len, body, crc]);
}

function png(w, h, rgba) {
  const stride = w * 4 + 1;
  const raw = Buffer.alloc(stride * h);
  for (let y = 0; y < h; y++) {
    raw[y * stride] = 0;
    rgba.copy(raw, y * stride + 1, y * w * 4, (y + 1) * w * 4);
  }
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4); ihdr[8] = 8; ihdr[9] = 6;
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0))]);
}

// ---- the motif in unit coordinates (same as the app icon) ----
const distToSegment = (px, py, ax, ay, bx, by) => {
  const dx = bx - ax, dy = by - ay;
  const t = Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy)));
  return Math.hypot(px - (ax + t * dx), py - (ay + t * dy));
};

function motif(u, v) {
  const ring = Math.abs(Math.hypot(u - 0.5, v - 0.5) - 0.30) <= 0.045;
  const w = 0.055;
  const check = distToSegment(u, v, 0.355, 0.52, 0.46, 0.625) <= w || distToSegment(u, v, 0.46, 0.625, 0.66, 0.39) <= w;
  return ring || check ? 1 : 0;
}

/** Inside a rounded square of the given corner radius (unit coordinates)? */
function insideRounded(u, v, r) {
  const x = Math.abs(u - 0.5) - (0.5 - r);
  const y = Math.abs(v - 0.5) - (0.5 - r);
  return Math.hypot(Math.max(x, 0), Math.max(y, 0)) <= r ? 1 : 0;
}

/** Renders a w x h image. `paint(x, y)` gets pixel-space coordinates and returns {color, alpha} coverage samples. */
function render(w, h, sample) {
  const rgba = Buffer.alloc(w * h * 4);
  const N = 3;
  for (let py = 0; py < h; py++) {
    for (let px = 0; px < w; px++) {
      let r = 0, g = 0, b = 0, a = 0;
      for (let sy = 0; sy < N; sy++) {
        for (let sx = 0; sx < N; sx++) {
          const s = sample(px + (sx + 0.5) / N, py + (sy + 0.5) / N);
          r += s[0] * s[3]; g += s[1] * s[3]; b += s[2] * s[3]; a += s[3];
        }
      }
      const i = (py * w + px) * 4;
      const n = N * N;
      rgba[i] = a ? Math.round(r / a) : 0;
      rgba[i + 1] = a ? Math.round(g / a) : 0;
      rgba[i + 2] = a ? Math.round(b / a) : 0;
      rgba[i + 3] = Math.round((a / n) * 255);
    }
  }
  return png(w, h, rgba);
}

/** The square app icon. `radius` 0 gives a full-bleed square (Apple rounds it itself). */
const squareIcon = (size, radius) => render(size, size, (x, y) => {
  const u = x / size, v = y / size;
  if (!insideRounded(u, v, radius || 1e-6)) return [0, 0, 0, 0];
  return motif(u, v) ? [...WHITE, 1] : [...GREEN, 1];
});

/** A 1200x630 preview card for social networks: the icon on the brand green. */
const socialCard = () => {
  const W = 1200, H = 630, S = 420, x0 = (W - S) / 2, y0 = (H - S) / 2;
  return render(W, H, (x, y) => {
    const u = (x - x0) / S, v = (y - y0) / S;
    const inside = u >= 0 && u <= 1 && v >= 0 && v <= 1;
    if (inside && motif(u, v)) return [...WHITE, 1];
    // A soft darker band along the bottom keeps it from looking flat.
    return y > H - 90 ? [...DEEP, 1] : [...GREEN, 1];
  });
};

// ---- ICO: three PNG images in one file ----
function ico(images) {
  const head = Buffer.alloc(6); head.writeUInt16LE(0, 0); head.writeUInt16LE(1, 2); head.writeUInt16LE(images.length, 4);
  let offset = 6 + images.length * 16;
  const entries = images.map(({ size, data }) => {
    const e = Buffer.alloc(16);
    e[0] = size >= 256 ? 0 : size; e[1] = size >= 256 ? 0 : size; e[2] = 0; e[3] = 0;
    e.writeUInt16LE(1, 4); e.writeUInt16LE(32, 6); e.writeUInt32LE(data.length, 8); e.writeUInt32LE(offset, 12);
    offset += data.length;
    return e;
  });
  return Buffer.concat([head, ...entries, ...images.map((i) => i.data)]);
}

const write = (name, buf) => { writeFileSync(join(out, name), buf); console.log(`wrote public/${name} (${Math.round(buf.length / 1024)} KB)`); };

write('favicon-32.png', squareIcon(32, 0.22));
write('favicon.ico', ico([16, 32, 48].map((size) => ({ size, data: squareIcon(size, 0.22) }))));
write('apple-touch-icon.png', squareIcon(180, 0));
write('icon-192.png', squareIcon(192, 0.22));
write('icon-512.png', squareIcon(512, 0.22));
write('og-default.png', socialCard());

writeFileSync(join(out, 'site.webmanifest'), JSON.stringify({
  name: 'TestaCBT',
  short_name: 'TestaCBT',
  description: 'Practise WAEC, NECO, JAMB, Post-UTME and IGCSE past questions and take timed mock exams.',
  start_url: '/dashboard',
  scope: '/',
  display: 'standalone',
  background_color: '#0A7A4A',
  theme_color: '#0A7A4A',
  icons: [
    { src: '/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
    { src: '/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
  ],
}, null, 2) + '\n');
console.log('wrote public/site.webmanifest');
