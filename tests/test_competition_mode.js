const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/catalog.js', 'utf8');
const match = code.match(/async function buildSolvedSet\(cfg\)\{[\s\S]*?return solved;\n\s*\}/);
if (!match) {
    throw new Error('buildSolvedSet not found');
}

let requestedUrl;

const context = {
    STORAGE_KEYS: {
        PLAYER_NAME: 'quizUser',
        QUIZ_SOLVED: 'quizSolved'
    },
    sessionStorage: {
        _data: {},
        getItem(key) { return Object.prototype.hasOwnProperty.call(this._data, key) ? this._data[key] : null; },
        setItem(key, val) { this._data[key] = String(val); },
        removeItem(key) { delete this._data[key]; }
    },
    fetch: async(url) => {
        requestedUrl = url;
        return {
            ok: true,
            json: async() => [{ name: 'Team1', catalog: 'slug1' }]
        };
    },
    console,
    withBase: p => p
};
context.getStored = key => context.sessionStorage.getItem(key);
context.setStored = (key, value) => context.sessionStorage.setItem(key, value);

const buildSolvedSet = vm.runInNewContext('(' + match[0] + ')', context);
context.sessionStorage.setItem('quizUser', 'Team1');

(async() => {
    const res = await buildSolvedSet({ competitionMode: true });
    assert(res.has('slug1'));
    assert.deepStrictEqual(JSON.parse(context.sessionStorage.getItem('quizSolved')), ['slug1']);
    assert.strictEqual(requestedUrl, '/results.json');
    console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
