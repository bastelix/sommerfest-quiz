const fs = require('fs');
const vm = require('vm');

class Element {
  constructor(tag) {
    this.tagName = tag.toUpperCase();
    this.children = [];
    this.dataset = {};
    this.textContent = '';
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

let uiWarnings = 0;
const UIkit = { notification: () => { uiWarnings++; } };

const fetchCalls = [];
const fetch = async (url, opts) => {
  fetchCalls.push({ url, opts });
  return { json: async () => [] };
};

const document = {
  readyState: 'loading',
  addEventListener: () => {},
  getElementById: () => null,
  querySelector: () => null,
  createElement: tag => new Element(tag),
  head: new Element('head')
};

const window = { document, basePath: '', startQuiz: () => {}, quizQuestions: [] };

const context = {
  window,
  document,
  sessionStorage,
  localStorage,
  fetch,
  UIkit,
  alert: () => {},
  console,
  URLSearchParams
};
context.window.window = context.window;
context.global = context;

(async () => {
  vm.runInNewContext(fs.readFileSync('public/js/catalog.js', 'utf8'), context);
  // prevent DOM side effects
  context.showCatalogIntro = () => {};

  const opt = { textContent: 'Test', dataset: { file: 'foo.json', uid: '1', sortOrder: '1' } };
  await context.handleSelection(opt);

  if (fetchCalls.length !== 1) {
    throw new Error('fetch not called');
  }
  const headers = fetchCalls[0].opts && fetchCalls[0].opts.headers;
  if (!headers || headers.Accept !== 'application/json') {
    throw new Error('jsonHeaders missing');
  }
  if (uiWarnings !== 0) {
    throw new Error('UI warning triggered');
  }
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
