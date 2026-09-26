// Draws the TestaCBT app icons (an answer-sheet bubble with a check mark) as PNG files, with no image libraries.
//   node scripts/make-icons.mjs
// Replace the files in assets/images with your final artwork whenever you have it; app.json points at these names.
import { deflateSync } from 'node:zlib';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const out = join(dirname(fileURLToPath(import.meta.url)), '..', 'assets', 'images');
mkdirSync(out, { recursive: true });

const GREEN = [10, 122, 74];
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

function png(size, rgba) {
  const raw = Buffer.alloc((size * 4 + 1) * size);
  for (let y = 0; y < size; y++) {
    raw[y * (size * 4 + 1)] = 0;
    rgba.copy(raw, y * (size * 4 + 1) + 1, y * size * 4, (y + 1) * size * 4);
  }
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(size, 0); ihdr.writeUInt32BE(size, 4); ihdr[8] = 8; ihdr[9] = 6;
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0))]);
}

// ---- the motif: a ring (the answer bubble) with a check inside, in unit coordinates ----
const distToSegment = (px, py, ax, ay, bx, by) => {
  const dx = bx - ax, dy = by - ay;
  const t = Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy)));
  return Math.hypot(px - (ax + t * dx), py - (ay + t * dy));
};

/** 0..1 coverage of the motif at a point. `scale` shrinks it toward the centre (for adaptive icons). */
function motif(x, y, scale) {
  const u = (x - 0.5) / scale + 0.5;
  const v = (y - 0.5) / scale + 0.5;
  const ring = Math.abs(Math.hypot(u - 0.5, v - 0.5) - 0.30) <= 0.045;
  const w = 0.055;
  const check = distToSegment(u, v, 0.355, 0.52, 0.46, 0.625) <= w || distToSegment(u, v, 0.46, 0.625, 0.66, 0.39) <= w;
  return ring || check ? 1 : 0;
}

function draw(size, { bg, fg, scale = 1, cornerless = true }) {
  const rgba = Buffer.alloc(size * size * 4);
  const N = 3;   // 3x3 samples per pixel for smooth edges

  for (let py = 0; py < size; py++) {
    for (let px = 0; px < size; px++) {
      let hit = 0;
      for (let sy = 0; sy < N; sy++) for (let sx = 0; sx < N; sx++) hit += motif((px + (sx + 0.5) / N) / size, (py + (sy + 0.5) / N) / size, scale);
      const cover = hit / (N * N);

      const i = (py * size + px) * 4;
      if (bg) {
        rgba[i] = Math.round(bg[0] * (1 - cover) + fg[0] * cover);
        rgba[i + 1] = Math.round(bg[1] * (1 - cover) + fg[1] * cover);
        rgba[i + 2] = Math.round(bg[2] * (1 - cover) + fg[2] * cover);
        rgba[i + 3] = 255;
      } else {
        rgba[i] = fg[0]; rgba[i + 1] = fg[1]; rgba[i + 2] = fg[2];
        rgba[i + 3] = Math.round(cover * 255);
      }
    }
  }
  return png(size, rgba);
}

const write = (name, buf) => { writeFileSync(join(out, name), buf); console.log(`wrote ${name} (${Math.round(buf.length / 1024)} KB)`); };

write('icon.png', draw(1024, { bg: GREEN, fg: WHITE }));                                  // store icon: full square, no transparency
write('android-icon-foreground.png', draw(1024, { bg: null, fg: WHITE, scale: 0.72 }));   // inside the adaptive icon's safe zone
write('android-icon-monochrome.png', draw(1024, { bg: null, fg: [0, 0, 0], scale: 0.72 }));
write('splash-icon.png', draw(512, { bg: null, fg: WHITE }));

// Solid green background layer for the adaptive icon.
const bgPixels = Buffer.alloc(1024 * 1024 * 4);
for (let i = 0; i < bgPixels.length; i += 4) { bgPixels[i] = GREEN[0]; bgPixels[i + 1] = GREEN[1]; bgPixels[i + 2] = GREEN[2]; bgPixels[i + 3] = 255; }
write('android-icon-background.png', png(1024, bgPixels));
