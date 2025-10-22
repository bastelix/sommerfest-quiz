const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const { URLSearchParams } = require('url');

const code = fs.readFileSync('public/js/admin.js', 'utf8');

const binderStart = code.indexOf('function bindTeamPrintButtons');
if (binderStart === -1) {
  throw new Error('bindTeamPrintButtons not found');
}
const binderEnd = code.indexOf('const summaryPrintBtn', binderStart);
const binderCode = code.slice(binderStart, binderEnd);

const setStart = code.indexOf('function setQrImage');
if (setStart === -1) {
  throw new Error('setQrImage not found');
}
const summaryStart = code.indexOf('let summaryRequestId = 0;');
if (summaryStart === -1) {
  throw new Error('summary pager state not found');
}
const qrSection = code.slice(setStart, summaryStart);

const activeHelpTextIndex = code.indexOf('function activeHelpText');
if (activeHelpTextIndex === -1) {
  throw new Error('activeHelpText not found');
}
const summarySection = code.slice(summaryStart, activeHelpTextIndex);

const listenerMatch = code.match(/document.addEventListener\('current-event-changed', e => {([\s\S]*?)\n\s*}\);/);
if (!listenerMatch) {
  throw new Error('event-change listener not found');
}
const listenerCode = "document.addEventListener('current-event-changed', e => {" + listenerMatch[1] + "\n  });";

const combinedCode = `${binderCode}\n${qrSection}${summarySection}\n${listenerCode}`;

class FakeElement {
  constructor(tagName, doc) {
    this.tagName = tagName.toUpperCase();
    this.document = doc;
    this.children = [];
    this.parentNode = null;
    this.attributes = {};
    this.dataset = {};
    this.eventListeners = {};
    this.style = {};
    this.hidden = false;
    this.type = '';
    this.src = '';
    this.href = '';
    this.target = '';
    this.width = 0;
    this.height = 0;

    const classSet = new Set();
    const syncClassAttr = () => {
      this.attributes.class = Array.from(classSet).join(' ');
    };

    Object.defineProperty(this, 'className', {
      get: () => Array.from(classSet).join(' '),
      set: value => {
        classSet.clear();
        String(value)
          .split(/\s+/)
          .forEach(cls => {
            if (cls) classSet.add(cls);
          });
        syncClassAttr();
      }
    });

    this.classList = {
      add: (...names) => {
        names.forEach(name => {
          if (name) classSet.add(name);
        });
        syncClassAttr();
      },
      remove: (...names) => {
        names.forEach(name => classSet.delete(name));
        syncClassAttr();
      },
      contains: name => classSet.has(name)
    };

    this._textContent = '';
    Object.defineProperty(this, 'textContent', {
      get: () => this._textContent,
      set: value => {
        this._textContent = String(value);
        this.children = [];
      }
    });

    this._innerHTML = '';
    Object.defineProperty(this, 'innerHTML', {
      get: () => this._innerHTML,
      set: value => {
        this._innerHTML = String(value);
        if (value === '') {
          this.children = [];
        }
      }
    });

    this._id = '';
    Object.defineProperty(this, 'id', {
      get: () => this._id,
      set: value => {
        const val = String(value);
        if (this._id && this.document.elements[this._id] === this) {
          delete this.document.elements[this._id];
        }
        this._id = val;
        if (val !== '') {
          this.attributes.id = val;
          this.document.registerId(val, this);
        }
      }
    });
  }

  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);
    return child;
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
    } else if (name === 'src') {
      this.src = val;
    } else if (name === 'type') {
      this.type = val;
    }
  }

  getAttribute(name) {
    if (name === 'id') {
      return this._id;
    }
    if (name === 'class') {
      return this.className;
    }
    if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      return this.dataset[key];
    }
    return this.attributes[name];
  }

  removeAttribute(name) {
    delete this.attributes[name];
    if (name === 'id') {
      if (this._id && this.document.elements[this._id] === this) {
        delete this.document.elements[this._id];
      }
      this._id = '';
    } else if (name === 'class') {
      this.className = '';
    } else if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      delete this.dataset[key];
    } else if (name === 'src') {
      this.src = '';
    }
  }

  addEventListener(type, handler) {
    (this.eventListeners[type] ||= []).push(handler);
  }

  dispatchEvent(evt) {
    const listeners = this.eventListeners[evt.type] || [];
    listeners.forEach(fn => fn({ ...evt, target: this, preventDefault: evt.preventDefault || (() => {}) }));
  }

  click() {
    this.dispatchEvent({ type: 'click', preventDefault() {} });
  }

  matches(selector) {
    if (selector.startsWith('.')) {
      const cls = selector.slice(1);
      return this.classList.contains(cls);
    }
    if (selector.startsWith('[data-') && selector.endsWith(']')) {
      const attr = selector.slice(6, -1);
      const key = attr.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      return Object.prototype.hasOwnProperty.call(this.dataset, key);
    }
    return false;
  }

  querySelectorAll(selector) {
    const results = [];
    this.children.forEach(child => {
      if (child.matches(selector)) {
        results.push(child);
      }
      results.push(...child.querySelectorAll(selector));
    });
    return results;
  }

  querySelector(selector) {
    const [first] = this.querySelectorAll(selector);
    return first || null;
  }
}

