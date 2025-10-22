const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/lazy-images.js', 'utf8');
const transformed = code
  .replace(/export function /g, 'function ')
  .concat('\nmodule.exports = { applyLazyImage, forceLoadLazyImage, resetLazyObserver };\n');

const context = {
  module: { exports: {} },
  exports: {},
  console,
  setTimeout,
  clearTimeout,
  globalThis: {}
};

vm.createContext(context);
vm.runInContext(transformed, context);

const { applyLazyImage, resetLazyObserver } = context.module.exports;

function createImage() {
  return {
    dataset: {},
    src: '',
    loading: '',
    decoding: '',
    fetchPriority: '',
    removeAttribute(attr) {
      if (attr === 'src') {
        this.src = '';
      }
    }
  };
}

resetLazyObserver();
const immediateImg = createImage();
applyLazyImage(immediateImg, 'https://example.com/a.png');
assert.strictEqual(immediateImg.loading, 'lazy');
assert.strictEqual(immediateImg.decoding, 'async');
assert.strictEqual(immediateImg.fetchPriority, 'low');
assert.strictEqual(immediateImg.dataset.src, 'https://example.com/a.png');
assert.strictEqual(immediateImg.dataset.lazyLoaded, 'true');
assert.strictEqual(immediateImg.src, 'https://example.com/a.png');

class FakeIntersectionObserver {
  constructor(callback) {
    this.callback = callback;
    FakeIntersectionObserver.instances.push(this);
  }

  observe(target) {
    this.target = target;
  }

  unobserve(target) {
    this.lastUnobserved = target;
  }

  disconnect() {
    this.disconnected = true;
  }

  trigger(target = this.target) {
    this.callback([
      { target, isIntersecting: true, intersectionRatio: 1 }
    ]);
  }
}
FakeIntersectionObserver.instances = [];

context.IntersectionObserver = FakeIntersectionObserver;
resetLazyObserver();

const lazyImg = createImage();
applyLazyImage(lazyImg, 'https://example.com/b.png');
assert.strictEqual(lazyImg.loading, 'lazy');
assert.strictEqual(lazyImg.decoding, 'async');
assert.strictEqual(lazyImg.fetchPriority, 'low');
assert.strictEqual(lazyImg.dataset.src, 'https://example.com/b.png');
assert.strictEqual(lazyImg.dataset.lazyLoaded, 'false');
assert.strictEqual(lazyImg.src, '');

const fakeObserver = FakeIntersectionObserver.instances[FakeIntersectionObserver.instances.length - 1];
assert.strictEqual(fakeObserver.target, lazyImg);

fakeObserver.trigger();
assert.strictEqual(lazyImg.src, 'https://example.com/b.png');
assert.strictEqual(lazyImg.dataset.lazyLoaded, 'true');

applyLazyImage(lazyImg, 'https://example.com/c.png', { forceLoad: true });
assert.strictEqual(lazyImg.src, 'https://example.com/c.png');
assert.strictEqual(lazyImg.dataset.lazyLoaded, 'true');
assert.strictEqual(fakeObserver.lastUnobserved, lazyImg);

applyLazyImage(lazyImg, null);
assert.strictEqual(lazyImg.src, '');
assert.strictEqual(lazyImg.dataset.src, undefined);
assert.strictEqual(lazyImg.dataset.lazyLoaded, undefined);

console.log('ok');
