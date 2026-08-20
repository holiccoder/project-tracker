// 构建脚本：生成图标，并把 shared/* + 对应 manifest 复制到 dist/chrome、dist/edge 与 dist/firefox。
// 用法：node extensions/build.mjs

import { cpSync, mkdirSync, readFileSync, rmSync, copyFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { generateIcons } from './scripts/generate-icons.mjs';

const root = dirname(fileURLToPath(import.meta.url));
const sharedDir = join(root, 'shared');
const distDir = join(root, 'dist');
const targets = ['chrome', 'edge', 'firefox'];

// 1. 生成图标到 shared/icons/
const iconFiles = generateIcons(join(sharedDir, 'icons'));
console.log(`图标已生成：${iconFiles.length} 个`);

// 2. 复制 shared + manifest 到各目标目录
for (const target of targets) {
  const outDir = join(distDir, target);
  rmSync(outDir, { recursive: true, force: true });
  mkdirSync(outDir, { recursive: true });

  cpSync(sharedDir, outDir, { recursive: true });
  copyFileSync(join(root, target, 'manifest.json'), join(outDir, 'manifest.json'));

  // 校验 manifest 是合法 JSON
  JSON.parse(readFileSync(join(outDir, 'manifest.json'), 'utf8'));
  console.log(`已构建 dist/${target}`);
}

console.log('构建完成。');
