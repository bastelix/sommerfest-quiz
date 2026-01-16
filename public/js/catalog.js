/* global UIkit, STORAGE_KEYS, getStored, setStored, clearStored */

// Fallbacks, falls storage.js nicht geladen wurde
if (typeof STORAGE_KEYS === 'undefined') {
  globalThis.STORAGE_KEYS = {};
}
if (typeof getStored !== 'function') {
  globalThis.getStored = key => {
    try {
      return (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(key)) ||
             (typeof localStorage !== 'undefined' && localStorage.getItem(key));
    } catch (e) {
      return null;
    }
  };
}
if (typeof setStored !== 'function') {
  globalThis.setStored = (key, value) => {
    try {
      if (typeof sessionStorage !== 'undefined') {
        sessionStorage.setItem(key, value);
      }
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem(key, value);
      }
    } catch (e) { /* empty */ }
  };
}
if (typeof clearStored !== 'function') {
  globalThis.clearStored = key => {
    try {
      if (typeof sessionStorage !== 'undefined') {
        sessionStorage.removeItem(key);
      }
      if (typeof localStorage !== 'undefined') {
        localStorage.removeItem(key);
      }
    } catch (e) { /* empty */ }
  };
}

const jsonHeaders = { Accept: 'application/json' };

const htmlEntityMap = {
  '&amp;': '&',
  '&lt;': '<',
  '&gt;': '>',
  '&quot;': '"',
  '&apos;': '\'',
  '&nbsp;': '\u00a0'
};

function decodeHtmlEntities(value) {
  if (!value) {
    return '';
  }

  if (typeof document !== 'undefined' && typeof document.createElement === 'function') {
    if (!decodeHtmlEntities._el) {
      decodeHtmlEntities._el = document.createElement('textarea');
    }
    const el = decodeHtmlEntities._el;
    if (el) {
      try {
        el.innerHTML = value;
        if (typeof el.value === 'string' && el.value && el.value !== value) {
          return el.value;
        }
        if (typeof el.textContent === 'string' && el.textContent && el.textContent !== value) {
          return el.textContent;
        }
      } catch (e) {
        /* empty */
      }
    }
  }

  if (value.indexOf('&') === -1) {
    return value;
  }

  let decoded = value
    .replace(/&#(\d+);/g, (_, num) => {
      const code = parseInt(num, 10);
      return Number.isNaN(code) ? _ : String.fromCharCode(code);
    })
    .replace(/&#x([0-9a-f]+);/gi, (_, hex) => {
      const code = parseInt(hex, 16);
      return Number.isNaN(code) ? _ : String.fromCharCode(code);
    });

  decoded = decoded.replace(/&(amp|lt|gt|quot|apos|nbsp);/gi, entity => {
    const lower = entity.toLowerCase();
    return Object.prototype.hasOwnProperty.call(htmlEntityMap, lower)
      ? htmlEntityMap[lower]
      : entity;
  });

  return decoded;
}

async function buildSolvedSet(cfg){
  const solved = new Set();
  try{
    const prev = getStored(STORAGE_KEYS.QUIZ_SOLVED);
    if(prev){
      JSON.parse(prev).forEach(s => solved.add(String(s).toLowerCase()));
    }
  }catch(e){ /* empty */ }
  if(cfg?.competitionMode){
    try{
      let path = '/results.json';
      const uid = typeof window.getActiveEventId === 'function' ? window.getActiveEventId() : '';
      if(uid){
        path = `/results.json?event_uid=${encodeURIComponent(uid)}`;
      }
      const url = (typeof withBase === 'function') ? withBase(path) : path;
      const res = await fetch(url, { headers: (typeof jsonHeaders !== 'undefined') ? jsonHeaders : { Accept: 'application/json' } });
      if(res.ok){
        const list = await res.json();
        const user = getStored(STORAGE_KEYS.PLAYER_NAME);
        if(user){
          for(const t of list){
            if(t && t.name === user && t.catalog){
              solved.add(String(t.catalog).toLowerCase());
            }
          }
        }
      }
    }catch(e){ /* empty */ }
    try{
      setStored(STORAGE_KEYS.QUIZ_SOLVED, JSON.stringify(Array.from(solved)));
    }catch(e){ /* empty */ }
  }
  return solved;
}

