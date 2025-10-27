const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

class FakeElement {
  constructor(tagName, doc) {
    this.tagName = String(tagName || 'div').toUpperCase();
    this.document = doc;
    this.children = [];
    this.parentNode = null;
    this.attributes = {};
    this.dataset = {};
    this.eventListeners = {};
    this.value = '';
    this.checked = false;
    this.placeholder = '';
  }

  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);
    return child;
  }

  setAttribute(name, value) {
    const normalized = value === undefined ? '' : String(value);
    this.attributes[name] = normalized;
    if (name === 'id' && this.document) {
      this.document.registerId(normalized, this);
    }
    if (name.startsWith('data-')) {
      const key = name
        .slice(5)
        .replace(/-([a-z])/g, (_, ch) => ch.toUpperCase());
      this.dataset[key] = normalized;
    }
    if (name === 'value') {
      this.value = normalized;
    }
  }

  getAttribute(name) {
    if (Object.prototype.hasOwnProperty.call(this.attributes, name)) {
      return this.attributes[name];
    }
    if (name.startsWith('data-')) {
      const key = name
        .slice(5)
        .replace(/-([a-z])/g, (_, ch) => ch.toUpperCase());
      return this.dataset[key];
    }
    if (name === 'value') {
      return this.value;
    }
    return undefined;
  }

  matches(selector) {
    const attrMatch = selector.match(/^\[([^=\]]+)(?:="([^"]*)")?\]$/);
    if (!attrMatch) {
      return false;
    }
    const [, rawAttr, expected] = attrMatch;
    const actual = this.getAttribute(rawAttr);
    if (actual === undefined) {
      return false;
    }
    if (expected === undefined) {
      return true;
    }
    return actual === expected;
  }

  querySelectorAll(selector) {
    const results = [];
    const visit = (node) => {
      node.children.forEach((child) => {
        if (child.matches(selector)) {
          results.push(child);
        }
        visit(child);
      });
    };
    visit(this);
    return results;
  }

  querySelector(selector) {
    const matches = this.querySelectorAll(selector);
    return matches.length ? matches[0] : null;
  }
}

class FakeDocument {
  constructor(root = null) {
    this.root = root;
    this.elements = {};
  }

  setRoot(element) {
    this.root = element;
  }

  registerId(id, element) {
    if (id) {
      this.elements[id] = element;
    }
  }

  getElementById(id) {
    return this.elements[id] || null;
  }

  createElement(tagName) {
    return new FakeElement(tagName, this);
  }

  querySelector(selector) {
    if (!this.root) {
      return null;
    }
    if (this.root.matches(selector)) {
      return this.root;
    }
    const matches = this.root.querySelectorAll(selector);
    return matches.length ? matches[0] : null;
  }
}

const modulesList = new FakeElement('ul');
modulesList.setAttribute('data-dashboard-modules', '1');
const document = new FakeDocument(modulesList);
modulesList.document = document;

const infoModule = new FakeElement('li', document);
infoModule.setAttribute('data-module-id', 'infoBanner');
modulesList.appendChild(infoModule);

const infoToggle = new FakeElement('input', document);
infoToggle.type = 'checkbox';
infoToggle.setAttribute('data-module-toggle', '1');
infoToggle.checked = false;
infoModule.appendChild(infoToggle);

const infoLayout = new FakeElement('select', document);
infoLayout.setAttribute('data-module-layout', '');
infoLayout.setAttribute('data-default-layout', 'auto');
infoLayout.value = 'auto';
infoModule.appendChild(infoLayout);

const infoTitle = new FakeElement('input', document);
infoTitle.setAttribute('data-module-title', '');
infoTitle.placeholder = 'Hinweise';
infoTitle.value = '';
infoModule.appendChild(infoTitle);

const code = fs.readFileSync('public/js/admin.js', 'utf8');
const start = code.indexOf("const dashboardModulesList = document.querySelector('[data-dashboard-modules]');");
if (start === -1) {
  throw new Error('dashboard modules block not found');
}
const end = code.indexOf('function applyDashboardModules', start);
if (end === -1) {
  throw new Error('applyDashboardModules block not found');
}
const snippet = code.slice(start, end);

const context = vm.createContext({
  console,
  window: { UIkit: null },
  document,
  cfgFields: new Proxy({}, {
    get: () => null,
    set: () => true,
  }),
  cfgInitial: { dashboardShareToken: '', dashboardSponsorToken: '' },
  settingsInitial: {},
  transRagChatTokenSaved: '',
  transRagChatTokenMissing: '',
  ragChatTokenPlaceholder: '',
  apiFetch: () => Promise.resolve({ ok: true, json: async () => [] }),
  setTimeout: () => 0,
  clearTimeout: () => {},
  setInterval: () => 0,
  clearInterval: () => {},
});

vm.runInContext(snippet, context);

infoToggle.checked = true;
const modules = context.readDashboardModules();
assert.strictEqual(modules.length, 1, 'info module should be collected');
assert.strictEqual(modules[0].id, 'infoBanner');
assert.strictEqual(modules[0].enabled, true);
assert.strictEqual(modules[0].layout, 'auto');
assert.strictEqual(modules[0].options.title, 'Hinweise');

infoToggle.checked = false;
infoLayout.value = 'wide';
infoTitle.value = '';
context.renderDashboardModulesList(modules);

assert.strictEqual(infoToggle.checked, true, 'info module visibility should persist');
assert.strictEqual(infoLayout.value, 'auto', 'info module layout should be restored');
assert.strictEqual(infoTitle.value, 'Hinweise', 'info module title should use stored value');
