const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/results-data-service.js', 'utf8');
const sanitized = code
    .replace(/^import[^;]+;\s*/gm, '')
    .replace(/export\s+class\s+ResultsDataService/g, 'class ResultsDataService')
    .replace(/export\s+function\s+computeRankings/g, 'function computeRankings');
const context = { console, formatTimestamp: value => value };
vm.runInNewContext(sanitized, context);
if (typeof context.computeRankings !== 'function') {
    throw new Error('Functions not found');
}
const computeRankings = context.computeRankings;

const rows = [
    { name: 'Team1', catalog: 'A', correct: 3, total: 5, time: 10 }
];
let r = computeRankings(rows, [], 3);
assert.strictEqual(r.catalogList.length, 0);
assert.strictEqual(r.accuracyList.length, 1);
assert.strictEqual(r.accuracyList[0].name, 'Team1');
assert.strictEqual(r.accuracyList[0].value, 'Ø 60 %');

rows.push({ name: 'Team1', catalog: 'B', correct: 2, total: 4, time: 20 });
rows.push({ name: 'Team1', catalog: 'C', correct: 1, total: 3, time: 30 });

r = computeRankings(rows, [], 3);
assert.strictEqual(r.catalogList.length, 1);
assert.strictEqual(r.catalogList[0].name, 'Team1');
assert.strictEqual(r.accuracyList[0].value, 'Ø 50 %');
console.log('ok');
