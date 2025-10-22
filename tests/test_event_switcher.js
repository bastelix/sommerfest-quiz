const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

(async () => {
  const code = fs.readFileSync('public/js/event-switcher.js', 'utf8');

  const dispatchedEvents = [];
  const document = {
    dispatchEvent(evt) {
      dispatchedEvents.push(evt);
      return true;
    },
    querySelector() {
      return null;
    }
  };

  const secondConfig = { nested: { value: 5 } };
  const fetchStub = (url, options = {}) => {
    const signal = options.signal;
    if (url.endsWith('/config.json') && options.method === 'POST') {
      return Promise.resolve({
        ok: true,
        json: async () => ({}),
        text: async () => ''
      });
    }
    if (url.includes('/events/first/config.json')) {
      return new Promise((resolve, reject) => {
        if (signal) {
          signal.addEventListener(
            'abort',
            () => {
              const err = new Error('aborted');
              err.name = 'AbortError';
              reject(err);
            },
            { once: true }
          );
        }
      });
    }
    if (url.includes('/events/second/config.json')) {
      if (signal) {
        signal.addEventListener('abort', () => {}, { once: true });
      }
      return Promise.resolve({
        ok: true,
        json: async () => secondConfig,
        text: async () => ''
      });
    }
    throw new Error(`Unexpected url ${url}`);
  };

  class CustomEventShim {
    constructor(type, init = {}) {
      this.type = type;
      this.detail = init.detail;
    }
  }

  const sandbox = {
    window: {},
    document,
    fetch: fetchStub,
    CustomEvent: CustomEventShim,
    AbortController,
    console,
    setTimeout,
    clearTimeout
  };
  sandbox.window.window = sandbox.window;
  sandbox.window.basePath = '';
  sandbox.window.csrfToken = 'token';
  sandbox.window.quizConfig = {};
  sandbox.window.document = document;

  const transformed = code
    .replace(/export function/g, 'function')
    .replace(/export let/g, 'let')
    .replace(/export const/g, 'const');
  const appended = `
const exported = {};
Object.defineProperties(exported, {
  switchPending: { get: () => switchPending, set: value => { switchPending = value; } },
  lastSwitchFailed: { get: () => lastSwitchFailed, set: value => { lastSwitchFailed = value; } }
});
exported.setCurrentEvent = setCurrentEvent;
exported.resetSwitchState = resetSwitchState;
exported.markSwitchError = markSwitchError;
exported.getActiveEventUID = getActiveEventUID;
exported.getSwitchEpoch = getSwitchEpoch;
exported.isCurrentEpoch = isCurrentEpoch;
exported.registerCacheReset = registerCacheReset;
exported.registerScopedAbortController = registerScopedAbortController;
module.exports = exported;
`;

  sandbox.module = { exports: {} };
  sandbox.exports = sandbox.module.exports;
  const context = vm.createContext(sandbox);
  vm.runInNewContext(`${transformed}\n${appended}`, context);

  const moduleExports = context.module.exports;
  const {
    setCurrentEvent,
    getSwitchEpoch,
    getActiveEventUID,
    registerCacheReset
  } = moduleExports;

  const resetCalls = [];
  registerCacheReset(detail => {
    resetCalls.push(detail);
  });

  assert.strictEqual(moduleExports.switchPending, false);
  assert.strictEqual(moduleExports.lastSwitchFailed, false);
  assert.strictEqual(getSwitchEpoch(), 0);

  const firstPromise = setCurrentEvent('first', 'First');
  assert.strictEqual(moduleExports.switchPending, true);
  assert.strictEqual(getSwitchEpoch(), 1);

  const secondPromise = setCurrentEvent('second', 'Second');
  assert.strictEqual(getSwitchEpoch(), 2);

  await assert.rejects(firstPromise, err => err && err.name === 'AbortError');

  const resultConfig = await secondPromise;
  assert.strictEqual(moduleExports.switchPending, false);
  assert.strictEqual(moduleExports.lastSwitchFailed, false);
  assert.strictEqual(getActiveEventUID(), 'second');
  assert.strictEqual(getSwitchEpoch(), 2);

  assert.strictEqual(resetCalls.length, 3);
  assert.deepStrictEqual(resetCalls.map(entry => entry.uid), ['first', 'second', 'second']);
  assert.deepStrictEqual(resetCalls.map(entry => entry.pending === true), [true, true, false]);

  const changedEvents = dispatchedEvents.filter(evt => evt.type === 'event:changed');
  assert.strictEqual(changedEvents.length, 1);
  const detail = changedEvents[0].detail;
  assert.strictEqual(detail.uid, 'second');
  assert.strictEqual(detail.name, 'Second');
  assert.strictEqual(detail.epoch, 2);

  assert.notStrictEqual(resultConfig, secondConfig);
  assert.notStrictEqual(detail.config, secondConfig);
  assert.notStrictEqual(resultConfig.nested, secondConfig.nested);
  assert.notStrictEqual(detail.config.nested, secondConfig.nested);
  secondConfig.nested.value = 42;
  assert.strictEqual(resultConfig.nested.value, 5);
  assert.strictEqual(detail.config.nested.value, 5);

  console.log('ok');
})().catch(err => {
  console.error(err);
  process.exit(1);
});