class FakeDocument {
  constructor() {
    this.elements = {};
    this.listeners = {};
    this.allElements = new Set();
    this.body = this.createElement('body');
    this.body.parentNode = null;
  }

  registerElement(el) {
    this.allElements.add(el);
  }

  registerId(id, el) {
    this.elements[id] = el;
  }

  createElement(tag) {
    const el = new FakeElement(tag, this);
    this.registerElement(el);
    return el;
  }

  getElementById(id) {
    return this.elements[id] || null;
  }

  addEventListener(type, handler) {
    (this.listeners[type] ||= []).push(handler);
  }

  dispatchEvent(evt) {
    const listeners = this.listeners[evt.type] || [];
    listeners.forEach(handler => handler(evt));
  }

  querySelectorAll(selector) {
    const results = new Set();
    const visit = el => {
      if (el.matches(selector)) {
        results.add(el);
      }
      el.children.forEach(child => visit(child));
    };
    this.allElements.forEach(el => {
      if (!el.parentNode) {
        visit(el);
      }
    });
    return Array.from(results);
  }
}

const document = new FakeDocument();
const window = {
  baseUrl: 'https://example.test',
  basePath: '',
  location: { origin: 'https://example.test', hostname: 'example.test' },
  openCalls: [],
  open(url) {
    this.openCalls.push(url);
  },
  transSummaryLoadingCatalogs: 'Loading catalogs…',
  transSummaryLoadingTeams: 'Loading teams…',
  transSummaryLoadingEvent: 'Loading event details…',
  transSummaryCatalogProgress: 'Showing %current% of %total% catalogs',
  transSummaryTeamProgress: 'Showing %current% of %total% teams',
  transSummaryLoadError: 'Entries could not be loaded.',
  transSummaryNoCatalogs: 'No catalogs',
  transSummaryNoTeams: 'No teams'
};
window.quizConfig = {};

const ctx = {
  document,
  window,
  currentEventUid: '',
  currentEventName: '',
  cfgInitial: {},
  availableEvents: [],
  eventDependentSections: [],
  eventSelectNodes: [],
  eventIndicators: [],
  apiFetch: null,
  withBase: path => path,
  openQrDesignModal: () => {},
  applyLazyImage: (img, src) => {
    if (!img) return;
    if (src) {
      img.src = src;
      img.dataset = img.dataset || {};
      img.dataset.src = src;
    } else {
      img.src = '';
    }
  },
  replaceInitialConfig: config => config,
  populateEventSelectors: list => {
    ctx.availableEvents = Array.isArray(list) ? list : [];
  },
  renderCurrentEventIndicator: () => {},
  updateEventButtons: () => {},
  updateActiveHeader: () => {},
  highlightCurrentEvent: () => {},
  updateHeading: () => {},
  eventSettingsHeading: null,
  catalogsHeading: null,
  questionsHeading: null,
  catSelect: null,
  teamListEl: null,
  loadCatalogs: () => {},
  loadTeamList: () => {},
  fetchCalls: [],
  URLSearchParams
};

