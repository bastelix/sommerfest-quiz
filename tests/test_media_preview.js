const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/media-manager.js', 'utf8');
const match = code.match(/function updatePreview\(file\) {\n([\s\S]*?)\n    }\n\n    async function handleCopyUrl/);
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
const previewUrlContainer = { hidden: true };
const previewUrlInput = {
  value: '',
  focus() {
    this.focused = true;
  },
  select() {
    this.selected = this.value;
  }
};
const previewCopyButton = {
  disabled: true,
  setAttribute(name, value) {
    this[name] = value;
  }
};
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
  previewUrlContainer,
  previewUrlInput,
  previewCopyButton,
  previewDownload,
  translations: { preview: 'Preview' },
  formatSize: (bytes) => `${bytes} B`,
  formatDate: (value) => String(value),
  withBase: (path) => {
    calls.push(path);
    return path;
  },
  renderMetadataEditor: () => {},
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
assert.strictEqual(previewUrlContainer.hidden, false);
assert.strictEqual(previewUrlInput.value, '/uploads/sample.png');
assert.strictEqual(previewCopyButton.disabled, false);
assert.strictEqual(previewCopyButton['aria-disabled'], 'false');
assert.strictEqual(previewDownload.href, '/uploads/sample.png');
assert.strictEqual(previewDownload.download, 'sample.png');

context.updatePreview(null);

assert.strictEqual(previewImage.hidden, true);
assert.strictEqual(previewImage.src, '');
assert.strictEqual(previewPlaceholder.hidden, false);
assert.strictEqual(previewMeta.hidden, true);
assert.strictEqual(previewActions.hidden, true);
assert.strictEqual(previewUrlContainer.hidden, true);
assert.strictEqual(previewUrlInput.value, '');
assert.strictEqual(previewCopyButton.disabled, true);
assert.strictEqual(previewCopyButton['aria-disabled'], 'true');
assert.strictEqual(previewDownload.href, '#');
assert.strictEqual(previewDownload.download, '');

console.log('ok');
