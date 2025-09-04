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
    if (options.body instanceof FormData) {
      delete headers['Content-Type'];
    }
    return fetch(withBase(path), { credentials: 'same-origin', cache: 'no-store', ...options, headers });
  };

  function collectData() {
    const data = new FormData();
    document.querySelectorAll('form input, form textarea, form select').forEach((el) => {
      const key = el.name || el.id;
      if (!key) return;
      if (el.type === 'checkbox') {
        data.append(key, el.checked ? '1' : '0');
      } else if (el.type === 'file') {
        if (el.files?.[0]) data.append(key, el.files[0]);
      } else {
        let value = el.value;
        if (key === 'pageTitle' && !value) {
          value = 'Modernes Quiz mit UIkit';
        }
        data.append(key, value);
      }
    });
    return data;
  }

  const puzzleWordEnabled = document.getElementById('puzzleWordEnabled');
  const puzzleWord = document.getElementById('puzzleWord');
  const puzzleFeedback = document.getElementById('puzzleFeedback');
  const competitionMode = document.getElementById('competitionMode');
  const optQrLogin = document.getElementById('QRUser');
  const logoInput = document.getElementById('logo');
  const logoPreview = document.getElementById('logoPreview');
  const publishBtn = document.querySelector('.event-config-sidebar .uk-button-primary');

  function applyRules(shouldQueue) {
    const queue = typeof shouldQueue === 'boolean' ? shouldQueue : true;
    if (puzzleWordEnabled && puzzleWord && puzzleFeedback) {
      const enabled = puzzleWordEnabled.checked;
      puzzleWord.disabled = !enabled;
      puzzleFeedback.disabled = !enabled;
    }
    if (queue) queueAutosave();
  }

  function save() {
    if (!eventId) return;
    const body = collectData();
    csrfFetch(`/admin/event/${eventId}`, {
      method: 'PATCH',
      body
    })
      .then((res) => {
        if (!res.ok) throw new Error('Failed to save');
        UIkit?.notification({ message: 'Erfolgreich gespeichert', status: 'success', timeout: 2000 });
      })
      .catch(() => {
        UIkit?.notification({ message: 'Speichern fehlgeschlagen', status: 'danger' });
      });
  }

  let autosaveTimer;
  function queueAutosave() {
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(save, 800);
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (eventId) {
      fetch(withBase(`/admin/event/${eventId}`), { credentials: 'same-origin', cache: 'no-store' })
        .then((res) => {
          if (!res.ok) throw new Error('Failed to load');
          return res.json();
        })
        .then(({ event, config }) => {
          const data = { ...config };
          if (event?.name && !data.pageTitle) data.pageTitle = event.name;
          Object.entries(data).forEach(([key, value]) => {
            const el = document.getElementById(key);
            if (!el) return;
            if (el.type === 'checkbox') {
              el.checked = !!value;
            } else if (el.tagName === 'IMG') {
              el.src = value;
              el.hidden = !value;
            } else if (el.type !== 'file') {
              el.value = value ?? '';
            }
          });
          if (config?.logoPath && logoPreview) {
            logoPreview.src = withBase(config.logoPath);
            logoPreview.hidden = false;
          }
          applyRules(false);
          queueAutosave();
        })
        .catch(() => {
          UIkit?.notification({ message: 'Konfiguration konnte nicht geladen werden', status: 'danger' });
          applyRules(false);
        });
    } else {
      applyRules(false);
    }
    puzzleWordEnabled?.addEventListener('change', applyRules);
    competitionMode?.addEventListener('change', queueAutosave);
    optQrLogin?.addEventListener('change', queueAutosave);
    publishBtn?.addEventListener('click', (e) => { e.preventDefault(); save(); });
    document.querySelectorAll('input, textarea, select').forEach((el) => {
      el.addEventListener('input', queueAutosave);
      el.addEventListener('change', queueAutosave);
    });
    logoInput?.addEventListener('change', () => {
      const file = logoInput.files?.[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (ev) => {
        if (logoPreview) {
          logoPreview.src = ev.target.result;
          logoPreview.hidden = false;
        }
      };
      reader.readAsDataURL(file);
    });
  });
})();
