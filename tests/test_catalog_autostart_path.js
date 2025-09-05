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
  querySelector() { return null; }
  addEventListener() {}
}
class SelectElement extends Element {
  constructor() {
    super('select');
    this.options = [];
    this.value = '';
    this.selectedOptions = [];
  }
  appendChild(child) {
    if (child.tagName === 'OPTION') {
      this.options.push(child);
    }
    return super.appendChild(child);
  }
}
class OptionElement extends Element {
  constructor(value, text) {
    super('option');
    this.value = value;
    this.textContent = text;
  }
}

const quiz = new Element('div');
quiz.id = 'quiz';
const select = new SelectElement();
select.id = 'catalog-select';
const opt = new OptionElement('valid', 'Valid');
opt.dataset.slug = 'valid';
opt.dataset.file = 'file';
select.options.push(opt);
select.selectedOptions = [opt];

const elements = { quiz, 'catalog-select': select };

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
let started = false;

const window = {
  location: { search: '?autostart=1', pathname: '/catalog/valid' },
  quizConfig: {},
  basePath: '',
  startQuiz: () => { started = true; },
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
  URLSearchParams,
  jsonHeaders: { Accept: 'application/json' }
};
context.window.window = context.window;
context.global = context;

(async () => {
  vm.runInNewContext(fs.readFileSync('public/js/catalog.js', 'utf8'), context);
  await new Promise(r => setTimeout(r, 0));
  if (select.value !== 'valid') {
    throw new Error('slug not pre-selected');
  }
  if (!started) {
    throw new Error('autostart did not run');
  }
  const hasButton = quiz.children.some(c => c.tagName === 'BUTTON');
  if (hasButton) {
    throw new Error('intro button should not be rendered');
  }
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
