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

const penaltyRows = [
    { name: 'Penalty', catalog: 'Main', correct: 1, total: 2, time: 5, attempt: 1 },
    { name: 'Neutral', catalog: 'Main', correct: 2, total: 2, time: 4, attempt: 1 }
];
const penaltyQuestions = [
    { name: 'Penalty', catalog: 'Main', attempt: 1, final_points: 50, efficiency: 0.7 },
    { name: 'Penalty', catalog: 'Main', attempt: 1, final_points: -100, efficiency: -0.3 },
    { name: 'Neutral', catalog: 'Main', attempt: 1, final_points: 40, efficiency: 0.8 },
    { name: 'Neutral', catalog: 'Main', attempt: 1, final_points: 30, efficiency: 1.2 }
];

const penaltyRankings = computeRankings(penaltyRows, penaltyQuestions, 1);
assert.strictEqual(penaltyRankings.pointsList.length, 2);
assert.strictEqual(penaltyRankings.pointsList[0].name, 'Neutral');
assert.strictEqual(penaltyRankings.pointsList[0].raw, 70);
assert.strictEqual(penaltyRankings.pointsList[1].name, 'Penalty');
assert.strictEqual(penaltyRankings.pointsList[1].raw, -50);
assert.strictEqual(penaltyRankings.accuracyList.length, 2);
assert.strictEqual(penaltyRankings.accuracyList[0].name, 'Neutral');
assert.ok(penaltyRankings.accuracyList[0].raw <= 1 && penaltyRankings.accuracyList[0].raw >= 0);
assert.strictEqual(penaltyRankings.accuracyList[1].name, 'Penalty');
assert.ok(penaltyRankings.accuracyList[1].raw <= 1 && penaltyRankings.accuracyList[1].raw >= 0);
console.log('ok');
