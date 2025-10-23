const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

class FakeTextNode {
  constructor(text, document){
    this.nodeType = 3;
    this.ownerDocument = document;
    this.parentNode = null;
    this._textContent = String(text);
  }
  get textContent(){
    return this._textContent;
  }
  set textContent(value){
    this._textContent = String(value);
  }
  remove(){
    if(this.parentNode){
      this.parentNode.removeChild(this);
    }
  }
}

class FakeElement {
  constructor(tagName, document){
    this.nodeType = 1;
    this.tagName = tagName.toUpperCase();
    this.ownerDocument = document;
    this.parentNode = null;
    this.childNodes = [];
    this._children = [];
    this.attributes = {};
    this.dataset = {};
    this.eventListeners = {};
    this.style = {};
    this.hidden = false;
    this.type = '';
    this.value = '';
    this.disabled = false;
    this.files = [];
    this.tabIndex = 0;
    this.href = '';
    this.target = '';
    this.src = '';
    this._classSet = new Set();
    this._className = '';
    this._id = '';
    this._textContent = '';
  }
  get children(){
    return this._children;
  }
  appendChild(child){
    let node = child;
    if(typeof node === 'string'){
      node = new FakeTextNode(node, this.ownerDocument);
    }
    if(node.parentNode){
      node.parentNode.removeChild(node);
    }
    this.childNodes.push(node);
    if(node.nodeType === 1){
      this._children.push(node);
      node.ownerDocument = this.ownerDocument;
    }
    node.parentNode = this;
    this._textContent = '';
    return node;
  }
  append(...nodes){
    nodes.forEach(node => this.appendChild(node));
  }
  removeChild(child){
    const indexNode = this.childNodes.indexOf(child);
    if(indexNode !== -1){
      this.childNodes.splice(indexNode, 1);
    }
    if(child.nodeType === 1){
      const index = this._children.indexOf(child);
      if(index !== -1){
        this._children.splice(index, 1);
      }
    }
    if(child.parentNode === this){
      child.parentNode = null;
    }
    return child;
  }
  remove(){
    if(this.parentNode){
      this.parentNode.removeChild(this);
    }
  }
  setAttribute(name, value){
    const val = String(value);
    this.attributes[name] = val;
    if(name === 'id'){
      this.id = val;
    }else if(name === 'class'){
      this.className = val;
    }
  }
  getAttribute(name){
    return this.attributes[name];
  }
  addEventListener(type, handler){
    if(!this.eventListeners[type]){
      this.eventListeners[type] = [];
    }
    this.eventListeners[type].push(handler);
  }
  removeEventListener(type, handler){
    const list = this.eventListeners[type];
    if(!list) return;
    const index = list.indexOf(handler);
    if(index !== -1){
      list.splice(index, 1);
    }
  }
  dispatchEvent(event){
    const list = this.eventListeners[event.type] || [];
    list.forEach(fn => fn.call(this, event));
  }
  get className(){
    return this._className;
  }
  set className(value){
    this._classSet.clear();
    String(value).split(/\s+/).forEach(cls => {
      if(cls) this._classSet.add(cls);
    });
    this._className = Array.from(this._classSet).join(' ');
    if(this._className){
      this.attributes.class = this._className;
    }else{
      delete this.attributes.class;
    }
  }
  get classList(){
    return {
      add: (...names) => {
        names.forEach(name => {
          if(name) this._classSet.add(name);
        });
        this.className = Array.from(this._classSet).join(' ');
      },
      remove: (...names) => {
        names.forEach(name => this._classSet.delete(name));
        this.className = Array.from(this._classSet).join(' ');
      },
      contains: name => this._classSet.has(name)
    };
  }
  get textContent(){
    if(this._textContent){
      return this._textContent;
    }
    if(this.childNodes.length){
      return this.childNodes.map(node => node.textContent || '').join('');
    }
    return '';
  }
  set textContent(value){
    this._textContent = String(value);
    this.childNodes.slice().forEach(child => child.remove());
    this._children = [];
  }
  get innerHTML(){
    return this._textContent;
  }
  set innerHTML(value){
    this._textContent = String(value);
    this.childNodes.slice().forEach(child => child.remove());
    this._children = [];
  }
  get id(){
    return this._id;
  }
  set id(value){
    const val = String(value);
    if(this._id && this.ownerDocument && this.ownerDocument.elements[this._id] === this){
      this.ownerDocument.unregisterId(this._id, this);
    }
    this._id = val;
    if(val && this.ownerDocument){
      this.ownerDocument.registerId(val, this);
    }
    if(val){
      this.attributes.id = val;
    }else{
      delete this.attributes.id;
    }
  }
}

class FakeDocument {
  constructor(){
    this.elements = {};
    this.eventListeners = {};
    this.body = new FakeElement('body', this);
  }
  createElement(tagName){
    return new FakeElement(tagName, this);
  }
  registerId(id, element){
    this.elements[id] = element;
  }
  unregisterId(id, element){
    if(this.elements[id] === element){
      delete this.elements[id];
    }
  }
  getElementById(id){
    return this.elements[id] || null;
  }
  addEventListener(type, handler){
    if(!this.eventListeners[type]){
      this.eventListeners[type] = [];
    }
    this.eventListeners[type].push(handler);
  }
  removeEventListener(type, handler){
    const list = this.eventListeners[type];
    if(!list) return;
    const index = list.indexOf(handler);
    if(index !== -1){
      list.splice(index, 1);
    }
  }
  dispatchEvent(event){
    const list = this.eventListeners[event.type] || [];
    list.slice().forEach(handler => handler.call(this, event));
  }
}

