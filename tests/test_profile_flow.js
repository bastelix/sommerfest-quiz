const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

// Test profile enforcement redirect
const indexCode = fs.readFileSync('templates/index.twig', 'utf8');
const match = indexCode.match(/function enforceProfile\(\)\s*{[\s\S]*?}\s*enforceProfile\(\);/);
if (!match) {
  throw new Error('enforceProfile script not found');
}
const ctx1 = {
  URLSearchParams,
  window: { quizConfig: { randomNames: true, event_uid: '' } },
  localStorage: {
    data: {},
    getItem(k) { return this.data[k] ?? null; },
    setItem(k, v) { this.data[k] = String(v); },
  },
  location: {
    href: '/quiz',
    search: '',
    replaced: null,
    replace(url) { this.replaced = url; }
  }
};
vm.runInNewContext(match[0], ctx1);
assert.strictEqual(ctx1.location.replaced, '/profile?return=' + encodeURIComponent('/quiz'));

const ctx1b = {
  URLSearchParams,
  window: { quizConfig: { randomNames: true, event_uid: '' } },
  localStorage: ctx1.localStorage,
  location: {
    href: '/quiz?uid=abc',
    search: '?uid=abc',
    replaced: null,
    replace(url) { this.replaced = url; }
  }
};
vm.runInNewContext(match[0], ctx1b);
assert.strictEqual(
  ctx1b.location.replaced,
  '/profile?return=' + encodeURIComponent('/quiz?uid=abc') + '&uid=abc'
);

// Test saving profile name
const profileCode = fs.readFileSync('public/js/profile.js', 'utf8');
const ctx2 = {
  URLSearchParams,
  window: { quizConfig: { event_uid: '' } },
  nameInput: { value: '' },
  localStorage: {
    data: {},
    getItem(k) { return this.data[k] ?? null; },
    setItem(k, v) { this.data[k] = String(v); },
    removeItem(k) { delete this.data[k]; }
  },
  fetchCalls: [],
  self: { crypto: { randomUUID: () => 'uid-123' } },
  returnUrl: '/quiz',
  location: { href: '', search: '' },
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
vm.runInNewContext(profileCode, ctx2);
ctx2.nameInput.value = 'Alice';
ctx2.nameKey = 'qr_player_name:';
ctx2.uidKey = 'qr_player_uid:';
ctx2.eventUid = '';
(async () => {
  await ctx2.saveHandler?.({ preventDefault() {} });
  assert.strictEqual(ctx2.localStorage.getItem('qr_player_name:'), 'Alice');
  assert.strictEqual(ctx2.localStorage.getItem('qr_player_uid:'), 'uid-123');
  assert.strictEqual(ctx2.fetchCalls[0].url, '/api/players');
  assert(ctx2.fetchCalls[0].opts.body.includes('Alice'));
  assert.strictEqual(ctx2.location.href, '/quiz');
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });

// Test auto-loading profile name via UID
const ctx3 = {
  URLSearchParams,
  window: { quizConfig: { event_uid: 'ev1', collectPlayerUid: true } },
  nameInput: { value: '' },
  localStorage: {
    data: {},
    getItem(k) { return this.data[k] ?? null; },
    setItem(k, v) { this.data[k] = String(v); },
    removeItem(k) { delete this.data[k]; }
  },
  fetchCalls: [],
  self: { crypto: { randomUUID: () => 'uid-123' } },
  returnUrl: '/quiz',
  location: { href: '', search: '?uid=uid-123' },
  console,
  document: {
    getElementById(id) {
      if (id === 'playerName') return ctx3.nameInput;
      if (id === 'save-name') return { addEventListener: () => {} };
      if (id === 'delete-name') return { addEventListener: () => {} };
      return null;
    },
    addEventListener(ev, fn) {
      if (ev === 'DOMContentLoaded') fn();
    }
  }
};
ctx3.fetch = (url, opts) => {
  ctx3.fetchCalls.push(url);
  return Promise.resolve({ ok: true, json: () => Promise.resolve({ player_name: 'Bob' }) });
};
ctx3.window.location = ctx3.location;
vm.runInNewContext(profileCode, ctx3);
(async () => {
  await new Promise(r => setTimeout(r, 0));
  assert.strictEqual(ctx3.localStorage.getItem('qr_player_name:ev1'), 'Bob');
  assert.strictEqual(ctx3.localStorage.getItem('qr_player_uid:ev1'), 'uid-123');
  assert.strictEqual(ctx3.location.href, '/quiz');
  assert(ctx3.fetchCalls[0].startsWith('/api/players?'));
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
