const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/results.js', 'utf8');
const fmtMatch = code.match(/function formatTime\(ts\)[\s\S]*?\n\s*\}/);
const rankPattern =
    'function computeRankings\\(rows, qrows\\) \\{' +
    '[\\s\\S]*?return { puzzleList, catalogList, pointsList };\\n\\s*\\}';
const rankMatch = code.match(new RegExp(rankPattern));
if (!fmtMatch || !rankMatch) {
    throw new Error('Functions not found');
}
const context = { catalogCount: 3, console };
context.formatTime = vm.runInNewContext('(' + fmtMatch[0] + ')', context);
const computeRankings = vm.runInNewContext('(' + rankMatch[0] + ')', context);

const rows = [
    { name: 'Team1', catalog: 'A', correct: 3, time: 10 }
];
let r = computeRankings(rows, []);
assert.strictEqual(r.catalogList.length, 0);

rows.push({ name: 'Team1', catalog: 'B', correct: 2, time: 20 });
rows.push({ name: 'Team1', catalog: 'C', correct: 1, time: 30 });

r = computeRankings(rows, []);
assert.strictEqual(r.catalogList.length, 1);
assert.strictEqual(r.catalogList[0].name, 'Team1');
console.log('ok');
