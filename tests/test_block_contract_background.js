const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

function loadModule() {
  const code = fs.readFileSync('public/js/components/block-contract.js', 'utf8');
  const sanitized = code
    .replace(/^import[^;]+;\n/gm, '')
    .replace(/export /g, '')
    .replace(/const TOKEN_ENUMS = [\s\S]*?};\n/, '');

  const prelude = `const TOKEN_ENUMS = {
  background: ['default', 'muted', 'primary'],
  spacing: ['small', 'normal', 'large'],
  width: ['narrow', 'normal', 'wide'],
  columns: ['single', 'two', 'three', 'four'],
  accent: ['brandA', 'brandB', 'brandC']
};\n`;

  const sectionIntentsCode = fs
    .readFileSync('public/js/components/section-intents.js', 'utf8')
    .replace(/^import[^;]+;\n/gm, '')
    .replace(/export\s+/g, '');

  const context = { RENDERER_MATRIX: {} };
  vm.createContext(context);
  vm.runInContext(sectionIntentsCode, context);
  vm.runInContext(prelude + sanitized, context);
  return context;
}

(() => {
  const { normalizeSectionBackground, validateSectionBackground } = loadModule();

  const fullwidthImage = normalizeSectionBackground({ mode: 'image', image: 'hero' }, undefined, 'full');
  assert.deepStrictEqual({ ...fullwidthImage }, { mode: 'image', imageId: 'hero', attachment: 'scroll', overlay: 0 });

  const clampedOverlay = normalizeSectionBackground({ mode: 'image', image: 'hero', overlay: 2 }, undefined, 'full');
  assert.strictEqual(clampedOverlay.overlay, 1);

  const cardImage = normalizeSectionBackground({ mode: 'image', image: 'hero', overlay: 0.5, attachment: 'fixed' }, undefined, 'card');
  assert.deepStrictEqual({ ...cardImage }, { mode: 'image', imageId: 'hero', attachment: 'fixed', overlay: 0.5 });

  const colorLayout = normalizeSectionBackground({ mode: 'color', colorToken: 'primary', overlay: 0.6 }, undefined, 'normal');
  assert.deepStrictEqual({ ...colorLayout }, { mode: 'color', colorToken: 'primary' });

  assert.strictEqual(
    validateSectionBackground({ mode: 'image', imageId: 'hero', attachment: 'scroll', overlay: 0.25 }, 'full'),
    true
  );
  assert.strictEqual(
    validateSectionBackground({ mode: 'image', imageId: 'hero', overlay: 1.5 }, 'full'),
    false
  );
  assert.strictEqual(
    validateSectionBackground({ mode: 'image', imageId: 'hero', unknown: true }, 'full'),
    false
  );
  assert.strictEqual(
    validateSectionBackground({ mode: 'image', imageId: 'hero', overlay: 0.3 }, 'card'),
    true
  );

  console.log('ok');
})();
