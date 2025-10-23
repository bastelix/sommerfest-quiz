const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

// Test profile enforcement redirect
const indexCode = fs.readFileSync('templates/index.twig', 'utf8');
const match = indexCode.match(/function enforceProfile\(\)\s*{[\s\S]*?}\s*enforceProfile\(\);/);
if (!match) {
  throw new Error('enforceProfile script not found');
}
const storageCode = fs.readFileSync('public/js/storage.js', 'utf8');
const storageObj1 = {
  data: {},
  getItem(k) { return this.data[k] ?? null; },
  setItem(k, v) { this.data[k] = String(v); },
  removeItem(k) { delete this.data[k]; }
};
const ctx1 = {
  URLSearchParams,
  window: { quizConfig: { randomNames: true, event_uid: '' } },
  localStorage: storageObj1,
  sessionStorage: storageObj1,
  location: {
    href: '/quiz',
    search: '',
    replaced: null,
    replace(url) { this.replaced = url; }
  }
};
vm.runInNewContext(storageCode, ctx1);
vm.runInNewContext(match[0], ctx1);
assert.strictEqual(ctx1.location.replaced, '/profile?return=' + encodeURIComponent('/quiz'));

const ctx1b = {
  URLSearchParams,
  window: { quizConfig: { randomNames: true, event_uid: '' } },
  localStorage: ctx1.localStorage,
  sessionStorage: ctx1.sessionStorage,
  location: {
    href: '/quiz?uid=abc',
    search: '?uid=abc',
    replaced: null,
    replace(url) { this.replaced = url; }
  }
};
vm.runInNewContext(storageCode, ctx1b);
vm.runInNewContext(match[0], ctx1b);
assert.strictEqual(
  ctx1b.location.replaced,
  '/profile?return=' + encodeURIComponent('/quiz?uid=abc') + '&uid=abc'
);

