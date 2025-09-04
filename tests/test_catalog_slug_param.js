const fs = require('fs');
const code = fs.readFileSync('public/js/catalog.js', 'utf8');

if (!/params\.get\(['\"]slug['\"]\)/.test(code)) {
  throw new Error('slug param handling missing');
}

if (!/dataset\.slug/.test(code)) {
  throw new Error('dataset.slug match missing');
}

if (!/return\s+value\s*===\s*id\s*\|\|\s*slug\s*===\s*id/.test(code)) {
  throw new Error('slug comparison logic missing');
}

console.log('ok');