function findDescendant(element, predicate){
  if(predicate(element)) return element;
  for(const child of element.children){
    const found = findDescendant(child, predicate);
    if(found) return found;
  }
  return null;
}

(async () => {
  const document = new FakeDocument();
  const windowObj = { location: { search: '' }, basePath: '', quizConfig: {} };

  const STORAGE_KEYS = {
    PLAYER_NAME: 'playerName',
    PLAYER_UID: 'playerUid',
    CATALOG: 'catalog',
    CATALOG_NAME: 'catalogName',
    CATALOG_DESC: 'catalogDesc',
    CATALOG_COMMENT: 'catalogComment',
    CATALOG_UID: 'catalogUid',
    CATALOG_SORT: 'catalogSort',
    LETTER: 'letter',
    PUZZLE_SOLVED: 'puzzleSolved',
    PUZZLE_TIME: 'puzzleTime',
    QUIZ_SOLVED: 'quizSolved'
  };

  const storageData = new Map();
  const getStored = key => (storageData.has(key) ? storageData.get(key) : null);
  const setStored = (key, value) => { storageData.set(key, String(value)); };
  const clearStored = key => { storageData.delete(key); };

  storageData.set(STORAGE_KEYS.PLAYER_NAME, 'Team Ä');

  const catalogResponse = [
    { uid: 'cat-1', name: 'Katalog Eins', slug: 'katalog-eins' }
  ];
  const resultsResponse = [
    { name: 'Team Ä', catalog: 'cat-1', correct: 4, total: 5, points: 8, max_points: 10, attempt: 1 },
    { name: 'Other', catalog: 'cat-1', correct: 5, total: 5, points: 10, max_points: 10, attempt: 1 }
  ];
  const questionResponse = [
    { name: 'Team Ä', catalog: 'cat-1', attempt: 1, final_points: 8, questionPoints: 10, efficiency: 0.8 },
    { name: 'Other', catalog: 'cat-1', attempt: 1, final_points: 10, questionPoints: 10, efficiency: 1 }
  ];

  const headersStub = { get: () => '' };

  const fetchMock = async url => {
    const base = url.split('?')[0];
    if(base === '/kataloge/catalogs.json'){
      return { ok: true, json: async () => catalogResponse, headers: headersStub };
    }
    if(base === '/results.json'){
      return { ok: true, json: async () => resultsResponse, headers: headersStub };
    }
    if(base === '/question-results.json'){
      return { ok: true, json: async () => questionResponse, headers: headersStub };
    }
    throw new Error('Unexpected fetch ' + url);
  };

  const UIkit = {
    modal(element){
      return {
        show(){ element._shown = true; },
        hide(){
          if(element._listeners && element._listeners.hidden){
            element._listeners.hidden.slice().forEach(fn => fn());
          }
        }
      };
    },
    util: {
      on(element, type, handler){
        if(!element._listeners){
          element._listeners = {};
        }
        if(!element._listeners[type]){
          element._listeners[type] = [];
        }
        element._listeners[type].push(handler);
      }
    },
    notification: () => {}
  };

  const context = {
    console,
    window: windowObj,
    document,
    UIkit,
    STORAGE_KEYS,
    getStored,
    setStored,
    clearStored,
    fetch: fetchMock,
    URLSearchParams,
    alert: () => {},
    setTimeout,
    clearTimeout,
    FormData: class FakeFormData {
      constructor(){ this.entries = []; }
      append(key, value){ this.entries.push([key, value]); }
    }
  };

  windowObj.window = windowObj;
  windowObj.document = document;
  windowObj.basePath = '';
  windowObj.quizConfig = {};
  windowObj.fetch = fetchMock;
  windowObj.UIkit = UIkit;
  windowObj.setTimeout = setTimeout;
  windowObj.clearTimeout = clearTimeout;

  const code = fs.readFileSync('public/js/summary.js', 'utf8');
  vm.runInNewContext(code, context);

  document.dispatchEvent({ type: 'DOMContentLoaded' });

  const flush = () => new Promise(resolve => setImmediate(resolve));
  await flush();
  await flush();
  await flush();

  const modal = document.body.children.find(el => el.attributes && Object.prototype.hasOwnProperty.call(el.attributes, 'uk-modal'));
  assert.ok(modal, 'results modal not attached');

  const dialog = modal.children[0];
  assert.ok(dialog, 'modal dialog missing');
  const userParagraph = dialog.children[1];
  assert.strictEqual(userParagraph.textContent, 'Team Ä');

  const contentWrap = document.getElementById('team-results');
  assert.ok(contentWrap, 'team results container missing');

  const table = findDescendant(contentWrap, el => el.tagName === 'TABLE');
  assert.ok(table, 'results table missing');
  const tbody = table.children.find(el => el.tagName === 'TBODY');
  assert.ok(tbody, 'table body missing');
  assert.strictEqual(tbody.children.length, 1, 'expected single catalog row');
  const row = tbody.children[0];
  const catalogLink = row.children[0].children[0];
  assert.strictEqual(catalogLink.textContent, 'Katalog Eins');
  assert.strictEqual(row.children[1].textContent, '4/5');
  assert.strictEqual(row.children[2].textContent, '8/10');

  console.log('ok');
})().catch(err => {
  console.error(err);
  process.exit(1);
});
