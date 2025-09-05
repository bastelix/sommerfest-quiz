const fs = require('fs');
const vm = require('vm');

class Element {
  constructor(tag) {
    this.tagName = tag.toUpperCase();
    this.children = [];
    this.dataset = {};
    this.style = {};
    this.textContent = '';
  }
  appendChild(child) {
    this.children.push(child);
    return child;
  }
  addEventListener() {}
  querySelector() { return null; }
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

const inline = new Element('script');
inline.id = 'valid-data';
inline.textContent = '[]';

const quiz = new Element('div');
quiz.id = 'quiz';

const elements = { 'valid-data': inline, quiz };
let initFn = null;
const document = {
  readyState: 'loading',
  getElementById: id => elements[id] || null,
  querySelector: () => null,
  createElement: tag => new Element(tag),
  addEventListener: (ev, fn) => { if (ev === 'DOMContentLoaded') initFn = fn; },
  head: new Element('head'),
  body: new Element('body')
};

document.body.appendChild(quiz);

const window = {
  location: { search: '?slug=valid' },
  basePath: '',
  document,
  startQuiz: () => {}
};

const context = {
  window,
  document,
  sessionStorage,
  localStorage,
  fetch: async () => ({ json: async () => [] }),
  UIkit: {},
  alert: () => {},
  console,
  URLSearchParams
};
context.window.window = context.window;
context.global = context;

(async () => {
  vm.runInNewContext(fs.readFileSync('public/js/catalog.js', 'utf8'), context);
  context.showCatalogIntro = () => {};
  if (typeof initFn !== 'function') {
    throw new Error('init not captured');
  }
  initFn();
  await new Promise(r => setTimeout(r, 0));
  if (sessionStorage.getItem('quizCatalog') !== 'valid') {
    throw new Error('sessionStorage quizCatalog not set');
  }
  if (localStorage.getItem('quizCatalog') !== 'valid') {
    throw new Error('localStorage quizCatalog not set');
  }
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
