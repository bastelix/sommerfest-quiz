/* global UIkit, STORAGE_KEYS, setStored, clearStored */

const jsonHeaders = { Accept: 'application/json' };

let quizScriptPromise;
let quizStarted = false;

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
    } else {
      console.warn('Ungültiger Katalog-Parameter:', id);
      UIkit?.notification?.({ message: 'Katalog nicht gefunden (slug: ' + id + ').', status: 'warning' });
      // Fallback: Übersicht/erste Option anzeigen
    }
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

  // Metadaten speichern
  const name = opt.textContent || opt.dataset.name || '';
  const desc = opt.dataset.desc || '';
  const comment = opt.dataset.comment || '';

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

  // Katalogdaten laden
  const file = opt.dataset.file;
  try {
    if (file) {
      const base = window.basePath || '';
      const res = await fetch(base + file, { headers: jsonHeaders });
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