globalThis.buildSolvedSet = buildSolvedSet;

let quizScriptPromise;
let quizStarted = false;
let solvedSet = new Set();

async function loadQuizScript() {
  if (typeof window.startQuiz === 'function') {
    return;
  }
  if (!quizScriptPromise) {
    quizScriptPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = (window.basePath || '') + 'js/quiz.js';
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }
  await quizScriptPromise;
}

function getRemainingCatalogNames() {
  try {
    const solved = new Set(
      JSON.parse(getStored(STORAGE_KEYS.QUIZ_SOLVED) || '[]')
        .map(s => String(s).toLowerCase())
    );

    let catalogs = [];
    const dataEl = document.getElementById('catalogs-data');
    if (dataEl) {
      try {
        catalogs = JSON.parse(dataEl.textContent);
      } catch (e) {
        catalogs = [];
      }
    }

    if (!Array.isArray(catalogs) || catalogs.length === 0) {
      const select = document.getElementById('catalog-select');
      if (select) {
        catalogs = Array.from(select.options).map(o => ({
          name: o.textContent || o.dataset.name || '',
          slug: o.dataset.slug || o.value || '',
          uid: o.dataset.uid || '',
          sort_order: o.dataset.sortOrder || ''
        }));
      }
    }

    return catalogs
      .filter(c => {
        const id = (c.slug || c.uid || c.sort_order || '')
          .toString()
          .toLowerCase();
        return id && !solved.has(id);
      })
      .map(c => c.name || c.slug || c.uid || c.sort_order);
  } catch (e) {
    return [];
  }
}

function showRemainingModal(names) {
  const modal = document.createElement('div');
  modal.setAttribute('uk-modal', '');
  modal.setAttribute('aria-modal', 'true');
  const list = names.map(n => `<li>${n}</li>`).join('');
  modal.innerHTML = `
    <div class="uk-modal-dialog uk-modal-body">
      <div class="uk-card qr-card uk-card-body uk-padding-small uk-width-1-1">
        ${names.length
          ? `<p>Folgende Kataloge sind noch offen:</p>
        <ul class="uk-list uk-list-bullet">${list}</ul>`
          : '<p>Alle Kataloge wurden bereits gelöst.</p>'}
        <button id="remaining-close" class="uk-button uk-button-primary uk-width-1-1">Schließen</button>
      </div>
    </div>`;
  document.body.appendChild(modal);
  const ui = UIkit.modal(modal);
  const btn = modal.querySelector('#remaining-close');
  btn.addEventListener('click', () => ui.hide());
  UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
  ui.show();
}

function getSummaryUrl() {
  const uid = typeof window.getActiveEventId === 'function' ? window.getActiveEventId() : '';
  const summaryPath = '/summary' + (uid ? `?event=${encodeURIComponent(uid)}` : '');
  if (typeof withBase === 'function') {
    return withBase(summaryPath);
  }
  const base = window.basePath || '';
  return base + summaryPath;
}

function notifyAllSolvedAndRedirect() {
  const message = 'Alle Kataloge bereits gelöst.';
  if (UIkit?.notification) {
    UIkit.notification({ message, status: 'primary', timeout: 1500 });
  }
  const target = getSummaryUrl();
  window.setTimeout(() => {
    window.location.href = target;
  }, 1500);
}

async function startQuizOnce(qs, skipIntro = false) {
  if (quizStarted) {
    return;
  }
  quizStarted = true;
  await loadQuizScript();
  const questions = qs || window.quizQuestions || [];
  window.startQuiz(questions, skipIntro);
}

