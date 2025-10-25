const fs = require('fs');

const quizCode = fs.readFileSync('public/js/quiz.js', 'utf8');
if (!/setStored\('quizUser', name\);\s*setStored\(STORAGE_KEYS\.PLAYER_NAME, name\);/.test(quizCode)) {
    throw new Error('Team name not stored under both keys');
}

if (!/setStored\(STORAGE_KEYS\.PLAYER_UID, uid\);/.test(quizCode)) {
    throw new Error('Player UID not persisted when saving name');
}

if (!/fetch\(['"]\/api\/players['"]/.test(quizCode)) {
    throw new Error('Player name changes are not synced with the server');
}

if (!/if\(!getStored\('quizUser'\) && !cfg\.QRRestrict && !cfg\.QRUser\)\{\s*if\(cfg\.randomNames\)\{\s*await promptTeamName\(\);/s.test(quizCode)) {
    throw new Error('Initial random name prompt missing');
}

if (!/const usingSuggestion = nameReservation &&/.test(quizCode)) {
    throw new Error('Suggestion usage check missing');
}

if (!/const confirmed = await confirmNameReservationIfMatching\(name\);/.test(quizCode)) {
    throw new Error('Reservation confirmation missing');
}

if (!/releaseNameReservation\(\);/.test(quizCode)) {
    throw new Error('Reservation release missing');
}

if (/generatePlayerName\s*\(/.test(quizCode)) {
    throw new Error('legacy generatePlayerName usage detected');
}

const appCode = fs.readFileSync('public/js/app.js', 'utf8');
if (!/getElementById\('teamNameBtn'\)/.test(appCode)) {
    throw new Error('teamNameBtn handling missing in app.js');
}

console.log('ok');
