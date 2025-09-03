const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const code = fs.readFileSync('public/js/catalog.js', 'utf8');
const match = code.match(/async function buildSolvedSet\(cfg\)\{[\s\S]*?return solved;\n\s*\}/);
if (!match) {
    throw new Error('buildSolvedSet not found');
}

const context = {
    sessionStorage: {
        _data: {},
        getItem(key) { return Object.prototype.hasOwnProperty.call(this._data, key) ? this._data[key] : null; },
        setItem(key, val) { this._data[key] = String(val); },
        removeItem(key) { delete this._data[key]; }
    },
    fetch: async() => ({
        ok: true,
        json: async() => [{ name: 'Team1', catalog: 'uid1' }]
    }),
    console,
    withBase: p => p
};

const buildSolvedSet = vm.runInNewContext('(' + match[0] + ')', context);
context.sessionStorage.setItem('quizUser', 'Team1');

(async() => {
    const res = await buildSolvedSet({ competitionMode: true });
    assert(res.has('uid1'));
    assert.deepStrictEqual(JSON.parse(context.sessionStorage.getItem('quizSolved')), ['uid1']);
    console.log('ok');
})().catch(err => { console.error(err); process.exit(1); });
