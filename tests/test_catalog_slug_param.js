const fs = require('fs');
const vm = require('vm');

class Element {
  constructor(tag) {
    this.tagName = tag.toUpperCase();
    this.children = [];
    this.dataset = {};
    this.className = '';
    this.classList = { add: () => {}, remove: () => {} };
    this.style = {};
    this.textContent = '';
  }
  appendChild(child) {
    this.children.push(child);
    return child;
  }
  querySelector(sel) {
    if (sel === 'div[data-role="catalog-comment-block"]') {
      return this.children.find(
        c => c.tagName === 'DIV' && c.dataset.role === 'catalog-comment-block'
      ) || null;
    }
    return null;
  }
  addEventListener() {}
}

class SelectElement extends Element {
  constructor() {
    super('select');
    this.options = [];
    this.value = '';
  }
  appendChild(child) {
    if (child.tagName === 'OPTION') {
      this.options.push(child);
    }
    return super.appendChild(child);
  }
  addEventListener() {}
  get selectedOptions() {
    return this.options.filter(o => o.value === this.value);
  }
}

class OptionElement extends Element {
  constructor(value, text) {
    super('option');
    this.value = value;
    this.textContent = text;
  }
}

const header = new Element('div');
header.id = 'quiz-header';
const quiz = new Element('div');
quiz.id = 'quiz';
const select = new SelectElement();
select.id = 'catalog-select';

const opt = new OptionElement('valid', 'Valid');
opt.dataset.slug = 'valid';
opt.dataset.file = 'file.json';
opt.dataset.uid = 'uid';
opt.dataset.sortOrder = '1';
opt.dataset.desc = 'Desc';
opt.dataset.comment = 'Comment';
select.appendChild(opt);

const elements = {
  'quiz-header': header,
  quiz,
  'catalog-select': select
};

let capturedInit = null;
const document = {
  readyState: 'loading',
  getElementById: id => elements[id] || null,
  querySelector: () => null,
  createElement: tag => new Element(tag),
  addEventListener: (ev, fn) => {
    if (ev === 'DOMContentLoaded') capturedInit = fn;
  },
  head: new Element('head')
};

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

const window = {
  location: { search: '?slug=valid' },
  quizConfig: {},
  basePath: '',
  startQuiz: () => {},
  document
};

const context = {
  window,
  document,
  sessionStorage,
  localStorage,
  fetch: async () => ({ json: async () => [] }),
  alert: () => {},
  UIkit: {},
  console,
  URLSearchParams
};
context.window.window = context.window; // self-reference
context.global = context;

(async () => {
  vm.runInNewContext(fs.readFileSync('public/js/catalog.js', 'utf8'), context);
  if (typeof capturedInit !== 'function') {
    throw new Error('init not captured');
  }
  capturedInit();
  await new Promise(r => setTimeout(r, 0));

  if (select.value !== 'valid') {
    throw new Error('selection by slug failed');
  }
  const comment = quiz.children.find(c => c.tagName === 'P' && c.textContent === 'Comment');
  if (!comment) {
    throw new Error('catalog comment missing');
  }
  const button = quiz.children.find(
    c => c.tagName === 'BUTTON' && c.textContent === "Los geht's!"
  );
  if (!button) {
    throw new Error("start button missing");
  }
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });

