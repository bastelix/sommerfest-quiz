const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/admin.js', 'utf8');
const summaryStart = code.indexOf('const summaryPageSize');
const summaryEnd = code.indexOf('function activeHelpText');
if (summaryStart === -1 || summaryEnd === -1) {
  throw new Error('summary block not found');
}
const summaryCode = code.slice(summaryStart, summaryEnd);

const listenerMatch = code.match(/document.addEventListener\('current-event-changed', e => {([\s\S]*?)\n\s*}\);/);
if (!listenerMatch) {
  throw new Error('event-change listener not found');
}
const listenerCode = "document.addEventListener('current-event-changed', e => {" + listenerMatch[1] + "\n  });";

const events = [
  { uid: 'ev1', name: 'Event One', description: 'Desc One' },
  { uid: 'ev2', name: 'Event Two', description: 'Desc Two' }
];

class FakeElement {
  constructor(tag) {
    this.tagName = tag.toUpperCase();
    this.children = [];
    this.dataset = {};
    this.listeners = {};
    this.attributes = {};
    this.hidden = false;
    this.disabled = false;
    this.textContent = '';
    this._innerHTML = '';
    this.className = '';
    this.style = {};
  }

  set innerHTML(value) {
    this._innerHTML = value;
    this.children = [];
  }

  get innerHTML() {
    return this._innerHTML;
  }

  appendChild(child) {
    this.children.push(child);
    return child;
  }

  setAttribute(name, value) {
    this.attributes[name] = value;
    if (name === 'hidden') this.hidden = true;
    if (name.startsWith('data-')) {
      const key = name
        .slice(5)
        .replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      this.dataset[key] = value;
    }
  }

  removeAttribute(name) {
    delete this.attributes[name];
    if (name === 'hidden') this.hidden = false;
    if (name === 'src') delete this.src;
    if (name.startsWith('data-')) {
      const key = name
        .slice(5)
        .replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      delete this.dataset[key];
    }
  }

  addEventListener(type, fn) {
    this.listeners[type] = this.listeners[type] || [];
    this.listeners[type].push(fn);
  }
}

const catalogs = Array.from({ length: 14 }, (_, i) => ({
  slug: 'cat' + (i + 1),
  name: 'Catalog ' + (i + 1),
  description: 'Catalog description ' + (i + 1)
}));
const teams = Array.from({ length: 5 }, (_, i) => 'Team ' + (i + 1));

const ctx = {
  currentEventUid: '',
  currentEventName: '',
  cfgInitial: {},
  availableEvents: [],
  eventDependentSections: [{ hidden: false }, { hidden: false }],
  populateEventSelectors: list => { ctx.availableEvents = list; },
  renderCurrentEventIndicator: () => {},
  updateEventButtons: () => {},
  updateActiveHeader: () => {},
  highlightCurrentEvent: () => {},
  updateHeading: () => {},
  catSelect: null,
  teamListEl: null,
  loadCatalogs: () => {},
  loadTeamList: () => {},
  withBase: p => p,
  notify: () => {},
  bindTeamPrintButton: btn => {
    btn.dataset.printBound = '1';
    btn.addEventListener('click', () => {});
  },
  window: {
    baseUrl: 'https://quiz.example',
    transSummaryLoadMoreCatalogs: 'Load more catalogs',
    transSummaryLoadMoreTeams: 'Load more teams',
    transSummaryLoading: 'Loading…',
    transSummaryAllLoaded: 'All entries loaded.',
    transSummaryPageStatus: 'Page %current% of %total% (%count% entries)',
    transSummaryNoCatalogs: 'No catalogs available.',
    transSummaryNoTeams: 'No teams or people available.',
    transSummaryCatalogsError: 'Failed to load catalogs.',
    transSummaryTeamsError: 'Failed to load teams.',
    transTeamPdf: 'QR print'
  },
  document: {
    elements: {},
    listeners: {},
    getElementById(id) { return this.elements[id] || null; },
    addEventListener(name, fn) { this.listeners[name] = fn; },
    dispatchEvent(evt) {
      const fn = this.listeners[evt.type];
      if (fn) fn(evt);
    },
    createElement: tag => new FakeElement(tag)
  },
  apiFetch: async url => {
    const responses = {
      '/events.json': events,
      '/kataloge/catalogs.json': catalogs,
      '/teams.json': teams,
      '/events/ev1/config.json': { qrColorEvent: '#111111' },
      '/events/ev2/config.json': { qrColorEvent: '#222222' },
      '/config.json': {}
    };
    if (!Object.prototype.hasOwnProperty.call(responses, url)) {
      throw new Error('unknown url ' + url);
    }
    return { json: async () => responses[url] };
  },
  URL,
  URLSearchParams
};

ctx.eventSettingsHeading = { dataset: { title: 'Event config' }, textContent: '' };
ctx.catalogsHeading = { dataset: { title: 'Catalogs' }, textContent: '' };
ctx.questionsHeading = { dataset: { title: 'Questions' }, textContent: '' };

const summaryEventName = new FakeElement('h2');
const summaryEventDesc = new FakeElement('p');
const summaryEventQr = new FakeElement('img');
summaryEventQr.dataset = {};
summaryEventQr.hidden = true;
summaryEventQr.removeAttribute = function (attr) {
  delete this[attr];
};

const summaryCatalogs = new FakeElement('div');
summaryCatalogs.dataset.empty = 'No catalogs available.';
summaryCatalogs.dataset.error = 'Failed to load catalogs.';

