const fs = require('fs');
const code = fs.readFileSync('public/js/catalog.js', 'utf8');

if (!/allowed = allowed\.map\(t => String\(t\)\.toLowerCase\(\)\);/.test(code)) {
    throw new Error('allowed normalization missing');
}
if (!/allowed.indexOf\(name.toLowerCase\(\)\) === -1/.test(code)) {
    throw new Error('case-insensitive check missing');
}
if (!/cfg.QRRestrict && allowed.indexOf\(name.toLowerCase\(\)\) === -1\)\{\n\s*alert\('Unbekanntes oder nicht berechtigtes Team\/Person'\)/.test(code)) {
    throw new Error('manual entry validation missing');
}
console.log('ok');
