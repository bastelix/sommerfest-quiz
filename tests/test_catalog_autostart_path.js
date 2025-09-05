const fs = require('fs');
const vm = require('vm');

class Element {
  constructor(tag) {
    this.tagName = tag.toUpperCase();
    this.children = [];
    this.dataset = {};
    this.style = {};
    this.textContent = '';
    this.onload = null;
    this.onerror = null;
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

const storage = () => {
  const data = {};
  return {
    getItem: k => (k in data ? data[k] : null),
    setItem: (k, v) => { data[k] = String(v); },
    removeItem: k => { delete data[k]; }
  };
};

async function runScenario({ scriptFail = false, fetchFail = false }) {
  const quiz = new Element('div');
  quiz.id = 'quiz';
  const select = new SelectElement();
  select.id = 'catalog-select';
  const opt = new OptionElement('valid', 'Valid');
  opt.dataset.slug = 'valid';
  opt.dataset.file = 'file';
  select.appendChild(opt);
  select.selectedOptions = [opt];

  const elements = { quiz, 'catalog-select': select };

  const head = new Element('head');
  let startCount = 0;
  head.appendChild = child => {
    head.children.push(child);
    if (child.tagName === 'SCRIPT') {
      setTimeout(() => {
        if (scriptFail) {
          child.onerror && child.onerror(new Error('load error'));
        } else {
          window.startQuiz = () => { startCount++; };
          child.onload && child.onload();
        }
      }, 0);
    }
    return child;
  };

  const document = {
    readyState: 'complete',
    getElementById: id => elements[id] || null,
    querySelector: () => null,
    createElement: tag => new Element(tag),
    addEventListener: () => {},
    head
  };

  const sessionStorage = storage();
  const localStorage = storage();

  const window = {
    location: { search: '?autostart=1', pathname: '/catalog/valid' },
    quizConfig: {},
    basePath: '',
    document
  };

  const fetch = fetchFail
    ? async () => { throw new Error('network'); }
    : async () => ({ json: async () => [] });

  const context = {
    window,
    document,
    sessionStorage,
    localStorage,
    fetch,
    alert: () => {},
    UIkit: {},
    console,
    URLSearchParams,
    jsonHeaders: { Accept: 'application/json' }
  };
  context.window.window = context.window;
  context.global = context;

  vm.runInNewContext(fs.readFileSync('public/js/catalog.js', 'utf8'), context);
  await new Promise(r => setTimeout(r, 0));
  await new Promise(r => setTimeout(r, 0));

  const hasButton = quiz.children.some(c => c.tagName === 'BUTTON');
  return { startCount, selectValue: select.value, hasButton };
}

(async () => {
  let res = await runScenario({});
  if (res.selectValue !== 'valid') {
    throw new Error('slug not pre-selected');
  }
  if (res.startCount !== 1) {
    throw new Error('autostart did not run exactly once');
  }
  if (res.hasButton) {
    throw new Error('intro button should not be rendered');
  }

  res = await runScenario({ scriptFail: true });
  if (res.startCount !== 0) {
    throw new Error('autostart should not run on script load error');
  }

  res = await runScenario({ fetchFail: true });
  if (res.startCount !== 0) {
    throw new Error('autostart should not run on fetch error');
  }

  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
