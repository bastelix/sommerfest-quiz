(function(){
  const currentScript = document.currentScript;
  const basePath = window.basePath || (currentScript ? currentScript.dataset.base || '' : '');
  const withBase = (p) => basePath + p;
  const eventId = document.body?.dataset.eventId || currentScript?.dataset.eventId || window.eventId || '';

  const getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
    currentScript?.dataset.csrf ||
    window.csrfToken || '';

  const csrfFetch = (path, options = {}) => {
    const token = getCsrfToken();
    const headers = {
      ...(options.headers || {}),
      ...(token ? { 'X-CSRF-Token': token } : {})
    };
    return fetch(withBase(path), { credentials: 'same-origin', cache: 'no-store', ...options, headers });
  };

  function collectData() {
    const data = {};
    document.querySelectorAll('form input, form textarea, form select').forEach((el) => {
      const key = el.name || el.id;
      if (!key) return;
      if (el.type === 'checkbox') {
        data[key] = el.checked;
      } else {
        data[key] = el.value;
      }
    });
    return data;
  }

  const puzzleWordEnabled = document.getElementById('puzzleWordEnabled');
  const puzzleWord = document.getElementById('puzzleWord');
  const puzzleFeedback = document.getElementById('puzzleFeedback');
  const saveBtn = document.querySelector('.event-config-sidebar .uk-button-secondary');
  const publishBtn = document.querySelector('.event-config-sidebar .uk-button-primary');
  const presetLinks = document.querySelectorAll('.event-config-sidebar .uk-card:nth-child(2) .uk-list a');

  function applyRules() {
    if (puzzleWordEnabled && puzzleWord && puzzleFeedback) {
      const enabled = puzzleWordEnabled.checked;
      puzzleWord.disabled = !enabled;
      puzzleFeedback.disabled = !enabled;
    }
  }

  function save() {
    if (!eventId) return;
    const body = JSON.stringify(collectData());
    csrfFetch(`/admin/event/${eventId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body
    }).catch(() => {});
  }

  let autosaveTimer;
  function queueAutosave() {
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(save, 800);
  }

  document.addEventListener('DOMContentLoaded', () => {
    applyRules();
    puzzleWordEnabled?.addEventListener('change', applyRules);
    saveBtn?.addEventListener('click', (e) => { e.preventDefault(); save(); });
    publishBtn?.addEventListener('click', (e) => { e.preventDefault(); save(); });
    presetLinks.forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const preset = link.textContent?.trim().toLowerCase();
        if (preset === 'fragen importieren') {
          // preset example: enable puzzle word
          if (puzzleWordEnabled) {
            puzzleWordEnabled.checked = true;
            applyRules();
          }
        } else if (preset === 'layout laden') {
          document.getElementById('primary-color')?.setAttribute('value', '#1e87f0');
        }
        queueAutosave();
      });
    });
    document.querySelectorAll('input, textarea, select').forEach((el) => {
      el.addEventListener('input', queueAutosave);
      el.addEventListener('change', queueAutosave);
    });
  });
})();