vm.runInNewContext(combinedCode, ctx);

function flushPromises(times = 1) {
  if (times <= 0) {
    return Promise.resolve();
  }
  return new Promise(resolve => setImmediate(resolve)).then(() => flushPromises(times - 1));
}

function makeElement(tag, id) {
  const el = document.createElement(tag);
  if (id) {
    el.id = id;
  }
  return el;
}

const summaryEventName = makeElement('h2', 'summaryEventName');
const summaryEventDesc = makeElement('p', 'summaryEventDesc');
const summaryEventQr = makeElement('img', 'summaryEventQr');
const summaryCatalogs = makeElement('div', 'summaryCatalogs');
summaryCatalogs.setAttribute('data-summary-page-size', '2');
summaryCatalogs.setAttribute('data-empty-text', 'No catalogs');
const summaryCatalogsPager = makeElement('div', 'summaryCatalogsPager');
summaryCatalogsPager.hidden = true;
const summaryCatalogsMeta = makeElement('p', 'summaryCatalogsMeta');
const summaryCatalogsMore = makeElement('button', 'summaryCatalogsMore');
summaryCatalogsMore.hidden = true;
summaryCatalogsMore.type = 'button';
summaryCatalogsPager.appendChild(summaryCatalogsMeta);
summaryCatalogsPager.appendChild(summaryCatalogsMore);
const summaryTeams = makeElement('div', 'summaryTeams');
summaryTeams.setAttribute('data-summary-page-size', '2');
summaryTeams.setAttribute('data-empty-text', 'No teams');
const summaryTeamsPager = makeElement('div', 'summaryTeamsPager');
summaryTeamsPager.hidden = true;
const summaryTeamsMeta = makeElement('p', 'summaryTeamsMeta');
const summaryTeamsMore = makeElement('button', 'summaryTeamsMore');
summaryTeamsMore.hidden = true;
summaryTeamsMore.type = 'button';
summaryTeamsPager.appendChild(summaryTeamsMeta);
summaryTeamsPager.appendChild(summaryTeamsMore);

document.body.appendChild(summaryEventName);
document.body.appendChild(summaryEventDesc);
document.body.appendChild(summaryEventQr);
document.body.appendChild(summaryCatalogs);
document.body.appendChild(summaryCatalogsPager);
document.body.appendChild(summaryTeams);
document.body.appendChild(summaryTeamsPager);

document.registerId('summaryCatalogsMeta', summaryCatalogsMeta);
document.registerId('summaryCatalogsMore', summaryCatalogsMore);
document.registerId('summaryTeamsMeta', summaryTeamsMeta);
document.registerId('summaryTeamsMore', summaryTeamsMore);

const events = [
  { uid: 'ev1', name: 'Event One', description: 'First event' },
  { uid: 'ev2', name: 'Event Two', description: 'Second event' }
];

const catalogItems = [
  { slug: 'alpha', name: 'Catalog A', description: 'A' },
  { slug: 'bravo', name: 'Catalog B', description: 'B' },
  { slug: 'charlie', name: 'Catalog C', description: 'C' }
];

const teamItems = {
  ev1: ['Team Red', 'Team Blue', 'Team Green'],
  ev2: ['Team Yellow']
};

function buildPage(items, page, perPage) {
  const total = items.length;
  const offset = (page - 1) * perPage;
  const slice = items.slice(offset, offset + perPage);
  const nextPage = offset + perPage < total ? page + 1 : null;
  return {
    items: slice,
    pager: {
      page,
      perPage,
      total,
      count: slice.length,
      nextPage
    }
  };
}

