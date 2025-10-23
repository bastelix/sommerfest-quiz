const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const quizCode = fs.readFileSync('public/js/quiz.js', 'utf8');
if (!/setStored\('quizUser', name\);\s*setStored\(STORAGE_KEYS\.PLAYER_NAME, name\);/.test(quizCode)) {
    throw new Error('Team name not stored under both keys');
}
const initMatch = quizCode.match(/if\(!getStored\('quizUser'\) && !cfg\.QRRestrict && !cfg\.QRUser\)\{[\s\S]*?\n\s*\}\n\s*\}/);
const handlerMatch = quizCode.match(/startBtn.addEventListener\('click', async\(\) => \{([\s\S]*?)\n\s*\}\);/);
if (!initMatch || !handlerMatch) {
    throw new Error('Required code blocks not found');
}

const appCode = fs.readFileSync('public/js/app.js', 'utf8');
if (!/getElementById\('teamNameBtn'\)/.test(appCode)) {
    throw new Error('teamNameBtn handling missing in app.js');
}

(async() => {
    const initCtx = {
        cfg: { randomNames: true },
        quizUser: null,
        promptCalled: false,
        randomCalled: false,
        getStored() { return this.quizUser; },
        setStored(k, v) { this.quizUser = v; },
        promptTeamName: async() => { initCtx.promptCalled = true; initCtx.setStored('quizUser', 'Team A'); },
        generatePlayerName: () => { initCtx.randomCalled = true; return 'R'; }
    };
    await vm.runInNewContext('(async () => {' + initMatch[0] + '})()', initCtx);
    assert(initCtx.promptCalled);
    assert(!initCtx.randomCalled);
    assert.strictEqual(initCtx.quizUser, 'Team A');

    const body = handlerMatch[1];
    const ctx = {
        cfg: { randomNames: true },
        promptCalled: false,
        randomCalled: false,
        nextCalled: false,
        getStored: () => null,
        setStored: () => {},
        promptTeamName: async() => { ctx.promptCalled = true; },
        generatePlayerName: () => { ctx.randomCalled = true; return 'R'; },
        next: () => { ctx.nextCalled = true; },
        alert: () => {}
    };
    await vm.runInNewContext('(async () => {' + body + '})()', ctx);
    assert(ctx.promptCalled);
    assert(!ctx.randomCalled);
    assert(ctx.nextCalled);
    console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
