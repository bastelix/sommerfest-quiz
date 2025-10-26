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

if (!/async function showManualInput\(\)[\s\S]*await getNameSuggestion\(\)/.test(code)) {
  throw new Error('showManualInput does not await getNameSuggestion');
}

if (!/await confirmNameReservationIfMatching\(name\)/.test(code)) {
  throw new Error('manual submit does not confirm reservation');
}

console.log('ok');
