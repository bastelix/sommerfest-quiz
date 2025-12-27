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

const metricsModule = new FakeElement('li', document);
metricsModule.setAttribute('data-module-id', 'containerMetrics');
modulesList.appendChild(metricsModule);

const toggle = new FakeElement('input', document);
toggle.type = 'checkbox';
toggle.setAttribute('data-module-toggle', '1');
toggle.checked = true;
metricsModule.appendChild(toggle);

const layoutSelect = new FakeElement('select', document);
layoutSelect.setAttribute('data-module-layout', '');
layoutSelect.setAttribute('data-default-layout', 'auto');
layoutSelect.value = 'wide';
metricsModule.appendChild(layoutSelect);

const refreshInput = new FakeElement('input', document);
refreshInput.setAttribute('data-module-container-refresh', '');
refreshInput.value = '45';
metricsModule.appendChild(refreshInput);

const memoryInput = new FakeElement('input', document);
memoryInput.setAttribute('data-module-container-memory', '');
memoryInput.value = '512';
metricsModule.appendChild(memoryInput);

const cpuInput = new FakeElement('input', document);
cpuInput.setAttribute('data-module-container-cpu-max', '');
cpuInput.value = '150';
metricsModule.appendChild(cpuInput);

const titleInput = new FakeElement('input', document);
titleInput.setAttribute('data-module-title', '');
titleInput.placeholder = 'Container-Metriken';
titleInput.value = '';
metricsModule.appendChild(titleInput);

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

const modules = context.readDashboardModules();
assert.strictEqual(modules.length, 1);
assert.strictEqual(modules[0].id, 'containerMetrics');
assert.strictEqual(modules[0].layout, 'wide');
assert.strictEqual(modules[0].options.refreshInterval, 45);
assert.strictEqual(modules[0].options.maxMemoryMb, 512);
assert.strictEqual(modules[0].options.cpuMaxPercent, 150);
assert.strictEqual(modules[0].options.title, 'Container-Metriken');

refreshInput.value = '2';
memoryInput.value = '';
cpuInput.value = '500';
modules[0].options.title = 'Runtime stats';
modules[0].options.refreshInterval = 2;
modules[0].options.maxMemoryMb = null;
modules[0].options.cpuMaxPercent = 500;
context.renderDashboardModulesList(modules);

assert.strictEqual(refreshInput.value, '5', 'refresh interval should clamp to minimum');
assert.strictEqual(memoryInput.value, '', 'memory cap should stay empty when not provided');
assert.strictEqual(cpuInput.value, '400', 'cpu cap should clamp to max');
assert.strictEqual(titleInput.value, 'Runtime stats');
