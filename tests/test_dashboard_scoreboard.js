const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

let code = fs.readFileSync('public/js/rankings-core.js', 'utf8');
code = code.replace(/export\s+(?=function)/g, '');
const context = {};
vm.runInNewContext(code, context);
const buildScoreboard = context.buildScoreboard;
if (typeof buildScoreboard !== 'function') {
  throw new Error('buildScoreboard not found');
}

const rows = [
  { name: 'Alpha', catalog: 'A', correct: 3, total: 3, time: 100 },
  { name: 'Beta', catalog: 'A', correct: 2, total: 3, time: 110 },
  { name: 'Alpha', catalog: 'B', correct: 1, total: 3, time: 120 },
  { name: 'Beta', catalog: 'B', correct: 3, total: 3, time: 130 },
];

const board = buildScoreboard(rows);
assert.strictEqual(board[0].name, 'Beta');
assert.ok(board[0].points > board[1].points);
console.log('ok');
