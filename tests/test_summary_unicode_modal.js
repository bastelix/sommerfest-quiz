const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const { URLSearchParams } = require('url');

class FakeTextNode {
  constructor(text, doc) {
    this.ownerDocument = doc;
    this.parentNode = null;
    this.tagName = '#TEXT';
    this.children = [];
    this._text = String(text);
  }

  get textContent() {
    return this._text;
  }

  set textContent(value) {
    this._text = String(value);
  }

  remove() {
    if (this.parentNode) {
      const idx = this.parentNode.children.indexOf(this);
      if (idx >= 0) {
        this.parentNode.children.splice(idx, 1);
      }
      this.parentNode = null;
    }
  }
}

class FakeElement {
  constructor(tagName, doc) {
    this.ownerDocument = doc;
    this.tagName = tagName.toUpperCase();
    this.children = [];
    this.parentNode = null;
    this.attributes = {};
    this.dataset = {};
    this.eventListeners = {};
    this.style = {};
    this.hidden = false;
    this.disabled = false;
    this.value = '';
    this.checked = false;
    this.type = '';
    this.href = '';
    this.target = '';
    this.files = [];
    this._textContent = '';
    this._id = '';
    this._classList = new Set();

    const syncClassAttr = () => {
      const val = Array.from(this._classList).join(' ');
      if (val) {
        this.attributes.class = val;
      } else {
        delete this.attributes.class;
      }
    };

    Object.defineProperty(this, 'className', {
      get: () => Array.from(this._classList).join(' '),
      set: value => {
        this._classList.clear();
        String(value)
          .split(/\s+/)
          .forEach(cls => {
            if (cls) this._classList.add(cls);
          });
        syncClassAttr();
      }
    });

    this.classList = {
      add: (...names) => {
        names.forEach(name => {
          if (name) this._classList.add(name);
        });
        syncClassAttr();
      },
      remove: (...names) => {
        names.forEach(name => this._classList.delete(name));
        syncClassAttr();
      },
      contains: name => this._classList.has(name)
    };
  }

  get id() {
    return this._id;
  }

  set id(value) {
    const val = String(value);
    if (this._id) {
      this.ownerDocument.unregisterId(this._id, this);
    }
    this._id = val;
    if (val) {
      this.ownerDocument.registerId(val, this);
      this.attributes.id = val;
    } else {
      delete this.attributes.id;
    }
  }

  get textContent() {
    if (this.children.length) {
      return this.children.map(child => child.textContent || '').join('');
    }
    return this._textContent;
  }

  set textContent(value) {
    this._textContent = String(value);
    this.children = [];
  }

  appendChild(child) {
    if (child === null || child === undefined) return null;
    if (typeof child === 'string') {
      return this.appendChild(this.ownerDocument.createTextNode(child));
    }
    if (child.parentNode) {
      const idx = child.parentNode.children.indexOf(child);
      if (idx >= 0) {
        child.parentNode.children.splice(idx, 1);
      }
    }
    child.parentNode = this;
    this.children.push(child);
    this._textContent = '';
    return child;
  }

  append(...nodes) {
    nodes.forEach(node => {
      if (node === null || node === undefined) return;
      if (typeof node === 'string') {
        this.appendChild(this.ownerDocument.createTextNode(node));
      } else {
        this.appendChild(node);
      }
    });
  }

  remove() {
    if (this.parentNode) {
      const idx = this.parentNode.children.indexOf(this);
      if (idx >= 0) {
        this.parentNode.children.splice(idx, 1);
      }
      this.parentNode = null;
    }
  }

  setAttribute(name, value) {
    const val = String(value);
    this.attributes[name] = val;
    if (name === 'id') {
      this.id = val;
    } else if (name === 'class') {
      this.className = val;
    } else if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      this.dataset[key] = val;
    } else if (name === 'href') {
      this.href = val;
    } else if (name === 'target') {
      this.target = val;
    } else if (name === 'type') {
      this.type = val;
    }
  }

  getAttribute(name) {
    if (name === 'id') return this.id;
    if (name === 'class') return this.className;
    if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      return this.dataset[key];
    }
    return this.attributes[name];
  }

  addEventListener(type, handler) {
    (this.eventListeners[type] ||= []).push(handler);
  }

  dispatchEvent(event) {
    const evt = typeof event === 'string' ? { type: event } : event;
    const listeners = this.eventListeners[evt.type] || [];
    listeners.forEach(fn => fn({ ...evt, target: this }));
  }
}

class FakeDocument {
  constructor() {
    this.elements = {};
    this.eventListeners = {};
    this.body = new FakeElement('body', this);
  }

  createElement(tagName) {
    return new FakeElement(tagName, this);
  }

  createTextNode(text) {
    return new FakeTextNode(text, this);
  }

  registerId(id, element) {
    this.elements[id] = element;
  }

  unregisterId(id, element) {
    if (this.elements[id] === element) {
      delete this.elements[id];
    }
  }

  getElementById(id) {
    return this.elements[id] || null;
  }

  addEventListener(type, handler) {
    (this.eventListeners[type] ||= []).push(handler);
  }

  dispatchEvent(type, detail = {}) {
    const listeners = this.eventListeners[type] || [];
    listeners.forEach(handler => handler({ type, ...detail }));
  }
}

function findAllByTag(node, tagName, acc = []) {
  if (!node || !node.children) return acc;
  if (node.tagName === tagName.toUpperCase()) {
    acc.push(node);
  }
  node.children.forEach(child => findAllByTag(child, tagName, acc));
  return acc;
}

