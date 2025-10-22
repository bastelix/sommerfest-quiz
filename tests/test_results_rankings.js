const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

let code = fs.readFileSync('public/js/rankings-core.js', 'utf8');
code = code.replace(/export\s+(?=function)/g, '');
const context = { console };
vm.runInNewContext(code, context);
const computeRankings = context.computeRankings;
if (typeof computeRankings !== 'function') {
    throw new Error('computeRankings not found');
}

const rows = [
    { name: 'Team1', catalog: 'A', correct: 3, time: 10 }
];
let r = computeRankings(rows);
assert.strictEqual(r.catalogList.length, 0);

rows.push({ name: 'Team1', catalog: 'B', correct: 2, time: 20 });
rows.push({ name: 'Team1', catalog: 'C', correct: 1, time: 30 });

r = computeRankings(rows);
assert.strictEqual(r.catalogList.length, 1);
assert.strictEqual(r.catalogList[0].name, 'Team1');
console.log('ok');
