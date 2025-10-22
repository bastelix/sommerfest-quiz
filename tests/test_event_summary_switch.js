const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/admin.js', 'utf8');
const loadMatch = code.match(/function loadSummary\(\) {([\s\S]*?)\n  }\n\n  function/);
if (!loadMatch) {
  throw new Error('loadSummary not found');
}
const loadSummaryCode = 'function loadSummary() {' + loadMatch[1] + '\n}';

const listenerMatch = code.match(/document.addEventListener\('current-event-changed', e => {([\s\S]*?)\n\s*}\);/);
if (!listenerMatch) {
  throw new Error('event-change listener not found');
}
const listenerCode = "document.addEventListener('current-event-changed', e => {" + listenerMatch[1] + "\n  });";

const events = [
  { uid: 'ev1', name: 'Event One', description: 'Desc One' },
  { uid: 'ev2', name: 'Event Two', description: 'Desc Two' }
];
const eventConfigs = {
  ev1: { qrLogoPath: '/logo-ev1.png', teamResults: true },
  ev2: { teamResults: false }
};

const ctx = {
  currentEventUid: '',
  currentEventName: '',
  cfgInitial: { qrLogoPath: 'legacy.png' },
  renderLog: [],
  updateActiveHeader: () => {},
  updateHeading: () => {},
  renderCurrentEventIndicator: () => {},
  updateEventButtons: () => {},
  highlightCurrentEvent: () => {},
  populateEventSelectors: () => {},
  eventDependentSections: [],
  catSelect: null,
  teamListEl: null,
  loadCatalogs: () => {},
  loadTeamList: () => {},
  eventSettingsHeading: null,
  catalogsHeading: null,
  questionsHeading: null,
  withBase: p => p,
  availableEvents: [],
  window: { quizConfig: {} },
  document: {
    elements: {},
    listeners: {},
    getElementById(id) { return this.elements[id] || null; },
    addEventListener(name, fn) { this.listeners[name] = fn; },
    dispatchEvent(evt) { const fn = this.listeners[evt.type]; if (fn) fn(evt); },
    createElement: tag => ({
      dataset: {},
      appendChild() {},
      setAttribute() {},
      addEventListener() {},
      className: '',
      style: {},
      remove() {}
    })
  },
  apiFetch: async url => {
    const responses = {
      '/events.json': { json: async () => events },
      '/kataloge/catalogs.json': { json: async () => [] },
      '/teams.json': { json: async () => [] },
      '/events/ev1/config.json': { json: async () => eventConfigs.ev1 },
      '/events/ev2/config.json': { json: async () => eventConfigs.ev2 },
      '/config.json': { json: async () => ({}) }
    };
    if (!responses[url]) throw new Error('unknown url ' + url);
    return responses[url];
  },
  notify: () => {},
  URL,
  URLSearchParams
};

ctx.availableEvents = events.slice();
ctx.replaceInitialConfig = (newConfig) => {
  const source = (newConfig && typeof newConfig === 'object') ? newConfig : {};
  Object.keys(ctx.cfgInitial).forEach((key) => { delete ctx.cfgInitial[key]; });
  Object.assign(ctx.cfgInitial, source);
  return { ...ctx.cfgInitial };
};
ctx.renderCfg = (data) => {
  ctx.renderLog.push({ ...data });
};

ctx.document.elements.summaryEventName = { textContent: '' };
ctx.document.elements.summaryEventDesc = { textContent: '' };
ctx.document.elements.summaryEventQr = {
  dataset: {},
  hidden: true,
  src: '',
  removeAttribute(attr) { delete this[attr]; }
};
ctx.document.elements.summaryCatalogs = { innerHTML: '', appendChild() {} };
ctx.document.elements.summaryTeams = { innerHTML: '', appendChild() {} };

vm.runInNewContext(loadSummaryCode + '\n' + listenerCode, ctx);

async function dispatch(uid, name, config = eventConfigs[uid] || {}) {
  ctx.document.dispatchEvent({ type: 'current-event-changed', detail: { uid, name, config } });
  await new Promise(r => setImmediate(r));
}

(async () => {
  await dispatch('ev1', 'Event One');
  assert.strictEqual(ctx.document.elements.summaryEventName.textContent, 'Event One');
  assert.strictEqual(ctx.document.elements.summaryEventDesc.textContent, 'Desc One');
  assert(ctx.document.elements.summaryEventQr.src.includes('ev1'));
  assert.strictEqual(ctx.cfgInitial.qrLogoPath, '/logo-ev1.png');
  assert.strictEqual(ctx.cfgInitial.event_uid, 'ev1');
  assert.strictEqual(ctx.window.quizConfig.qrLogoPath, '/logo-ev1.png');
  assert.strictEqual(ctx.window.quizConfig.event_uid, 'ev1');

  await dispatch('ev2', 'Event Two');
  assert.strictEqual(ctx.document.elements.summaryEventName.textContent, 'Event Two');
  assert.strictEqual(ctx.document.elements.summaryEventDesc.textContent, 'Desc Two');
  assert(ctx.document.elements.summaryEventQr.src.includes('ev2'));
  assert.strictEqual(ctx.cfgInitial.event_uid, 'ev2');
  assert(!('qrLogoPath' in ctx.cfgInitial));
  assert.strictEqual(ctx.window.quizConfig.event_uid, 'ev2');
  assert(!('qrLogoPath' in ctx.window.quizConfig));
  assert(ctx.renderLog.length >= 2);
  const lastRender = ctx.renderLog[ctx.renderLog.length - 1];
  assert.strictEqual(lastRender.event_uid, 'ev2');
  assert(!('qrLogoPath' in lastRender));
  console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
