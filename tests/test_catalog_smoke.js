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
const header = new Element('div');
header.id = 'quiz-header';
const quiz = new Element('div');
quiz.id = 'quiz';

const elements = {
  'quiz-header': header,
  'quiz': quiz
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
  quizConfig: {},
  quizCatalogs: [
    {
      slug: 'valid',
      file: 'file',
      uid: 'uid',
      sort_order: 1,
      name: 'Valid',
      description: 'Desc',
      comment: 'Comment'
    }
  ],
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
  URLSearchParams,
  promptTeamName: async () => {},
  generateUserName: () => {}
};
context.window.window = context.window; // self-reference
context.global = context;

(async () => {
  vm.runInNewContext(fs.readFileSync('public/js/catalog.js', 'utf8'), context);
  await new Promise(r => setTimeout(r, 0));

  const commentBlock = header.querySelector('div[data-role="catalog-comment-block"]');
  if (!commentBlock || commentBlock.textContent !== 'Comment') {
    throw new Error('comment not rendered');
  }
  const button = quiz.children.find(c => c.tagName === 'BUTTON');
  if (!button) {
    throw new Error('start button missing');
  }
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
