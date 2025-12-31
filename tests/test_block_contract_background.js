const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

function loadModule() {
  const code = fs.readFileSync('public/js/components/block-contract.js', 'utf8');
  const sanitized = code
    .replace(/^import .*\n/, '')
    .replace(/export /g, '')
    .replace(/const TOKEN_ENUMS = [\s\S]*?};\n/, '');

  const prelude = `class Ajv {
  compile() { return () => true; }
}
const TOKEN_ENUMS = {
  background: ['primary', 'secondary', 'muted', 'accent', 'surface'],
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
  const { normalizeSectionBackground, validateSectionBackground } = loadModule();

  const fullwidthImage = normalizeSectionBackground({ mode: 'image', image: 'hero' }, undefined, 'fullwidth');
  assert.deepStrictEqual({ ...fullwidthImage }, { mode: 'image', imageId: 'hero', attachment: 'scroll', overlay: 0 });

  const clampedOverlay = normalizeSectionBackground({ mode: 'image', image: 'hero', overlay: 2 }, undefined, 'fullwidth');
  assert.strictEqual(clampedOverlay.overlay, 1);

  const layoutDowngrade = normalizeSectionBackground({ mode: 'image', image: 'hero', overlay: 0.5, attachment: 'fixed' }, undefined, 'card');
  assert.deepStrictEqual({ ...layoutDowngrade }, { mode: 'none' });

  const colorLayout = normalizeSectionBackground({ mode: 'color', colorToken: 'primary', overlay: 0.6 }, undefined, 'normal');
  assert.deepStrictEqual({ ...colorLayout }, { mode: 'color', colorToken: 'primary' });

  assert.strictEqual(
    validateSectionBackground({ mode: 'image', imageId: 'hero', attachment: 'scroll', overlay: 0.25 }, 'fullwidth'),
    true
  );
  assert.strictEqual(
    validateSectionBackground({ mode: 'image', imageId: 'hero', overlay: 1.5 }, 'fullwidth'),
    false
  );
  assert.strictEqual(
    validateSectionBackground({ mode: 'image', imageId: 'hero', unknown: true }, 'fullwidth'),
    false
  );
  assert.strictEqual(
    validateSectionBackground({ mode: 'image', imageId: 'hero', overlay: 0.3 }, 'card'),
    false
  );

  console.log('ok');
})();