async function init() {
  const cfg = window.quizConfig || {};
  solvedSet = await buildSolvedSet(cfg);

  // Container sicherstellen
  let quizContainer = document.getElementById('quiz');
  if (!quizContainer) {
    const mount = document.querySelector('main') || document.body;
    quizContainer = document.createElement('div');
    quizContainer.id = 'quiz';
    mount.appendChild(quizContainer);
  }

  // URL-Parameter lesen (unterstützt mehrere Varianten)
  const params = new URLSearchParams(window.location.search);
  let id = (
    params.get('slug') ||
    params.get('katalog') ||
    params.get('catalog') ||
    params.get('k') ||
    ''
  ).toLowerCase();
  if (!id) {
    const segments = window.location.pathname.split('/').filter(Boolean);
    const last = segments.pop();
    if (last) {
      id = decodeURIComponent(last).toLowerCase();
    }
  }
  const autoParam = params.get('autostart') ||
                    params.get('auto') ||
                    params.get('start') ||
                    params.get('play');
  const autostart = autoParam !== null &&
                    autoParam !== '0' &&
                    autoParam.toLowerCase() !== 'false';

  // Select suchen (ID oder data-role)
  const select = document.getElementById('catalog-select') ||
                 document.querySelector('[data-role="catalog-select"]');

  if (cfg.competitionMode) {
    if (id && solvedSet.has(id)) {
      const names = getRemainingCatalogNames();
      if (names.length) {
        showRemainingModal(names);
      } else {
        notifyAllSolvedAndRedirect();
      }
      return;
    }
    if (select) {
      Array.from(select.options).forEach(o => {
        const slugOpt = (o.value || o.dataset.slug || '').toLowerCase();
        if (solvedSet.has(slugOpt)) {
          o.disabled = true;
        }
      });
    }
  }

  // --- Fall A: Kein <select> vorhanden ---
  if (!select) {
    if (id) {
      // 1) Versuche Inline-Daten <script id="abc-data">...</script>
      const inline = document.getElementById(id + '-data');
      if (inline) {
        try {
          const data = JSON.parse(inline.textContent);
          // Optional: Name/Desc/Comment aus data oder Metas setzen
          setStored(STORAGE_KEYS.CATALOG_NAME, id.toUpperCase());
          setStored(STORAGE_KEYS.CATALOG, id);
          clearStored(STORAGE_KEYS.CATALOG_DESC);
          clearStored(STORAGE_KEYS.CATALOG_COMMENT);
          await startQuizOnce(data || [], false);
          return;
        } catch (e) {
          console.warn('Inline-Daten ungültig für slug=', id, e);
        }
      }

      // 2) Kein Inline: Zeige zumindest Intro, damit der Button erscheint
      setStored(STORAGE_KEYS.CATALOG_NAME, id.toUpperCase());
      setStored(STORAGE_KEYS.CATALOG, id);
      clearStored(STORAGE_KEYS.CATALOG_DESC);
      clearStored(STORAGE_KEYS.CATALOG_COMMENT);
      if (!autostart) {
        await startQuizOnce([], false);
      }
      UIkit?.notification?.({ message: 'Katalog nicht gefunden (slug: ' + id + ').', status: 'warning' });
      return;
    }

    // Weder select noch slug → nichts zu tun
    UIkit?.notification?.({ message: 'Kein Katalog wählbar.', status: 'warning' });
    return;
  }

  // --- Fall B: <select> existiert ---
  // Direktwahl per slug, falls vorhanden
  if (id) {
    const match = Array.from(select.options).find(o => {
      const value = (o.value || '').toLowerCase();
      const slug  = (o.dataset.slug || '').toLowerCase();
      return value === id || slug === id;
    });
    if (match) {
      select.value = match.value;
      // Dropdown ausblenden
      select.style.display = 'none';
      const selectLabel = document.querySelector('label[for="catalog-select"]');
      if (selectLabel) selectLabel.style.display = 'none';
      handleSelection(match, autostart);
      return;
    }
    console.warn('Ungültiger Katalog-Parameter:', id);
    UIkit?.notification?.({ message: 'Katalog nicht gefunden (slug: ' + id + ').', status: 'warning' });
    // Fallback: Übersicht/erste Option anzeigen
  }

  // Fallbacks, wenn kein slug oder kein Match
  select.style.display = '';
  const selectLabel = document.querySelector('label[for="catalog-select"]');
  if (selectLabel) selectLabel.style.display = '';

  if (select.options.length === 1 && !id) {
    handleSelection(select.options[0], autostart);
  } else {
    const opt = select.selectedOptions[0];
    if (opt) handleSelection(opt, autostart);
  }

  select.addEventListener('change', () => {
    const opt = select.selectedOptions[0];
    handleSelection(opt);
  });
}

