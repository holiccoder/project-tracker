// 仅依赖 node:zlib / node:fs / node:path，手工生成合法 PNG 图标。
// 图案：#2563eb 蓝色圆角方块底 + 白色钥匙孔。
// 可独立运行（node extensions/scripts/generate-icons.mjs），也可被 build.mjs 导入调用。

import { deflateSync } from 'node:zlib';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const SIZES = [16, 48, 128];
const BG = [0x25, 0x63, 0xeb]; // #2563eb
const FG = [0xff, 0xff, 0xff]; // 白色钥匙孔

// ---- CRC32 ----
const CRC_TABLE = (() => {
  const table = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) {
      c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    }
    table[n] = c >>> 0;
  }
  return table;
})();

function crc32(buf) {
  let c = 0xffffffff;
  for (let i = 0; i < buf.length; i++) {
    c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  }
  return (c ^ 0xffffffff) >>> 0;
}

function chunk(type, data) {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length, 0);
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(body), 0);
  return Buffer.concat([len, body, crc]);
}

// ---- 形状遮罩（基于 0..1 归一化坐标） ----
function insideRoundedRect(x, y, radius) {
  if (x < 0 || x > 1 || y < 0 || y > 1) return false;
  const cx = x < radius ? radius : x > 1 - radius ? 1 - radius : x;
  const cy = y < radius ? radius : y > 1 - radius ? 1 - radius : y;
  const dx = x - cx;
  const dy = y - cy;
  return dx * dx + dy * dy <= radius * radius;
}

function insideKeyhole(x, y) {
  const cx = 0.5;
  const cy = 0.38;
  const r = 0.16;
  // 圆孔
  const dx = x - cx;
  const dy = y - cy;
  if (dx * dx + dy * dy <= r * r) return true;
  // 下方梯形槽，从圆孔底部向下收窄
  const top = cy;
  const bottom = 0.76;
  if (y < top || y > bottom) return false;
  const t = (y - top) / (bottom - top);
  const halfWidth = r * (0.85 - 0.5 * t); // 0.85r -> 0.35r
  return Math.abs(x - cx) <= halfWidth;
}

// ---- 渲染单个 PNG（4x 超采样抗锯齿） ----
function renderPng(size) {
  const SS = 4;
  const n = size * SS;
  const cornerRadius = 0.2;

  const raw = Buffer.alloc(size * (size * 4 + 1));
  let offset = 0;

  for (let py = 0; py < size; py++) {
    raw[offset++] = 0; // filter: none
    for (let px = 0; px < size; px++) {
      let r = 0;
      let g = 0;
      let b = 0;
      let a = 0;
      for (let sy = 0; sy < SS; sy++) {
        for (let sx = 0; sx < SS; sx++) {
          const x = (px + (sx + 0.5) / SS) / size;
          const y = (py + (sy + 0.5) / SS) / size;
          if (!insideRoundedRect(x, y, cornerRadius)) continue; // 透明
          const color = insideKeyhole(x, y) ? FG : BG;
          r += color[0];
          g += color[1];
          b += color[2];
          a += 255;
        }
      }
      const samples = SS * SS;
      raw[offset++] = Math.round(r / samples);
      raw[offset++] = Math.round(g / samples);
      raw[offset++] = Math.round(b / samples);
      raw[offset++] = Math.round(a / samples);
    }
  }

  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(size, 0);
  ihdr.writeUInt32BE(size, 4);
  ihdr[8] = 8; // bit depth
  ihdr[9] = 6; // color type: RGBA
  ihdr[10] = 0; // compression
  ihdr[11] = 0; // filter
  ihdr[12] = 0; // interlace

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

export function generateIcons(outDir) {
  mkdirSync(outDir, { recursive: true });
  for (const size of SIZES) {
    const file = join(outDir, `icon${size}.png`);
    writeFileSync(file, renderPng(size));
  }
  return SIZES.map((size) => join(outDir, `icon${size}.png`));
}

// 独立运行时默认生成到 shared/icons/
if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const here = dirname(fileURLToPath(import.meta.url));
  const outDir = join(here, '..', 'shared', 'icons');
  const files = generateIcons(outDir);
  for (const f of files) console.log(`已生成 ${f}`);
}
