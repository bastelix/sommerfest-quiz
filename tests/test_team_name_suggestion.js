const fs = require('fs');
const code = fs.readFileSync('public/js/quiz.js', 'utf8');

if (!/async function getNameSuggestion\(\)[\s\S]*client\.reserve\(\{\s*eventUid: currentEventUid\s*\}\)/.test(code)) {
  throw new Error('TeamNameClient reserve missing');
}

if (!/function releaseNameReservation\(\)[\s\S]*TeamNameClient/.test(code)) {
  throw new Error('releaseNameReservation missing TeamNameClient usage');
}

if (!/async function confirmNameReservationIfMatching\(name\)[\s\S]*client\.confirm/.test(code)) {
  throw new Error('confirmNameReservationIfMatching missing confirm call');
}

if (!/releaseConfirmedTeamName\([\s\S]*client\.releaseByName/.test(code)) {
  throw new Error('Confirmed team name release via TeamNameClient missing');
}

if (!/async function showManualInput\([\s\S]*getNameSuggestion\(\)[\s\S]*\.then/.test(code)) {
  throw new Error('showManualInput no longer updates suggestion asynchronously');
}

if (!/await confirmNameReservationIfMatching\(name\)/.test(code)) {
  throw new Error('manual submit does not confirm reservation');
}

if (!/async function promptTeamNameChange\([\s\S]*async function loadSuggestion\([\s\S]*await getNameSuggestion\(\)/.test(code)) {
  throw new Error('Team name change does not await suggestion retrieval');
}

if (!/promptTeamNameChange[\s\S]*await confirmNameReservationIfMatching\(name\)/.test(code)) {
  throw new Error('Team name change does not confirm reserved suggestion');
}

if (!/team-name-apply-suggestion/.test(code)) {
  throw new Error('Team name change is missing the suggestion apply action');
}

if (!/team-name-refresh-suggestion/.test(code)) {
  throw new Error('Team name change is missing the refresh action');
}

if (!/suggestionApplied && nameReservation/.test(code)) {
  throw new Error('Team name change no longer guards confirmation by explicit adoption');
}

if (!/previousInputValue === previousSuggestionValue/.test(code)) {
  throw new Error('Team name change no longer protects manual entries');
}

console.log('ok');