async function runTest() {
  const document = new FakeDocument();
  const windowObj = {
    document,
    quizConfig: {},
    basePath: '',
    location: { search: '', href: '' }
  };

  const STORAGE_KEYS = {
    PLAYER_NAME: 'quizUser',
    PLAYER_UID: 'playerUid',
    CATALOG: 'quizCatalog',
    CATALOG_NAME: 'quizCatalogName',
    CATALOG_DESC: 'quizCatalogDesc',
    CATALOG_COMMENT: 'quizCatalogComment',
    CATALOG_UID: 'quizCatalogUid',
    CATALOG_SORT: 'quizCatalogSort',
    LETTER: 'quizLetter',
    PUZZLE_SOLVED: 'puzzleSolved',
    PUZZLE_TIME: 'puzzleTime',
    QUIZ_SOLVED: 'quizSolved'
  };

  const storage = new Map();
  const getStored = key => (storage.has(key) ? storage.get(key) : null);
  const setStored = (key, value) => { storage.set(key, value); };
  const clearStored = key => { storage.delete(key); };

  setStored(STORAGE_KEYS.PLAYER_NAME, 'Jäger');

  const catalogs = [{ uid: 'cat-1', name: 'Katalog Eins', slug: 'katalog-eins' }];
  const results = [{
    name: 'Jäger',
    catalog: 'cat-1',
    catalogName: 'Katalog Eins',
    points: 5,
    total: 5,
    correct: 5,
    attempt: 1,
    max_points: 5,
    puzzleTime: 1700000000
  }];
  const questionResults = [{
    name: 'Jäger',
    catalog: 'cat-1',
    attempt: 1,
    catalogName: 'Katalog Eins',
    final_points: 5,
    points: 5,
    questionPoints: 5,
    efficiency: 1,
    correct: 1,
    total: 1
  }];

  const fetch = url => {
    const path = typeof url === 'string' ? url : url.url || '';
    if (path.includes('/kataloge/catalogs.json')) {
      return Promise.resolve({ ok: true, json: () => Promise.resolve(catalogs) });
    }
    if (path.includes('/question-results.json')) {
      return Promise.resolve({ ok: true, json: () => Promise.resolve(questionResults) });
    }
    if (path.includes('/results.json')) {
      return Promise.resolve({ ok: true, json: () => Promise.resolve(results) });
    }
    return Promise.reject(new Error(`Unexpected fetch ${path}`));
  };

  const UIkit = {
    modal(element) {
      return {
        element,
        show() {
          element.dispatchEvent('shown');
        },
        hide() {
          element.dispatchEvent('hidden');
        }
      };
    },
    util: {
      on(element, event, handler) {
        element.addEventListener(event, handler);
      }
    }
  };

  const context = {
    window: windowObj,
    document,
    UIkit,
    STORAGE_KEYS,
    getStored,
    setStored,
    clearStored,
    fetch,
    console,
    URLSearchParams,
    setTimeout,
    clearTimeout,
    setInterval,
    clearInterval
  };
  context.global = context;
  context.globalThis = context;

  windowObj.window = windowObj;
  windowObj.fetch = fetch;
  windowObj.URLSearchParams = URLSearchParams;
  windowObj.setTimeout = setTimeout;
  windowObj.clearTimeout = clearTimeout;
  windowObj.setInterval = setInterval;
  windowObj.clearInterval = clearInterval;

  const code = fs.readFileSync('public/js/summary.js', 'utf8');
  vm.runInNewContext(code, context);

  document.dispatchEvent('DOMContentLoaded');

  const flush = () => new Promise(resolve => setTimeout(resolve, 0));
  await flush();
  await flush();

  const modal = document.body.children[0];
  assert.ok(modal, 'modal not created');
  const dialog = modal.children[0];
  assert.ok(dialog, 'dialog missing');
  const userParagraph = dialog.children[1];
  assert.ok(userParagraph, 'user paragraph missing');
  assert.strictEqual(userParagraph.textContent, 'Jäger');

  const contentWrap = document.getElementById('team-results');
  assert.ok(contentWrap, 'team results container missing');

  const tables = findAllByTag(contentWrap, 'TABLE');
  assert.ok(tables.length > 0, 'no tables rendered');
  const summaryTable = tables.find(table => {
    const thead = table.children.find(child => child.tagName === 'THEAD');
    if (!thead || !thead.children.length) return false;
    const headerRow = thead.children[0];
    return headerRow && headerRow.children.length && headerRow.children[0].textContent === 'Katalog';
  });
  assert.ok(summaryTable, 'summary table not found');
  const tbody = summaryTable.children.find(child => child.tagName === 'TBODY');
  assert.ok(tbody, 'summary table body missing');
  assert.ok(tbody.children.length > 0, 'summary rows missing');
  const firstRow = tbody.children[0];
  assert.ok(firstRow.children.length >= 3, 'summary row incomplete');
  const catalogLink = firstRow.children[0].children[0];
  assert.ok(catalogLink, 'catalog link missing');
  assert.strictEqual(catalogLink.textContent, 'Katalog Eins');
  const pointsCell = firstRow.children[2];
  assert.strictEqual(pointsCell.textContent, '5/5');
}

runTest().then(() => {
  console.log('ok');
}).catch(err => {
  console.error(err);
  process.exit(1);
});
