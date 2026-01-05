const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync('public/js/components/block-contract.js', 'utf8')
  .replace(/^import[^;]+;\n/gm, '')
  .replace(/export /g, '');

const prelude = 'const RENDERER_MATRIX = { content_slider: { words: () => null, images: () => null } };\n'
  + 'const SECTION_INTENTS = ["content", "feature", "highlight", "hero"];\n';
const context = { console };
vm.createContext(context);
vm.runInContext(prelude + source, context);

const { validateBlockContract, normalizeBlockContract } = context;

const block = {
  id: 'slider-1',
  type: 'content_slider',
  variant: 'words',
  data: {
    title: 'Slider',
    slides: [
      {
        id: 'slide-1',
        label: 'Erster Slide',
        body: '<p>Inhalt</p>',
        imageId: '/uploads/image.jpg',
        imageAlt: 'Alt',
        link: { label: 'Mehr erfahren', href: '#' }
      }
    ]
  }
};

assert.ok(validateBlockContract(block).valid, 'Content slider block should be valid');

const normalized = normalizeBlockContract({
  ...block,
  data: {
    slides: [
      { id: 'slide-1', label: 'Erster Slide', link: { label: 'Mehr erfahren', href: '#' }, imageAlt: '' }
    ]
  }
});

assert.strictEqual(normalized.data.slides[0].link.href, '#', 'CTA link should stay intact');
assert.strictEqual(normalized.data.slides[0].imageAlt, undefined, 'Empty alt text is removed');

console.log('ok');
