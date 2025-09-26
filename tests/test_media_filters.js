const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/media-manager.js', 'utf8');

function extractFunction(name, next) {
  const pattern = new RegExp(`function ${name}\\(([^)]*)\\) {\\n([\\s\\S]*?)\\n    }\\n\\n    function ${next}`);
  const match = code.match(pattern);
  if (!match) {
    throw new Error(`${name} function not found`);
  }
  return {
    signature: match[1],
    body: match[2]
  };
}

const tagKeyMatch = extractFunction('tagKey', 'sanitizeTagValue');
const sanitizeMatch = extractFunction('sanitizeTagValue', 'uniqueTags');
const uniqueMatch = extractFunction('uniqueTags', 'getSelectedFile');

const context = {};
vm.createContext(context);

const script = `
function tagKey(${tagKeyMatch.signature}) {
${tagKeyMatch.body}
}
function sanitizeTagValue(${sanitizeMatch.signature}) {
${sanitizeMatch.body}
}
function uniqueTags(${uniqueMatch.signature}) {
${uniqueMatch.body}
}
`;

vm.runInContext(script, context);

assert.strictEqual(context.sanitizeTagValue('  Sommer  Fest! '), 'Sommer Fest');
assert.strictEqual(context.sanitizeTagValue(null), '');

const tags = Array.from(context.uniqueTags(['Hero', ' hero ', 'Summer', '', 'Sommer!', '']));
assert.deepStrictEqual(tags, ['Hero', 'Summer', 'Sommer']);

const empty = Array.from(context.uniqueTags(['', '   ', null, undefined]));
assert.deepStrictEqual(empty, []);

console.log('ok');
