// Compare baseline/ vs current/ pixel-by-pixel, emit diff/ images + report.html.
// Usage: node compare.js
const fs = require('fs');
const path = require('path');
const { PNG } = require('pngjs');
const pixelmatch = require('pixelmatch');

const baseDir = path.join(__dirname, 'baseline');
const curDir = path.join(__dirname, 'current');
const diffDir = path.join(__dirname, 'diff');
fs.mkdirSync(diffDir, { recursive: true });

const THRESH = 0.1; // per-pixel color sensitivity

function readPng(p) {
  return PNG.sync.read(fs.readFileSync(p));
}

function pad(img, w, h) {
  if (img.width === w && img.height === h) return img;
  const out = new PNG({ width: w, height: h });
  out.data.fill(0);
  PNG.bitblt(img, out, 0, 0, Math.min(img.width, w), Math.min(img.height, h), 0, 0);
  return out;
}

const files = fs.existsSync(baseDir)
  ? fs.readdirSync(baseDir).filter(f => f.endsWith('.png'))
  : [];

const rows = [];
let totalDiff = 0;
for (const f of files) {
  const bp = path.join(baseDir, f);
  const cp = path.join(curDir, f);
  if (!fs.existsSync(cp)) {
    rows.push({ f, status: 'MISSING', pct: 100, dims: '-' });
    totalDiff++;
    continue;
  }
  let a = readPng(bp), b = readPng(cp);
  const dimMismatch = a.width !== b.width || a.height !== b.height;
  const w = Math.max(a.width, b.width), h = Math.max(a.height, b.height);
  a = pad(a, w, h); b = pad(b, w, h);
  const diff = new PNG({ width: w, height: h });
  const n = pixelmatch(a.data, b.data, diff.data, w, h, { threshold: THRESH });
  const pct = (n / (w * h)) * 100;
  if (n > 0) fs.writeFileSync(path.join(diffDir, f), PNG.sync.write(diff));
  const dims = dimMismatch ? `${a.width}x${a.height} vs base` : `${w}x${h}`;
  rows.push({ f, status: n === 0 ? 'OK' : 'DIFF', pct, dims, n, dimMismatch });
  if (n > 0) totalDiff++;
}

rows.sort((x, y) => (y.pct || 0) - (x.pct || 0));

const esc = s => String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
let html = `<!doctype html><meta charset=utf8><title>SS diff</title>
<style>body{font-family:system-ui;margin:20px}h1{font-size:18px}
table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:6px;font-size:13px;vertical-align:top}
.OK{color:#137a13}.DIFF{color:#b00}.MISSING{color:#b00;font-weight:bold}
img{max-width:380px;display:block;border:1px solid #ccc}
tr.ok td{background:#f6fff6}</style>
<h1>Visual regression — ${rows.length} shots, ${totalDiff} changed</h1>
<table><tr><th>Page</th><th>Status</th><th>% changed</th><th>Dims</th><th>baseline</th><th>current</th><th>diff</th></tr>`;
for (const r of rows) {
  const okCls = r.status === 'OK' ? 'ok' : '';
  html += `<tr class="${okCls}"><td>${esc(r.f)}</td><td class="${r.status}">${r.status}${r.dimMismatch ? ' (size!)' : ''}</td>`
    + `<td>${(r.pct || 0).toFixed(3)}%</td><td>${esc(r.dims)}</td>`
    + `<td><img src="baseline/${esc(r.f)}"></td>`
    + `<td>${fs.existsSync(path.join(curDir, r.f)) ? `<img src="current/${esc(r.f)}">` : '-'}</td>`
    + `<td>${r.status === 'DIFF' ? `<img src="diff/${esc(r.f)}">` : '-'}</td></tr>`;
}
html += `</table>`;
fs.writeFileSync(path.join(__dirname, 'report.html'), html);

console.log(`\nCompared ${rows.length} shots. Changed: ${totalDiff}`);
for (const r of rows.filter(r => r.status !== 'OK')) {
  console.log(`  ${r.status.padEnd(8)} ${r.pct.toFixed(3).padStart(7)}%  ${r.f}${r.dimMismatch ? '  (size mismatch)' : ''}`);
}
console.log(`\nReport: ${path.join(__dirname, 'report.html')}`);
process.exit(totalDiff > 0 ? 1 : 0);