ctx.apiFetch = async url => {
  const fullUrl = url.startsWith('http') ? url : `https://example.test${url}`;
  ctx.fetchCalls.push(fullUrl);
  const parsed = new URL(fullUrl);
  const path = parsed.pathname;
  if (path === '/config.json' || path === '/events/ev1/config.json' || path === '/events/ev2/config.json') {
    return { json: async () => ({}) };
  }
  if (path === '/events.json') {
    return { json: async () => events };
  }
  if (path === '/kataloge/catalogs.json') {
    const page = parseInt(parsed.searchParams.get('page') || '1', 10);
    const perPage = parseInt(parsed.searchParams.get('per_page') || '2', 10);
    return { json: async () => buildPage(catalogItems, page, perPage) };
  }
  if (path === '/teams.json') {
    const page = parseInt(parsed.searchParams.get('page') || '1', 10);
    const perPage = parseInt(parsed.searchParams.get('per_page') || '2', 10);
    const eventUid = parsed.searchParams.get('event_uid') || ctx.currentEventUid || 'ev1';
    return { json: async () => buildPage(teamItems[eventUid] || [], page, perPage) };
  }
  throw new Error(`unknown url ${url}`);
};

function dispatch(uid, name) {
  ctx.fetchCalls = [];
  ctx.document.dispatchEvent({ type: 'current-event-changed', detail: { uid, name, config: {} } });
  return flushPromises(6);
}

(async () => {
  await dispatch('ev1', 'Event One');
  await flushPromises(6);
  assert.strictEqual(summaryEventName.textContent, 'Event One');
  assert.strictEqual(summaryEventDesc.textContent, 'First event');
  assert.strictEqual(summaryEventQr.hidden, false);
  assert.strictEqual(summaryCatalogs.children.length, 2);
  assert.strictEqual(summaryTeams.children.length, 2);
  assert.strictEqual(summaryCatalogsMore.hidden, false);
  assert.strictEqual(summaryTeamsMore.hidden, false);
  assert.strictEqual(summaryCatalogsMeta.textContent, 'Showing 2 of 3 catalogs');
  assert.strictEqual(summaryTeamsMeta.textContent, 'Showing 2 of 3 teams');

  const firstTeamBtn = summaryTeams.children[0].children[0].children[0];
  window.openCalls = [];
  firstTeamBtn.click();
  assert(window.openCalls[0].includes('event=ev1'));

  summaryCatalogsMore.click();
  summaryTeamsMore.click();
  await flushPromises(6);

  assert.strictEqual(summaryCatalogs.children.length, 3);
  assert.strictEqual(summaryTeams.children.length, 3);
  assert.strictEqual(summaryCatalogsMore.hidden, true);
  assert.strictEqual(summaryTeamsMore.hidden, true);
  assert.strictEqual(summaryCatalogsMeta.textContent, 'Showing 3 of 3 catalogs');
  assert.strictEqual(summaryTeamsMeta.textContent, 'Showing 3 of 3 teams');

  window.openCalls = [];
  const lastTeamBtn = summaryTeams.children[2].children[0].children[0];
  lastTeamBtn.click();
  assert(decodeURIComponent(window.openCalls[0]).includes('Team%20Green'));

  await dispatch('ev2', 'Event Two');
  await flushPromises(6);
  assert(ctx.fetchCalls.some(url => url.includes('/kataloge/catalogs.json')));
  assert.strictEqual(summaryEventName.textContent, 'Event Two');
  assert.strictEqual(summaryEventDesc.textContent, 'Second event');
  assert.strictEqual(summaryCatalogs.children.length, 2);
  assert.strictEqual(summaryTeams.children.length, 1);
  assert.strictEqual(summaryCatalogsMore.hidden, false);
  assert.strictEqual(summaryTeamsMore.hidden, true);
  assert.strictEqual(summaryCatalogsMeta.textContent, 'Showing 2 of 3 catalogs');
  assert.strictEqual(summaryTeamsMeta.textContent, 'Showing 1 of 1 teams');

  window.openCalls = [];
  const ev2TeamBtn = summaryTeams.children[0].children[0].children[0];
  ev2TeamBtn.click();
  assert(window.openCalls[0].includes('event=ev2'));
  assert(decodeURIComponent(window.openCalls[0]).includes('Team%20Yellow'));
})().catch(err => {
  console.error(err);
  process.exit(1);
});
