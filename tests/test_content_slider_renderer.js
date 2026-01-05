const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const matrixSource = fs
  .readFileSync('public/js/components/block-renderer-matrix-data.js', 'utf8')
  .replace(/^import[^;]+;\n/gm, '')
  .replace(/export\s+/g, '')
  .concat('\nthis.RENDERER_MATRIX = RENDERER_MATRIX;');

const sandbox = { console, resolveSectionIntent: () => 'feature' };
vm.createContext(sandbox);
vm.runInContext(matrixSource, sandbox);

const block = {
  id: 'slider-1',
  type: 'content_slider',
  variant: 'images',
  data: {
    title: 'Slider',
    slides: [
      {
        id: 'slide-1',
        label: 'Erster Slide',
        body: '<p>Inhalt</p>',
        imageId: '/uploads/image.jpg',
        imageAlt: 'Alt text',
        link: { label: 'Mehr', href: '#' }
      }
    ]
  }
};

const renderer = sandbox.RENDERER_MATRIX.content_slider.images;
assert.ok(renderer, 'Renderer should exist for content_slider.images');
const html = renderer(block, { context: 'preview' });
assert.ok(/uk-slider/.test(html), 'Rendered HTML should include UIkit slider');
assert.ok(/content-slider__item/.test(html), 'Slides should render items');

console.log('ok');
