const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const indexCode = fs.readFileSync('templates/index.twig', 'utf8');
const match = indexCode.match(/function enforceProfile\(\)\s*{[\s\S]*?}\s*enforceProfile\(\);/);
if (!match) {
  throw new Error('enforceProfile script not found');
}
const storageCode = fs.readFileSync('public/js/storage.js', 'utf8');

// Simulate enforceProfile redirect with catalog slug
const storage1 = {
  data: {},
  getItem(k) { return this.data[k] ?? null; },
  setItem(k, v) { this.data[k] = String(v); },
  removeItem(k) { delete this.data[k]; }
};
const ctx1 = {
  URLSearchParams,
  window: { quizConfig: { randomNames: true, event_uid: '' } },
  localStorage: storage1,
  sessionStorage: storage1,
  location: {
    href: '/catalog/my%2Fslug',
    search: '',
    replaced: null,
    replace(url) { this.replaced = url; }
  }
};
vm.runInNewContext(storageCode, ctx1);
vm.runInNewContext(match[0], ctx1);
const encodedReturn = ctx1.location.replaced.match(/return=([^&]+)/)[1];
assert(encodedReturn.includes('%252F'));

// Save name and ensure redirect uses decoded return URL
const storage2 = {
  data: {},
  getItem(k) { return this.data[k] ?? null; },
  setItem(k, v) { this.data[k] = String(v); },
  removeItem(k) { delete this.data[k]; }
};
const profileCode = fs.readFileSync('public/js/profile.js', 'utf8');
const ctx2 = {
  URLSearchParams,
  window: { quizConfig: { event_uid: '' } },
  nameInput: { value: '' },
  localStorage: storage2,
  sessionStorage: storage2,
  fetchCalls: [],
  self: { crypto: { randomUUID: () => 'uid-123' } },
  returnUrl: encodedReturn,
  location: { href: '', search: '' },
  postSession: () => Promise.resolve(),
  alert: () => {},
  console,
  document: {
    getElementById(id) {
      if (id === 'playerName') return ctx2.nameInput;
      if (id === 'save-name') return { addEventListener: (ev, fn) => { ctx2.saveHandler = fn; } };
      if (id === 'delete-name') return { addEventListener: () => {} };
      return null;
    },
    addEventListener(ev, fn) {
      if (ev === 'DOMContentLoaded') fn();
    }
  }
};
ctx2.fetch = (url, opts) => { ctx2.fetchCalls.push({ url, opts }); return Promise.resolve(); };
ctx2.window.location = ctx2.location;
vm.runInNewContext(storageCode, ctx2);
vm.runInNewContext(profileCode, ctx2);
ctx2.nameInput.value = 'Alice';
(async () => {
  await ctx2.saveHandler?.({ preventDefault() {} });
  assert.strictEqual(ctx2.location.href, decodeURIComponent(encodedReturn));
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
