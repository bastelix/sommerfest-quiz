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
    this._innerHTML = '';
  }
  set innerHTML(value) {
    this._innerHTML = String(value);
    this.textContent = this._innerHTML.replace(/<[^>]*>/g, '') || '';
  }
  get innerHTML() {
    return this._innerHTML;
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

const makeOption = (value, slug, text, comment) => {
  const opt = new OptionElement(value, text);
  opt.dataset.slug = slug;
  opt.dataset.file = slug + '.json';
  opt.dataset.uid = slug + '-uid';
  opt.dataset.sortOrder = value;
  opt.dataset.desc = 'Desc';
  opt.dataset.comment = comment;
  return opt;
};

select.appendChild(makeOption('first', 'first', 'First', 'First comment'));
select.appendChild(makeOption('second', 'valid', 'Valid', '&lt;em&gt;Comment&lt;/em&gt;'));

const elements = {
  'quiz-header': header,
  quiz,
  'catalog-select': select
};

let initFn = null;
const document = {
  readyState: 'loading',
  getElementById: id => elements[id] || null,
  querySelector: () => null,
  createElement: tag => new Element(tag),
  addEventListener: (ev, fn) => {
    if (ev === 'DOMContentLoaded') initFn = fn;
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

window.startQuiz = () => {
  const readStored = key => {
    if (typeof window !== 'undefined' && typeof window.getStored === 'function') {
      return window.getStored(key);
    }
    if (typeof getStored === 'function') {
      return getStored(key);
    }
    if (typeof sessionStorage !== 'undefined' && sessionStorage && typeof sessionStorage.getItem === 'function') {
      const direct = sessionStorage.getItem(key);
      if (direct != null) {
        return direct;
      }
      const scoped = sessionStorage.getItem(`${key}:`);
      if (scoped != null) {
        return scoped;
      }
    }
    if (typeof localStorage !== 'undefined' && localStorage && typeof localStorage.getItem === 'function') {
      const direct = localStorage.getItem(key);
      if (direct != null) {
        return direct;
      }
      const scoped = localStorage.getItem(`${key}:`);
      if (scoped != null) {
        return scoped;
      }
    }
    return null;
  };
  const windowKeys = (typeof window !== 'undefined' && window.STORAGE_KEYS)
    ? window.STORAGE_KEYS
    : null;
  const globalKeys = (typeof globalThis !== 'undefined' && globalThis.STORAGE_KEYS)
    ? globalThis.STORAGE_KEYS
    : (typeof STORAGE_KEYS !== 'undefined' ? STORAGE_KEYS : {});
  const keys = windowKeys && Object.keys(windowKeys).length ? windowKeys : globalKeys;
  const nameKey = keys.CATALOG_NAME || 'quizCatalogName';
  const descKey = keys.CATALOG_DESC || 'quizCatalogDesc';
  const commentKey = keys.CATALOG_COMMENT || 'quizCatalogComment';
  const headerEl = document.getElementById('quiz-header');
  if (headerEl) {
    const name = readStored(nameKey);
    if (name) {
      const h1 = new Element('h1');
      h1.textContent = name;
      headerEl.appendChild(h1);
    }
    const desc = readStored(descKey);
    if (desc) {
      const p = new Element('p');
      p.dataset.role = 'subheader';
      p.textContent = desc;
      headerEl.appendChild(p);
    }
    const storedComment = readStored(commentKey);
    if (storedComment) {
      const block = new Element('div');
      block.dataset.role = 'catalog-comment-block';
      block.innerHTML = storedComment;
      headerEl.appendChild(block);
    }
  }
  const comment = readStored(commentKey);
  if (comment) {
    const p = new Element('p');
    p.innerHTML = comment;
    quiz.appendChild(p);
  }
  const button = new Element('button');
  button.textContent = "Los geht's!";
  quiz.appendChild(button);
};

(async () => {
  vm.runInNewContext(fs.readFileSync('public/js/storage.js', 'utf8'), context);
  vm.runInNewContext(fs.readFileSync('public/js/catalog.js', 'utf8'), context);
  if (typeof initFn !== 'function') {
    throw new Error('init not captured');
  }
  initFn();
  await new Promise(r => setTimeout(r, 0));
  if (select.value !== 'second') {
    throw new Error('selection by slug failed');
  }
  const commentBlock = header.querySelector('div[data-role="catalog-comment-block"]');
  if (!commentBlock || commentBlock.innerHTML !== '<em>Comment</em>') {
    throw new Error('catalog comment missing');
  }
  const button = quiz.children.find(
    c => c.tagName === 'BUTTON' && c.textContent === "Los geht's!"
  );
  if (!button) {
    throw new Error("start button missing");
  }
  const sessionCatalog = sessionStorage.getItem('quizCatalog')
    || sessionStorage.getItem('quizCatalog:');
  if (sessionCatalog !== 'second') {
    throw new Error('sessionStorage quizCatalog not set');
  }
  const localCatalog = localStorage.getItem('quizCatalog')
    || localStorage.getItem('quizCatalog:');
  if (localCatalog !== 'second') {
    throw new Error('localStorage quizCatalog not set');
  }
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
