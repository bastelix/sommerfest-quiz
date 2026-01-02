const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const matrixSource = fs
  .readFileSync('public/js/components/block-renderer-matrix-data.js', 'utf8')
  .replace(/^import[^;]+;\n/gm, '')
  .replace(/export\s+/g, '')
  .concat('\nthis.RENDERER_MATRIX = RENDERER_MATRIX;');

const sandbox = { console, resolveSectionIntent: () => 'content' };
vm.createContext(sandbox);
vm.runInContext(matrixSource, sandbox);

const { RENDERER_MATRIX } = sandbox;
assert.ok(RENDERER_MATRIX, 'Renderer matrix should be available');

const page = JSON.parse(fs.readFileSync('content/marketing/quizrace.page.json', 'utf8'));
assert.ok(Array.isArray(page.blocks), 'Page should contain blocks');
assert.strictEqual(
  page.blocks.map(block => block.type).join(','),
  ['hero', 'stat_strip', 'process_steps', 'feature_list', 'faq', 'cta'].join(','),
  'Expected block ordering for quizrace page'
);

const renderedBlocks = page.blocks.map((block, index) => {
  const renderer = RENDERER_MATRIX[block.type]?.[block.variant];
  assert.ok(renderer, `Renderer missing for ${block.type}/${block.variant}`);
  assert.doesNotThrow(() => renderer(block, { context: 'preview' }), `Renderer failed for block ${block.id || index}`);

  return `${block.id || index}:${block.type}:${block.variant}`;
});

assert.strictEqual(
  new Set(renderedBlocks).size,
  renderedBlocks.length,
  'Blocks should remain distinct after import'
);

const calServerPage = JSON.parse(fs.readFileSync('content/marketing/calserver.page.json', 'utf8'));
assert.ok(Array.isArray(calServerPage.blocks), 'calServer page should contain blocks');

calServerPage.blocks.forEach((block, index) => {
  const renderer = RENDERER_MATRIX[block.type]?.[block.variant];
  assert.ok(renderer, `Renderer missing for ${block.type}/${block.variant}`);
  assert.doesNotThrow(() => renderer(block, { context: 'preview' }), `calServer renderer failed for block ${block.id || index}`);
});
