const fs = require('fs');
const assert = require('assert');

const code = fs.readFileSync('public/js/onboarding.js', 'utf8');

assert(/plan\s*=\s*data\.plan\s*\|\|/.test(code), 'Checkout plan not used');
assert(/throw new Error\('no plan'\)/.test(code), 'Missing plan not handled');

console.log('ok');
