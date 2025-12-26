const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/admin.js', 'utf8');
const normalizeMatch = code.match(/const normalizeEndpointToSameOrigin = ([\s\S]*?)const warnExternalEndpoint/);

if (!normalizeMatch) {
  throw new Error('normalizeEndpointToSameOrigin not found in admin.js');
}

const normalizeSource = normalizeMatch[1].trim().replace(/;?$/, '');

const context = {
  window: { location: { origin: 'https://quiz.example.com' } },
  URL,
  console
};

vm.createContext(context);
vm.runInContext(`normalizeEndpointToSameOrigin = ${normalizeSource};`, context);

const { normalizeEndpointToSameOrigin } = context;

// Absolute URL with same origin should be reduced to path + search + hash.
const sameOrigin = normalizeEndpointToSameOrigin('https://quiz.example.com/admin/settings?foo=1#bar');
assert.strictEqual(sameOrigin.endpoint, '/admin/settings?foo=1#bar');
assert.strictEqual(sameOrigin.external, false);
assert.strictEqual(sameOrigin.externalHost, null);

// Relative paths should remain unchanged and be treated as internal.
const relative = normalizeEndpointToSameOrigin('/api/data ');
assert.strictEqual(relative.endpoint, '/api/data');
assert.strictEqual(relative.external, false);
assert.strictEqual(relative.externalHost, null);

// External hosts should be flagged so that callers can abort requests.
const external = normalizeEndpointToSameOrigin('https://malicious.example.org/steal');
assert.strictEqual(external.endpoint, 'https://malicious.example.org/steal');
assert.strictEqual(external.external, true);
assert.strictEqual(external.externalHost, 'https://malicious.example.org');

console.log('admin endpoint normalization tests passed');
