const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

function loadModule() {
  const code = fs.readFileSync('public/js/components/block-contract.js', 'utf8');
  const sanitized = code
    .replace(/^import .*\n/, '')
    .replace(/export /g, '')
    .replace(/const TOKEN_ENUMS = [\s\S]*?};\n/, '');

  const prelude = `const TOKEN_ENUMS = {
  background: ['default', 'muted', 'primary'],
  spacing: ['small', 'normal', 'large'],
  width: ['narrow', 'normal', 'wide'],
  columns: ['single', 'two', 'three', 'four'],
  accent: ['brandA', 'brandB', 'brandC']
};\n`;

  const context = { RENDERER_MATRIX: {} };
  vm.createContext(context);
  vm.runInContext(prelude + sanitized, context);
  return context;
}

(() => {
  const { normalizeSectionStyle, validateSectionStyle } = loadModule();

  const fullwidthToCard = normalizeSectionStyle({
    layout: 'card',
    background: { mode: 'image', imageId: 'hero', overlay: 0.5, attachment: 'fixed' }
  });
  assert.deepStrictEqual(
    { ...fullwidthToCard, background: { ...fullwidthToCard.background } },
    { layout: 'card', background: { mode: 'none' } }
  );

  const imageToNone = normalizeSectionStyle({
    layout: 'fullwidth',
    background: { mode: 'none', imageId: 'ghost', overlay: 0.7, attachment: 'fixed' }
  });
  assert.deepStrictEqual(
    { ...imageToNone, background: { ...imageToNone.background } },
    { layout: 'fullwidth', background: { mode: 'none' } }
  );

  const legacyCard = normalizeSectionStyle(undefined, 'legacy-image', 'card');
  assert.deepStrictEqual(
    { ...legacyCard, background: { ...legacyCard.background } },
    { layout: 'card', background: { mode: 'none' } }
  );

  assert.strictEqual(
    validateSectionStyle({ background: { mode: 'color', colorToken: 'primary' } }),
    false
  );

  console.log('ok');
})();
