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
assert.strictEqual(r.catalogList.length, 1);
assert.strictEqual(r.catalogList[0].name, 'Team1');
assert.strictEqual(r.catalogList[0].value, '3 gelöst – 3 Punkte');
assert.strictEqual(r.accuracyList.length, 1);
assert.strictEqual(r.accuracyList[0].name, 'Team1');
assert.strictEqual(r.accuracyList[0].value, 'Ø 60 %');

rows.push({ name: 'Team1', catalog: 'B', correct: 2, total: 4, time: 20 });
rows.push({ name: 'Team1', catalog: 'C', correct: 1, total: 3, time: 30 });

r = computeRankings(rows, [], 3);
assert.strictEqual(r.catalogList.length, 1);
assert.strictEqual(r.catalogList[0].name, 'Team1');
assert.strictEqual(r.catalogList[0].value, '6 gelöst – 6 Punkte');
assert.strictEqual(r.accuracyList[0].value, 'Ø 50 %');

const tiePointsRows = [
    { name: 'Delta', catalog: 'Main', correct: 3, total: 5, points: 60, time: 15, durationSec: 90 },
    { name: 'Epsilon', catalog: 'Main', correct: 3, total: 5, points: 50, time: 20, durationSec: 80 }
];
const tiePointsRankings = computeRankings(tiePointsRows, [], 1);
assert.strictEqual(tiePointsRankings.catalogList[0].name, 'Delta');

const tieDurationRows = [
    { name: 'Eta', catalog: 'Main', correct: 2, total: 5, points: 40, time: 25, durationSec: 200 },
    { name: 'Theta', catalog: 'Main', correct: 2, total: 5, points: 40, time: 30, durationSec: 150 }
];
const tieDurationRankings = computeRankings(tieDurationRows, [], 1);
assert.strictEqual(tieDurationRankings.catalogList[0].name, 'Theta');

const penaltyRows = [
    { name: 'Penalty', catalog: 'Main', correct: 1, total: 2, time: 5, attempt: 1 },
    { name: 'Neutral', catalog: 'Main', correct: 2, total: 2, time: 4, attempt: 1 }
];
const penaltyQuestions = [
    { name: 'Penalty', catalog: 'Main', attempt: 1, final_points: 50, efficiency: 0.7, correct: 1 },
    { name: 'Penalty', catalog: 'Main', attempt: 1, final_points: -100, efficiency: -0.3, correct: 0 },
    { name: 'Neutral', catalog: 'Main', attempt: 1, final_points: 40, efficiency: 0.8, correct: 1 },
    { name: 'Neutral', catalog: 'Main', attempt: 1, final_points: 30, efficiency: 1.2, correct: 1 }
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

const binaryRows = [
    { name: 'Binary', catalog: 'Main', correct: 0, total: 3, time: 6, durationSec: 120, attempt: 1 },
    { name: 'Perfect', catalog: 'Main', correct: 1, total: 3, time: 8, durationSec: 90, attempt: 1 }
];
const binaryQuestions = [
    { name: 'Binary', catalog: 'Main', attempt: 1, final_points: 10, efficiency: 0.9, correct: 1 },
    { name: 'Binary', catalog: 'Main', attempt: 1, final_points: 10, efficiency: 0.9, correct: 1 },
    { name: 'Binary', catalog: 'Main', attempt: 1, final_points: 0, efficiency: 0, correct: 0 },
    { name: 'Perfect', catalog: 'Main', attempt: 1, final_points: 10, efficiency: 1, correct: 1 },
    { name: 'Perfect', catalog: 'Main', attempt: 1, final_points: 10, efficiency: 1, correct: 1 },
    { name: 'Perfect', catalog: 'Main', attempt: 1, final_points: 10, efficiency: 1, correct: 1 }
];

const binaryRankings = computeRankings(binaryRows, binaryQuestions, 1);
assert.strictEqual(binaryRankings.catalogList.length, 2);
assert.strictEqual(binaryRankings.catalogList[0].name, 'Perfect');
assert.strictEqual(binaryRankings.catalogList[0].solved, 3);
assert.strictEqual(binaryRankings.catalogList[1].name, 'Binary');
assert.strictEqual(binaryRankings.catalogList[1].solved, 2);
console.log('ok');
