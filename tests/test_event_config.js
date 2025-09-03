const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/event-config.js', 'utf8');

const puzzleWordEnabled = {
  checked: false,
  addEventListener(event, fn) {
    const key = 'on' + event;
    (this[key] || (this[key] = [])).push(fn);
  }
};
const puzzleWord = { disabled: false, addEventListener() {} };
const puzzleFeedback = { disabled: false, addEventListener() {} };
const primaryColor = { setAttribute(name, val) { this[name] = val; } };
const saveBtn = { addEventListener() {} };
const publishBtn = { addEventListener() {} };
const presetLink = {
  textContent: 'Fragen importieren',
  addEventListener(event, fn) { this['on' + event] = fn; }
};

const document = {
  body: { dataset: { eventId: '' } },
  getElementById(id) {
    if (id === 'puzzleWordEnabled') return puzzleWordEnabled;
    if (id === 'puzzleWord') return puzzleWord;
    if (id === 'puzzleFeedback') return puzzleFeedback;
    if (id === 'primary-color') return primaryColor;
    return null;
  },
  querySelector() { return null; },
  querySelectorAll(selector) {
    if (selector === '.event-config-sidebar .uk-button-secondary') return [saveBtn];
    if (selector === '.event-config-sidebar .uk-button-primary') return [publishBtn];
    if (selector === '.event-config-sidebar .uk-card:nth-child(2) .uk-list a') return [presetLink];
    if (selector === 'input, textarea, select') return [puzzleWordEnabled, puzzleWord, puzzleFeedback];
    return [];
  },
  addEventListener(event, fn) {
    if (event === 'DOMContentLoaded') fn();
  }
};

const context = {
  window: {},
  document,
  console,
  setTimeout(fn) { fn(); return 0; },
  clearTimeout() {}
};

vm.runInNewContext(code, context);

// Initial state: puzzle inputs disabled
assert.strictEqual(puzzleWord.disabled, true);
assert.strictEqual(puzzleFeedback.disabled, true);

// Rule: enabling checkbox activates inputs
puzzleWordEnabled.checked = true;
(puzzleWordEnabled.onchange || []).forEach(fn => fn());
assert.strictEqual(puzzleWord.disabled, false);
assert.strictEqual(puzzleFeedback.disabled, false);

// Rule: disabling checkbox deactivates inputs
puzzleWordEnabled.checked = false;
(puzzleWordEnabled.onchange || []).forEach(fn => fn());
assert.strictEqual(puzzleWord.disabled, true);
assert.strictEqual(puzzleFeedback.disabled, true);

// Preset: clicking link enables puzzle word
presetLink.onclick({ preventDefault() {} });
assert.strictEqual(puzzleWordEnabled.checked, true);
assert.strictEqual(puzzleWord.disabled, false);
assert.strictEqual(puzzleFeedback.disabled, false);

console.log('ok');
