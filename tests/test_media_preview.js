const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/media-manager.js', 'utf8');
const match = code.match(/function updatePreview\(file\) {\n([\s\S]*?)\n    }\n\n    function renderFiles/);
if (!match) {
  throw new Error('updatePreview function not found');
}
const updatePreviewCode = `function updatePreview(file) {\n${match[1]}\n}`;

const previewImage = {
  hidden: true,
  src: '',
  alt: ''
};
const previewPlaceholder = { hidden: false };
const previewMeta = { hidden: true };
const previewActions = { hidden: true };
const previewName = { textContent: '' };
const previewSize = { textContent: '' };
const previewModified = { textContent: '' };
const previewDownload = {
  href: '#',
  download: '',
  setAttribute(attr, value) {
    if (attr === 'download') {
      this.download = value;
    } else {
      this[attr] = value;
    }
  },
  removeAttribute(attr) {
    if (attr === 'download') {
      this.download = '';
    }
  }
};

const calls = [];
const context = {
  previewImage,
  previewPlaceholder,
  previewMeta,
  previewActions,
  previewName,
  previewSize,
  previewModified,
  previewDownload,
  translations: { preview: 'Preview' },
  formatSize: (bytes) => `${bytes} B`,
  formatDate: (value) => String(value),
  withBase: (path) => {
    calls.push(path);
    return path;
  },
  console
};

vm.createContext(context);
vm.runInContext(updatePreviewCode, context);

context.updatePreview({
  name: 'sample.png',
  url: '/uploads/sample.png',
  size: 2048,
  modified: '2024-01-01T00:00:00Z'
});

assert.deepStrictEqual(calls, ['/uploads/sample.png']);
assert.strictEqual(previewImage.hidden, false);
assert.strictEqual(previewImage.src, '/uploads/sample.png');
assert.strictEqual(previewImage.alt, 'Preview: sample.png');
assert.strictEqual(previewPlaceholder.hidden, true);
assert.strictEqual(previewMeta.hidden, false);
assert.strictEqual(previewActions.hidden, false);
assert.strictEqual(previewName.textContent, 'sample.png');
assert.strictEqual(previewSize.textContent, '2048 B');
assert.strictEqual(previewModified.textContent, '2024-01-01T00:00:00Z');
assert.strictEqual(previewDownload.href, '/uploads/sample.png');
assert.strictEqual(previewDownload.download, 'sample.png');

context.updatePreview(null);

assert.strictEqual(previewImage.hidden, true);
assert.strictEqual(previewImage.src, '');
assert.strictEqual(previewPlaceholder.hidden, false);
assert.strictEqual(previewMeta.hidden, true);
assert.strictEqual(previewActions.hidden, true);
assert.strictEqual(previewDownload.href, '#');
assert.strictEqual(previewDownload.download, '');

console.log('ok');
