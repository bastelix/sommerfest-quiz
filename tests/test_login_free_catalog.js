const fs = require('fs');
const code = fs.readFileSync('public/js/catalog.js', 'utf8');

if (!/if\(cfg\.competitionMode && \(cfg\.QRUser \|\| cfg\.randomNames\) && !params\.get\('katalog'\)\)/.test(code)) {
  throw new Error('competition mode condition missing');
}

if (!/if\(!getStored\('quizUser'\)\)\{[\s\S]*?if\(cfg\.randomNames\)\{[\s\S]*?await promptTeamName\(\);[\s\S]*?sessionStorage\.removeItem\('quizSolved'\);[\s\S]*?\}else\{[\s\S]*?generatePlayerName\(\);[\s\S]*?sessionStorage\.removeItem\('quizSolved'\);[\s\S]*?\}/.test(code)) {
  throw new Error('conditional random name assignment missing');
}

console.log('ok');
