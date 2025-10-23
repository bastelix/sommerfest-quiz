const fs = require('fs');
const code = fs.readFileSync('public/js/quiz.js', 'utf8');

if (!/let nameSuggestion;/.test(code)) {
  throw new Error('nameSuggestion variable missing');
}
if (!/function getNameSuggestion\(\)\{[\s\S]*?generatePlayerName/.test(code)) {
  throw new Error('getNameSuggestion function missing');
}
if (!/const suggestion = getNameSuggestion\(\);\s*title.textContent = 'Teamname eingeben';[\s\S]*?input.value = suggestion;/.test(code)) {
  throw new Error('promptTeamName suggestion handling missing');
}
if (!/function showManualInput\(\)[\s\S]*?const suggestion = getNameSuggestion\(\);[\s\S]*?const placeholderName = suggestion \|\| \(typeof generatePlayerName === 'function' \? generatePlayerName\(\) : ''\);[\s\S]*?input.placeholder = placeholderName \|\| 'Teamname eingeben';[\s\S]*?input.value = placeholderName \|\| '';/.test(code)) {
  throw new Error('showManualInput suggestion handling missing');
}

console.log('ok');
