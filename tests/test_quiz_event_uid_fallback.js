const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const quizCode = fs.readFileSync('public/js/quiz.js', 'utf8');
const headerMatch = quizCode.match(/const quizConfig =[\s\S]*?const basePath = window\.basePath \|\| '';/);
if(!headerMatch){
  throw new Error('Event UID bootstrap block not found');
}

const submissionMatch = quizCode.match(/const rawCatalog =[\s\S]*?summaryEl.appendChild\(link\);\n\s+}\n\s+}/);
if(!submissionMatch){
  throw new Error('Result submission block not found');
}

const context = {
  window: { quizConfig: {}, location: { search: '?event=evt-42' }, basePath: '' },
  location: { search: '?event=evt-42' },
  URLSearchParams,
  encodeURIComponent,
  console,
  JSON,
  Math,
  setTimeout: () => {},
  clearTimeout: () => {}
};

vm.runInNewContext(headerMatch[0], context);
assert.strictEqual(context.window.quizConfig.event_uid, 'evt-42');
const resolvedUid = vm.runInNewContext('currentEventUid', context);
assert.strictEqual(resolvedUid, 'evt-42');

const store = {
  CATALOG: 'MainSlug',
  QUIZ_SOLVED: '[]',
  PLAYER_UID: 'player-1',
  PUZZLE_SOLVED: 'false',
  PUZZLE_TIME: ''
};

Object.assign(context, {
  user: 'Team',
  score: 1,
  questionCount: 1,
  pointsEarned: 5,
  maxPointsTotal: 5,
  results: [true],
  answers: ['A'],
  quizStartedAt: 1700000000,
  cfg: { collectPlayerUid: true, teamResults: true },
  STORAGE_KEYS: {
    CATALOG: 'CATALOG',
    QUIZ_SOLVED: 'QUIZ_SOLVED',
    PLAYER_UID: 'PLAYER_UID',
    PUZZLE_SOLVED: 'PUZZLE_SOLVED',
    PUZZLE_TIME: 'PUZZLE_TIME'
  },
  getStored(key){
    return store[key];
  },
  setStored(key, value){
    store[key] = value;
  },
  fetch(url, options){
    context.fetchArgs = { url, options };
    return {
      catch(fn){
        context.fetchCatchAttached = true;
        return this;
      }
    };
  },
  withBase(path){
    return path;
  },
  document: {
    getElementById(id){
      if(id === 'catalogs-data'){
        return { textContent: JSON.stringify(['main']) };
      }
      return null;
    },
    createElement(tag){
      return {
        tag,
        className: '',
        textContent: '',
        set href(value){
          this._href = value;
        },
        get href(){
          return this._href;
        }
      };
    }
  },
  summaryEl: {
    appended: [],
    appendChild(node){
      this.appended.push(node);
    }
  },
  styleButton(){ }
});

vm.runInNewContext(submissionMatch[0], context);

assert(context.fetchArgs, 'fetch not called');
assert.strictEqual(context.fetchArgs.url, '/results');
const payload = JSON.parse(context.fetchArgs.options.body);
assert.strictEqual(payload.catalog, 'MainSlug');
assert.strictEqual(payload.points, 5);
assert.strictEqual(payload.maxPoints, 5);
assert.strictEqual(payload.event_uid, 'evt-42');
assert.strictEqual(context.summaryEl.appended.length, 1);
assert.strictEqual(context.summaryEl.appended[0]._href, '/summary?event=evt-42');
assert.deepStrictEqual(JSON.parse(store.QUIZ_SOLVED), ['mainslug']);
console.log('ok');