// Test saving profile name
const profileCode = fs.readFileSync('public/js/profile.js', 'utf8');
const storageObj2 = {
  data: {},
  getItem(k) { return this.data[k] ?? null; },
  setItem(k, v) { this.data[k] = String(v); },
  removeItem(k) { delete this.data[k]; }
};
const ctx2 = {
  URLSearchParams,
  window: { quizConfig: { event_uid: '' } },
  nameInput: { value: '' },
  localStorage: storageObj2,
  sessionStorage: storageObj2,
  fetchCalls: [],
  self: { crypto: { randomUUID: () => 'uid-123' } },
  returnUrl: '/quiz',
  location: { href: '', search: '' },
  postSession: () => Promise.resolve(),
  deleteSession: () => Promise.resolve(),
  alert: () => {},
  console,
  document: {
    getElementById(id) {
      if (id === 'playerName') return ctx2.nameInput;
      if (id === 'save-name') return { addEventListener: (ev, fn) => { ctx2.saveHandler = fn; } };
      if (id === 'delete-name') return { addEventListener: (ev, fn) => { ctx2.deleteHandler = fn; } };
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

(async () => {
  await new Promise(r => setTimeout(r, 0));
  ctx2.nameInput.value = 'Alice';
  await ctx2.saveHandler?.({ preventDefault() {} });
  assert.strictEqual(ctx2.localStorage.getItem('quizUser'), 'Alice');
  assert.strictEqual(ctx2.getStored(ctx2.STORAGE_KEYS.PLAYER_NAME), 'Alice');
  assert.strictEqual(ctx2.localStorage.getItem('qr_player_uid:'), 'uid-123');
  assert.strictEqual(ctx2.fetchCalls[0].url, '/api/players');
  assert(ctx2.fetchCalls[0].opts.body.includes('Alice'));
  assert.strictEqual(ctx2.location.href, '/quiz');

  const quizCode = fs.readFileSync('public/js/quiz.js', 'utf8');
  const promptBlock = quizCode.match(/if\(!getStored\('quizUser'\) && !cfg\.QRRestrict && !cfg\.QRUser\)\{\s*if\(cfg\.randomNames\)\{\s*await promptTeamName\(\);\s*\}\s*\}/);
  if (!promptBlock) { throw new Error('Prompt block not found'); }
  const ctxAfter = {
    cfg: { randomNames: true },
    promptCalled: false,
    getStored: ctx2.getStored,
    promptTeamName: async () => { ctxAfter.promptCalled = true; }
  };
  await vm.runInNewContext('(async () => {' + promptBlock[0] + '})()', ctxAfter);
  assert(!ctxAfter.promptCalled);

  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });

const storageObj2c = {
  data: {},
  getItem(k) { return this.data[k] ?? null; },
  setItem(k, v) { this.data[k] = String(v); },
  removeItem(k) { delete this.data[k]; }
};
const ctx2c = {
  URLSearchParams,
  window: { quizConfig: { event_uid: 'ev-delete' } },
  nameInput: { value: '' },
  localStorage: storageObj2c,
  sessionStorage: storageObj2c,
  fetchCalls: [],
  self: { crypto: { randomUUID: () => 'uid-del' } },
  returnUrl: '',
  location: { href: '', search: '' },
  postSession: () => Promise.resolve(),
  deleteCalls: 0,
  deleteSession: () => { ctx2c.deleteCalls += 1; return Promise.resolve(); },
  alert: () => {},
  console,
  UIkit: { notification: opts => { ctx2c.notifications.push(opts); } },
  notifications: [],
  document: {
    getElementById(id) {
      if (id === 'playerName') return ctx2c.nameInput;
      if (id === 'save-name') return { addEventListener: () => {} };
      if (id === 'delete-name') return { addEventListener: (ev, fn) => { ctx2c.deleteHandler = fn; } };
      return null;
    },
    addEventListener(ev, fn) {
      if (ev === 'DOMContentLoaded') fn();
    }
  }
};
ctx2c.fetch = () => Promise.resolve();
ctx2c.window.location = ctx2c.location;
ctx2c.Math = Object.create(Math);
ctx2c.Math.random = () => 0.12345;
vm.runInNewContext(storageCode, ctx2c);
ctx2c.setStored(ctx2c.STORAGE_KEYS.PLAYER_NAME, 'Alice');
ctx2c.setStored(ctx2c.STORAGE_KEYS.PLAYER_UID, 'uid-del');
vm.runInNewContext(profileCode, ctx2c);
(async () => {
  await new Promise(r => setTimeout(r, 0));
  assert.strictEqual(ctx2c.nameInput.value, 'Alice');
  assert.strictEqual(ctx2c.getStored(ctx2c.STORAGE_KEYS.PLAYER_NAME), 'Alice');
  await ctx2c.deleteHandler?.({ preventDefault() {} });
  assert.strictEqual(ctx2c.deleteCalls, 1);
  assert.strictEqual(ctx2c.getStored(ctx2c.STORAGE_KEYS.PLAYER_NAME), null);
  assert.strictEqual(ctx2c.getStored(ctx2c.STORAGE_KEYS.PLAYER_UID), null);
  assert.strictEqual(ctx2c.localStorage.getItem('quizUser'), null);
  assert.strictEqual(ctx2c.nameInput.value, '');
  assert.strictEqual(ctx2c.notifications.length, 1);
  assert.strictEqual(ctx2c.notifications[0].message, 'Name gelöscht');
  assert.strictEqual(ctx2c.notifications[0].status, 'success');
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });

const storageObj2d = {
  data: {},
  getItem(k) { return this.data[k] ?? null; },
  setItem(k, v) { this.data[k] = String(v); },
  removeItem(k) { delete this.data[k]; }
};
const ctx2d = {
  URLSearchParams,
  window: { quizConfig: { event_uid: 'ev-delete' } },
  nameInput: { value: '' },
  localStorage: storageObj2d,
  sessionStorage: storageObj2d,
  fetchCalls: [],
  self: { crypto: { randomUUID: () => 'uid-del' } },
  returnUrl: '',
  location: { href: '', search: '' },
  postSession: () => Promise.resolve(),
  deleteCalls: 0,
  deleteSession: () => {
    ctx2d.deleteCalls += 1;
    return Promise.reject(new Error('delete failed'));
  },
  alert: () => {},
  console,
  UIkit: { notification: opts => { ctx2d.notifications.push(opts); } },
  notifications: [],
  document: {
    getElementById(id) {
      if (id === 'playerName') return ctx2d.nameInput;
      if (id === 'save-name') return { addEventListener: () => {} };
      if (id === 'delete-name') return { addEventListener: (ev, fn) => { ctx2d.deleteHandler = fn; } };
      return null;
    },
    addEventListener(ev, fn) {
      if (ev === 'DOMContentLoaded') fn();
    }
  }
};
ctx2d.fetch = () => Promise.resolve();
ctx2d.window.location = ctx2d.location;
ctx2d.Math = Object.create(Math);
ctx2d.Math.random = () => 0.6789;
vm.runInNewContext(storageCode, ctx2d);
ctx2d.setStored(ctx2d.STORAGE_KEYS.PLAYER_NAME, 'Alice');
ctx2d.setStored(ctx2d.STORAGE_KEYS.PLAYER_UID, 'uid-del');
vm.runInNewContext(profileCode, ctx2d);
(async () => {
  await new Promise(r => setTimeout(r, 0));
  assert.strictEqual(ctx2d.nameInput.value, 'Alice');
  assert.strictEqual(ctx2d.getStored(ctx2d.STORAGE_KEYS.PLAYER_NAME), 'Alice');
  await ctx2d.deleteHandler?.({ preventDefault() {} });
  assert.strictEqual(ctx2d.deleteCalls, 1);
  assert.strictEqual(ctx2d.getStored(ctx2d.STORAGE_KEYS.PLAYER_NAME), 'Alice');
  assert.strictEqual(ctx2d.getStored(ctx2d.STORAGE_KEYS.PLAYER_UID), 'uid-del');
  assert.strictEqual(ctx2d.nameInput.value, 'Alice');
  assert.strictEqual(ctx2d.notifications.length, 1);
  assert.strictEqual(ctx2d.notifications[0].message, 'Fehler beim Löschen');
  assert.strictEqual(ctx2d.notifications[0].status, 'danger');
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });

// Test no redirect on failed save
const storageObj2b = {
  data: {},
  getItem(k) { return this.data[k] ?? null; },
  setItem(k, v) { this.data[k] = String(v); },
  removeItem(k) { delete this.data[k]; }
};
const ctx2b = {
  URLSearchParams,
  window: { quizConfig: { event_uid: '' } },
  nameInput: { value: '' },
  localStorage: storageObj2b,
  sessionStorage: storageObj2b,
  fetchCalls: [],
  self: { crypto: { randomUUID: () => 'uid-123' } },
  returnUrl: '/quiz',
  location: { href: '', search: '' },
  postSession: () => Promise.reject(new Error('fail')),
  deleteSession: () => Promise.resolve(),

  alert: () => {},
  console,
  document: {
    getElementById(id) {
      if (id === 'playerName') return ctx2b.nameInput;
      if (id === 'save-name') return { addEventListener: (ev, fn) => { ctx2b.saveHandler = fn; } };
      if (id === 'delete-name') return { addEventListener: (ev, fn) => { ctx2b.deleteHandler = fn; } };
      return null;
    },
    addEventListener(ev, fn) {
      if (ev === 'DOMContentLoaded') fn();
    }
  }
};
ctx2b.fetch = (url, opts) => { ctx2b.fetchCalls.push({ url, opts }); return Promise.resolve(); };
ctx2b.window.location = ctx2b.location;
vm.runInNewContext(storageCode, ctx2b);
vm.runInNewContext(profileCode, ctx2b);
(async () => {
  await new Promise(r => setTimeout(r, 0));
  ctx2b.nameInput.value = 'Alice';
  await ctx2b.saveHandler?.({ preventDefault() {} });
  assert.strictEqual(ctx2b.location.href, '');
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
  sessionStorage: {
    data: {},
    getItem(k) { return this.data[k] ?? null; },
    setItem(k, v) { this.data[k] = String(v); },
    removeItem(k) { delete this.data[k]; }
  },
  fetchCalls: [],
  self: { crypto: { randomUUID: () => 'uid-123' } },
  returnUrl: '/quiz',
  location: { href: '', search: '?uid=uid-123' },
  postSession: () => Promise.resolve(),
  deleteSession: () => Promise.resolve(),
  alert: () => {},
  console,
  document: {
    getElementById(id) {
      if (id === 'playerName') return ctx3.nameInput;
      if (id === 'save-name') return { addEventListener: () => {} };
      if (id === 'delete-name') return { addEventListener: (ev, fn) => { ctx3.deleteHandler = fn; } };
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
vm.runInNewContext(storageCode, ctx3);
vm.runInNewContext(profileCode, ctx3);
(async () => {
  await new Promise(r => setTimeout(r, 0));
  assert.strictEqual(ctx3.localStorage.getItem('quizUser'), 'Bob');
  assert.strictEqual(ctx3.getStored(ctx3.STORAGE_KEYS.PLAYER_NAME), 'Bob');
  assert.strictEqual(ctx3.localStorage.getItem('qr_player_uid:ev1'), 'uid-123');
  assert.strictEqual(ctx3.location.href, '/quiz');
  assert(ctx3.fetchCalls[0].startsWith('/api/players?'));
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
