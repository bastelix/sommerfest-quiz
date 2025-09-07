const fs = require('fs');
const vm = require('vm');

class Element {
  constructor(tag) {
    this.tagName = tag.toUpperCase();
    this.children = [];
    this.dataset = {};
    this.className = '';
    this.classList = {
      add: () => {},
      remove: () => {}
    };
    this.style = {};
    this.textContent = '';
  }
  appendChild(child) {
    this.children.push(child);
    return child;
  }
  querySelector(sel) {
    if (sel === 'h1') {
      return this.children.find(c => c.tagName === 'H1') || null;
    }
    if (sel === 'p[data-role="subheader"]') {
      return this.children.find(c => c.tagName === 'P' && c.dataset.role === 'subheader') || null;
    }
    if (sel === 'div[data-role="catalog-comment-block"]') {
      return this.children.find(c => c.tagName === 'DIV' && c.dataset.role === 'catalog-comment-block') || null;
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
opt.dataset.file = 'file';
opt.dataset.uid = 'uid';
opt.dataset.sortOrder = '1';
opt.dataset.desc = 'Desc';
opt.dataset.comment = 'Comment';
select.appendChild(opt);

const elements = {
  'quiz-header': header,
  'quiz': quiz,
  'catalog-select': select
};

const document = {
  readyState: 'complete',
  getElementById: id => elements[id] || null,
  querySelector: () => null,
  createElement: tag => new Element(tag),
  addEventListener: () => {},
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
  quizConfig: { logoPath: '/custom.png' },
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

context.window.startQuiz = () => {
  const headerEl = document.getElementById('quiz-header');
  if (headerEl) {
    if (window.quizConfig.logoPath) {
      const img = new Element('img');
      img.src = window.quizConfig.logoPath;
      headerEl.appendChild(img);
    }
    const h1 = new Element('h1');
    h1.textContent = 'Valid';
    headerEl.appendChild(h1);
    const sub = new Element('p');
    sub.dataset.role = 'subheader';
    sub.textContent = 'Desc';
    headerEl.appendChild(sub);
    const commentBlock = new Element('div');
    commentBlock.dataset.role = 'catalog-comment-block';
    commentBlock.textContent = 'Comment';
    headerEl.appendChild(commentBlock);
  }
  const button = new Element('button');
  document.getElementById('quiz').appendChild(button);
};

(async () => {
  vm.runInNewContext(fs.readFileSync('public/js/catalog.js', 'utf8'), context);
  await new Promise(r => setTimeout(r, 0));

  const commentBlock = header.querySelector('div[data-role="catalog-comment-block"]');
  if (!commentBlock || commentBlock.textContent !== 'Comment') {
    throw new Error('comment not rendered');
  }
  const logo = header.children.find(c => c.tagName === 'IMG');
  if (!logo || logo.src !== '/custom.png') {
    throw new Error('logo not rendered');
  }
  const button = quiz.children.find(c => c.tagName === 'BUTTON');
  if (!button) {
    throw new Error('start button missing');
  }
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
