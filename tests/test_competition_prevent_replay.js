const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

class Element {
  constructor(tag) {
    this.tagName = tag.toUpperCase();
    this.children = [];
    this.style = {};
  }
  appendChild(child) {
    this.children.push(child);
    return child;
  }
}

const storage = () => {
  const data = {};
  return {
    getItem: k => (k in data ? data[k] : null),
    setItem: (k, v) => { data[k] = String(v); },
    removeItem: k => { delete data[k]; }
  };
};

const sessionStorage = storage();
const localStorage = storage();

const body = new Element('body');

const document = {
  readyState: 'loading',
  getElementById: () => null,
  querySelector: sel => (sel === 'main' ? body : null),
  createElement: tag => new Element(tag),
  addEventListener: () => {},
  body
};

let warnings = 0;
const UIkit = { notification: () => { warnings++; } };
let started = 0;

const window = {
  document,
  location: { search: '?slug=slug1' },
  quizConfig: { competitionMode: true, event_uid: 'event1' },
  basePath: '',
  startQuiz: () => { started++; }
};
window.window = window;

const context = {
  window,
  document,
  sessionStorage,
  localStorage,
  fetch: async () => ({ ok: true, json: async () => [] }),
  UIkit,
  console,
  URLSearchParams,
  withBase: p => p
};
context.global = context;

(async () => {
  vm.runInNewContext(fs.readFileSync('public/js/storage.js', 'utf8'), context);
  context.setStored(context.STORAGE_KEYS.QUIZ_SOLVED, JSON.stringify(['slug1']));
  vm.runInNewContext(fs.readFileSync('public/js/catalog.js', 'utf8'), context);
  await context.init();
  assert.strictEqual(warnings, 1);
  assert.strictEqual(started, 0);
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