const summaryCatalogsPager = new FakeElement('div');
summaryCatalogsPager.hidden = true;
const summaryCatalogsStatus = new FakeElement('span');
summaryCatalogsStatus.dataset.template = 'Page %current% of %total% (%count% entries)';
const summaryCatalogsMoreBtn = new FakeElement('button');
summaryCatalogsMoreBtn.textContent = 'Load more catalogs';
summaryCatalogsMoreBtn.dataset.labelMore = 'Load more catalogs';
summaryCatalogsMoreBtn.dataset.labelLoading = 'Loading…';
summaryCatalogsMoreBtn.dataset.labelFinished = 'All entries loaded.';

const summaryTeams = new FakeElement('div');
summaryTeams.dataset.empty = 'No teams or people available.';
summaryTeams.dataset.error = 'Failed to load teams.';

const summaryTeamsPager = new FakeElement('div');
summaryTeamsPager.hidden = true;
const summaryTeamsStatus = new FakeElement('span');
summaryTeamsStatus.dataset.template = 'Page %current% of %total% (%count% entries)';
const summaryTeamsMoreBtn = new FakeElement('button');
summaryTeamsMoreBtn.textContent = 'Load more teams';
summaryTeamsMoreBtn.dataset.labelMore = 'Load more teams';
summaryTeamsMoreBtn.dataset.labelLoading = 'Loading…';
summaryTeamsMoreBtn.dataset.labelFinished = 'All entries loaded.';

ctx.document.elements.summaryEventName = summaryEventName;
ctx.document.elements.summaryEventDesc = summaryEventDesc;
ctx.document.elements.summaryEventQr = summaryEventQr;
ctx.document.elements.summaryCatalogs = summaryCatalogs;
ctx.document.elements.summaryCatalogsPager = summaryCatalogsPager;
ctx.document.elements.summaryCatalogsStatus = summaryCatalogsStatus;
ctx.document.elements.summaryCatalogsMoreBtn = summaryCatalogsMoreBtn;
ctx.document.elements.summaryTeams = summaryTeams;
ctx.document.elements.summaryTeamsPager = summaryTeamsPager;
ctx.document.elements.summaryTeamsStatus = summaryTeamsStatus;
ctx.document.elements.summaryTeamsMoreBtn = summaryTeamsMoreBtn;

const context = vm.createContext(ctx);
vm.runInContext(summaryCode + '\n' + listenerCode, context);
ctx.summaryCatalogState = vm.runInContext('summaryCatalogState', context);
ctx.summaryTeamState = vm.runInContext('summaryTeamState', context);

async function waitForSummary() {
  for (let i = 0; i < 5; i++) {
    if (ctx.summaryCatalogState?.pending || ctx.summaryTeamState?.pending) {
      break;
    }
    await new Promise(r => setImmediate(r));
  }
  if (ctx.summaryCatalogState?.pending) await ctx.summaryCatalogState.pending;
  if (ctx.summaryTeamState?.pending) await ctx.summaryTeamState.pending;
}

async function dispatch(uid, name) {
  ctx.document.dispatchEvent({ type: 'current-event-changed', detail: { uid, name, config: {} } });
  await waitForSummary();
}

(async () => {
  await dispatch('ev1', 'Event One');
  assert.strictEqual(summaryEventName.textContent, 'Event One');
  assert.strictEqual(summaryEventDesc.textContent, 'Desc One');
  assert(summaryEventQr.src.includes('ev1'));
  assert.strictEqual(ctx.summaryCatalogState.rendered, 12);
  assert.strictEqual(summaryCatalogs.children.length, 12);
  assert.strictEqual(summaryCatalogsStatus.textContent, 'Page 1 of 2 (14 entries)');
  assert.strictEqual(summaryCatalogsMoreBtn.hidden, false);
  assert.strictEqual(summaryCatalogsMoreBtn.disabled, false);
  assert.strictEqual(summaryCatalogsMoreBtn.textContent, 'Load more catalogs');
  assert.strictEqual(summaryTeams.children.length, 5);
  assert.strictEqual(summaryTeamsStatus.textContent, 'Page 1 of 1 (5 entries)');
  assert.strictEqual(summaryTeamsMoreBtn.hidden, false);
  assert.strictEqual(summaryTeamsMoreBtn.disabled, true);
  assert.strictEqual(summaryTeamsMoreBtn.textContent, 'All entries loaded.');
  const firstTeamCard = summaryTeams.children[0].children[0];
  const printBtn = firstTeamCard.children[0];
  assert.strictEqual(printBtn.dataset.printBound, '1');

  const clickHandler = summaryCatalogsMoreBtn.listeners.click[0];
  clickHandler({ preventDefault() {} });
  assert.strictEqual(ctx.summaryCatalogState.rendered, 14);
  assert.strictEqual(summaryCatalogs.children.length, 14);
  assert.strictEqual(summaryCatalogsStatus.textContent, 'Page 2 of 2 (14 entries)');
  assert.strictEqual(summaryCatalogsMoreBtn.disabled, true);
  assert.strictEqual(summaryCatalogsMoreBtn.textContent, 'All entries loaded.');

  await dispatch('ev2', 'Event Two');
  assert.strictEqual(summaryEventName.textContent, 'Event Two');
  assert.strictEqual(summaryEventDesc.textContent, 'Desc Two');
  assert(summaryEventQr.src.includes('ev2'));
  assert.strictEqual(ctx.summaryCatalogState.rendered, 12);
  assert.strictEqual(summaryCatalogs.children.length, 12);
  assert.strictEqual(summaryCatalogsMoreBtn.disabled, false);
  assert.strictEqual(summaryCatalogsMoreBtn.textContent, 'Load more catalogs');
  assert.strictEqual(summaryCatalogsStatus.textContent, 'Page 1 of 2 (14 entries)');
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
