const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/quiz.js', 'utf8');
if (!/if\(cfg.randomNames\)\s*\{\n\s*const nameBtn/.test(code)) {
    throw new Error('Team name button not found for randomNames');
}
const initMatch = code.match(/if\(!getStored\('quizUser'\) && !cfg\.QRRestrict && !cfg\.QRUser\)\{[\s\S]*?\n\s*\}\n\s*\}/);
const handlerMatch = code.match(/startBtn.addEventListener\('click', async\(\) => \{([\s\S]*?)\n\s*\}\);/);
if (!initMatch || !handlerMatch) {
    throw new Error('Required code blocks not found');
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
        generateUserName: () => { initCtx.randomCalled = true; return 'R'; }
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
        generateUserName: () => { ctx.randomCalled = true; return 'R'; },
        next: () => { ctx.nextCalled = true; },
        alert: () => {}
    };
    await vm.runInNewContext('(async () => {' + body + '})()', ctx);
    assert(ctx.promptCalled);
    assert(!ctx.randomCalled);
    assert(ctx.nextCalled);
    console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
