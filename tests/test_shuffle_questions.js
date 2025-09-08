const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/quiz.js', 'utf8');
const match = code.match(/function shuffleArray\(arr\)\{[\s\S]*?\}\n\s*\/\/[\s\S]*?\n\s*const shuffled = cfg\.shuffleQuestions !== false \? shuffleArray\(questions\) : questions\.slice\(\);/);
if (!match) {
  throw new Error('shuffleQuestions handling missing');
}

const context = { cfg: { shuffleQuestions: false }, questions: [1, 2, 3] };
vm.runInNewContext(match[0] + '\n;globalThis.shuffled = shuffled;', context);
assert.deepStrictEqual(context.shuffled, [1, 2, 3]);
console.log('ok');

