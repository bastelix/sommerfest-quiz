const fs = require('fs');
const code = fs.readFileSync('public/js/quiz.js', 'utf8');

if (!/if\(!getStored\('quizUser'\) && !cfg\.QRRestrict && !cfg\.QRUser\)\{\s*if\(cfg\.randomNames\)\{\s*await promptTeamName\(\);/s.test(code)) {
  throw new Error('random name prompt missing');
}

if (/generatePlayerName\s*\(/.test(code)) {
  throw new Error('legacy generatePlayerName usage detected');
}

console.log('ok');
