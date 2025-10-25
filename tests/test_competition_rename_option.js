const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

class Element {
  constructor(tag) {
    this.tagName = tag.toUpperCase();
    this.children = [];
    this.attributes = {};
    this.dataset = {};
    this.className = '';
    this.classList = {
      add: () => {},
      remove: () => {},
      toggle: () => {}
    };
    this.style = {};
    this.textContent = '';
    this.listeners = {};
    this.parentNode = null;
    this._innerHTML = '';
    if (this.tagName === 'INPUT') {
      this.value = '';
      this.type = 'text';
      this.placeholder = '';
    }
  }

  set innerHTML(value) {
    this._innerHTML = String(value);
    this.textContent = this._innerHTML.replace(/<[^>]*>/g, '') || '';
  }

  get innerHTML() {
    return this._innerHTML;
  }

  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);
    return child;
  }

  setAttribute(name, value) {
    this.attributes[name] = value;
    if (name === 'id') {
      this.id = value;
    }
  }

  getAttribute(name) {
    return Object.prototype.hasOwnProperty.call(this.attributes, name)
      ? this.attributes[name]
      : undefined;
  }

  addEventListener(event, handler) {
    if (!this.listeners[event]) {
      this.listeners[event] = [];
    }
    this.listeners[event].push(handler);
  }

  remove() {
    if (!this.parentNode) {
      return;
    }
    const idx = this.parentNode.children.indexOf(this);
    if (idx >= 0) {
      this.parentNode.children.splice(idx, 1);
    }
    this.parentNode = null;
  }

  focus() {
    this.focused = true;
  }

  select() {
    this.selected = true;
  }
}

function createStorage() {
  const data = {};
  return {
    getItem(key) {
      return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null;
    },
    setItem(key, value) {
      data[key] = String(value);
    },
    removeItem(key) {
      delete data[key];
    }
  };
}

function findById(root, id) {
  if (!root) {
    return null;
  }
  if (root.id === id) {
    return root;
  }
  for (const child of root.children) {
    const found = findById(child, id);
    if (found) {
      return found;
    }
  }
  return null;
}

const sessionStorage = createStorage();
const localStorage = createStorage();

const body = new Element('body');
const teamNameBtn = new Element('button');
teamNameBtn.id = 'teamNameBtn';
body.appendChild(teamNameBtn);

const document = {
  readyState: 'complete',
  body,
  createElement: tag => new Element(tag),
  getElementById: id => (id === 'teamNameBtn' ? teamNameBtn : null),
  addEventListener: () => {}
};

const fetchCalls = [];
const postSessionCalls = [];

const UIkit = {
  notifications: [],
  notification(opts) {
    this.notifications.push(opts);
  },
  util: {
    on(element, event, handler) {
      if (!element._handlers) {
        element._handlers = {};
      }
      if (!element._handlers[event]) {
        element._handlers[event] = [];
      }
      element._handlers[event].push(handler);
    }
  },
  modal(element) {
    if (!element._handlers) {
      element._handlers = {};
    }
    return {
      show() {
        const handlers = element._handlers.shown || [];
        handlers.forEach(fn => fn());
      },
      hide() {
        const handlers = element._handlers.hidden || [];
        handlers.forEach(fn => fn());
      }
    };
  }
};

const window = {
  quizConfig: {
    competitionMode: true,
    randomNames: true,
    event_uid: 'event-rename'
  },
  basePath: '',
  location: { search: '' },
  document
};
window.window = window;

const context = {
  window,
  document,
  sessionStorage,
  localStorage,
  fetch: async (url, opts = {}) => {
    fetchCalls.push({ url, opts });
    return { ok: true, json: async () => ({}) };
  },
  postSession: async (path, payload) => {
    postSessionCalls.push({ path, payload });
  },
  alert: () => {},
  UIkit,
  console,
  setTimeout,
  clearTimeout,
  URLSearchParams,
  Math: Object.create(Math),
  TeamNameClient: null,
  self: { crypto: { randomUUID: () => 'uuid-self' } },
  crypto: { randomUUID: () => 'uuid-global' }
};
context.Math.random = () => 0.123456789;
context.window.location = window.location;
context.window.quizConfig = window.quizConfig;
context.window.basePath = '';
context.window.window = context.window;
context.global = context;
context.globalThis = context;
context.window.self = context.self;

const storageCode = fs.readFileSync('public/js/storage.js', 'utf8');
vm.runInNewContext(storageCode, context);

const clearedKeys = [];
const originalClearStored = context.clearStored;
context.clearStored = key => {
  clearedKeys.push(key);
  return originalClearStored(key);
};

context.setStored('quizUser', 'Team Eins');
context.setStored(context.STORAGE_KEYS.PLAYER_UID, 'uid-existing');

const quizCode = fs.readFileSync('public/js/quiz.js', 'utf8');
vm.runInNewContext(quizCode, context);

(async () => {
  const promptPromise = context.promptTeamName();

  const initialModal = body.children.find(child => child !== teamNameBtn);
  if (!initialModal) {
    throw new Error('initial modal not rendered');
  }

  const renameBtn = findById(initialModal, 'team-name-reset');
  if (!renameBtn) {
    throw new Error('rename button missing');
  }
  assert.strictEqual(renameBtn.textContent, 'Name ändern');

  const renameHandler = renameBtn.listeners.click?.[0];
  if (typeof renameHandler !== 'function') {
    throw new Error('rename handler missing');
  }
  await renameHandler();

  const renameModal = body.children.find(child => child !== teamNameBtn);
  if (!renameModal || renameModal === initialModal) {
    throw new Error('rename modal not rendered');
  }

  const input = findById(renameModal, 'team-name-input');
  const submitBtn = findById(renameModal, 'team-name-submit');
  if (!input || !submitBtn) {
    throw new Error('rename modal controls missing');
  }

  input.value = 'Team Zwei';
  const submitHandler = submitBtn.listeners.click?.[0];
  if (typeof submitHandler !== 'function') {
    throw new Error('submit handler missing');
  }
  await submitHandler();

  await promptPromise;

  assert.strictEqual(context.getStored('quizUser'), 'Team Zwei');
  assert.strictEqual(context.getStored(context.STORAGE_KEYS.PLAYER_UID), 'uid-existing');
  assert(!clearedKeys.includes('quizUser'));
  assert.strictEqual(teamNameBtn.textContent, 'Team Zwei');
  assert.strictEqual(postSessionCalls.length, 1);
  assert.strictEqual(postSessionCalls[0].path, 'player');
  assert.strictEqual(postSessionCalls[0].payload && postSessionCalls[0].payload.name, 'Team Zwei');
  assert.strictEqual(fetchCalls.length, 1);
  assert(fetchCalls[0].opts && typeof fetchCalls[0].opts.body === 'string' && fetchCalls[0].opts.body.includes('Team Zwei'));

  console.log('ok');
})().catch(err => {
  console.error(err);
  process.exit(1);
});
