const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync('public/js/components/block-renderer-matrix-data.js', 'utf8');
const sandbox = { console };
vm.createContext(sandbox);
vm.runInContext(source.replace(/export\s+/g, ''), sandbox);

assert.strictEqual(typeof sandbox.renderInfoMedia, 'function', 'renderInfoMedia should be available');

const missingBodyHtml = sandbox.renderInfoMedia({ id: 'missing-body', data: {} }, 'stacked');
assert.ok(
  missingBodyHtml.includes('Noch kein Text hinterlegt.'),
  'Missing body should render placeholder text'
);

const missingMediaHtml = sandbox.renderInfoMedia({ id: 'no-media', data: { body: '<p>Body</p>' } }, 'image-left');
assert.ok(
  missingMediaHtml.includes('Kein Bild ausgewählt'),
  'Missing media should render a visible placeholder'
);

const invalidVariantHtml = sandbox.renderInfoMedia({ id: 'wrong-variant', data: { body: '<p>Body</p>' } }, 'unknown');
assert.ok(
  invalidVariantHtml.includes('Unsupported info_media variant'),
  'Unsupported variant should add warning instead of throwing'
);
assert.ok(
  invalidVariantHtml.includes('data-block-variant="unsupported"'),
  'Unsupported variant should mark the block as unsupported instead of faking a known layout'
);