async function handleSelection(opt, autostart = false) {
  if (!opt) {
    return;
  }

  const cfg = window.quizConfig || {};
  const slug = (opt.value || opt.dataset.slug || '').toLowerCase();
  const letter = opt.dataset.letter || '';
  if (cfg.competitionMode && solvedSet.has(slug)) {
    const names = getRemainingCatalogNames();
    if (names.length) {
      showRemainingModal(names);
    } else {
      notifyAllSolvedAndRedirect();
    }
    opt.disabled = true;
    return;
  }

  // Metadaten speichern
  const name = opt.textContent || opt.dataset.name || '';
  const desc = opt.dataset.desc || '';
  const rawComment = opt.dataset.comment || '';
  const comment = rawComment ? decodeHtmlEntities(rawComment) : '';

  setStored(STORAGE_KEYS.CATALOG_NAME, name);
  if (desc) {
    setStored(STORAGE_KEYS.CATALOG_DESC, desc);
  } else {
    clearStored(STORAGE_KEYS.CATALOG_DESC);
  }
  if (comment) {
    setStored(STORAGE_KEYS.CATALOG_COMMENT, comment);
  } else {
    clearStored(STORAGE_KEYS.CATALOG_COMMENT);
  }

  setStored(STORAGE_KEYS.CATALOG_UID, opt.dataset.uid || '');
  setStored(STORAGE_KEYS.CATALOG_SORT, opt.dataset.sortOrder || '');

  setStored(STORAGE_KEYS.CATALOG, opt.value || opt.dataset.slug || '');

  if (!cfg.competitionMode) {
    clearStored(STORAGE_KEYS.QUIZ_SOLVED);
  }
  clearStored(STORAGE_KEYS.PUZZLE_SOLVED);
  clearStored(STORAGE_KEYS.PUZZLE_TIME);
  clearStored(STORAGE_KEYS.LETTER);

  if (cfg.puzzleWordEnabled && letter) {
    setStored(STORAGE_KEYS.LETTER, letter);
  } else {
    clearStored(STORAGE_KEYS.LETTER);
  }

  const puzzleText = document.getElementById('puzzle-solved-text');
  if (puzzleText) {
    puzzleText.textContent = '';
    puzzleText.style.display = 'none';
  }
  const puzzleBtn = document.getElementById('check-puzzle-btn');
  if (puzzleBtn) puzzleBtn.style.display = 'none';
  const puzzleInfo = document.getElementById('puzzle-info');
  if (puzzleInfo) puzzleInfo.textContent = '';

  try {
    await postSession('catalog', { slug: opt.value });
  } catch (e) {
    UIkit?.notification?.({ message: 'Session-Update fehlgeschlagen.', status: 'danger' });
    console.error('session/catalog request failed', e);
  }

  // Katalogdaten laden
  const file = opt.dataset.file;
  try {
    if (file) {
      const base = window.basePath || '';
      const path = file.startsWith('/kataloge/') ? file : 'kataloge/' + file;
      const res = await fetch(base + path, { headers: jsonHeaders });
      const data = await res.json();
      window.quizQuestions = data;
      await startQuizOnce(data || window.quizQuestions || [], false);
      return;
    }
  } catch (e) {
    console.error('Katalogdatei konnte nicht geladen werden', e);
  }
  if (!autostart) {
    await startQuizOnce([], false);
  }
}

document.addEventListener('DOMContentLoaded', init);
if (document.readyState !== 'loading') {
  init();
}
