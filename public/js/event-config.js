(function(){
  const currentScript = document.currentScript;
  const basePath = window.basePath || (currentScript ? currentScript.dataset.base || '' : '');
  const withBase = (p) => basePath + p;
  let eventId = document.body?.dataset.eventId || currentScript?.dataset.eventId || window.eventId || '';
  const currentEventSelects = new Set();

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

  const syncCurrentEventSelect = (select, uid) => {
    if (!select) return;
    const value = uid || '';
    const options = Array.from(select.options || []);
    const hasOption = options.some((opt) => (opt.value || '') === value);
    if (hasOption) {
      select.value = value;
    } else {
      select.value = '';
    }
    select.dataset.currentEventValue = hasOption ? value : '';
  };

  const setCurrentEvent = (uid, name) => {
    return csrfFetch('/config.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event_uid: uid })
    })
      .then((resp) => {
        if (!resp.ok) {
          return resp.text().then((text) => {
            throw new Error(text || 'Fehler beim Wechseln des Events');
          });
        }
        if (uid) {
          return fetch(withBase(`/admin/event/${encodeURIComponent(uid)}`), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
          }).then((res) => {
            if (!res.ok) {
              return res.text().then((text) => {
                throw new Error(text || 'Fehler beim Laden des Events');
              });
            }
            return res.json();
          });
        }
        return { event: null, config: {} };
      })
      .then((detail) => {
        const config = detail?.config || {};
        document.dispatchEvent(
          new CustomEvent('current-event-changed', { detail: { uid, name, config } })
        );
        return detail;
      })
      .catch((err) => {
        if (err instanceof TypeError) {
          throw new Error('Server unreachable');
        }
        throw err;
      });
  };

  const initializeCurrentEventSelect = (select) => {
    if (!select || currentEventSelects.has(select)) return;
    currentEventSelects.add(select);
    syncCurrentEventSelect(select, eventId);
    select.addEventListener('change', () => {
      const prevValue = select.dataset.currentEventValue || '';
      const prevDisabled = select.disabled;
      const uid = select.value || '';
      if (uid === prevValue) {
        return;
      }
      const option = select.options[select.selectedIndex] || null;
      const name = uid ? ((option?.textContent || '').trim()) : '';
      select.disabled = true;
      setCurrentEvent(uid, name)
        .then(() => {
          syncCurrentEventSelect(select, uid);
        })
        .catch((err) => {
          console.error(err);
          UIkit?.notification({ message: err.message || 'Fehler beim Wechseln des Events', status: 'danger' });
          syncCurrentEventSelect(select, prevValue);
        })
        .finally(() => {
          select.disabled = prevDisabled;
        });
    });
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
  const logoInput = document.getElementById('logo');
  const logoPreview = document.getElementById('logoPreview');
  const publishBtn = document.querySelector('.event-config-sidebar .uk-button-primary');
  const eventSettingsHeading = document.getElementById('eventSettingsHeading');

  function applyRules() {
    if (puzzleWordEnabled && puzzleWord && puzzleFeedback) {
      const enabled = puzzleWordEnabled.checked;
      puzzleWord.disabled = !enabled;
      puzzleFeedback.disabled = !enabled;
    }
  }

  function clearForm() {
    document.querySelectorAll('form input, form textarea, form select').forEach((el) => {
      if (el.type === 'checkbox') {
        el.checked = false;
      } else if (el.type === 'file') {
        el.value = '';
      } else {
        el.value = '';
      }
    });
    if (logoPreview) {
      logoPreview.src = '';
      logoPreview.hidden = true;
    }
    applyRules();
  }

  function loadConfig(uid) {
    if (!uid) {
      applyRules();
      return Promise.resolve();
    }
    return fetch(withBase(`/admin/event/${uid}`), { credentials: 'same-origin', cache: 'no-store' })
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
        applyRules();
        isDirty = false;
      })
      .catch(() => {
        UIkit?.notification({ message: 'Konfiguration konnte nicht geladen werden', status: 'danger' });
        applyRules();
      });
  }

  function save() {
    if (!eventId) return;
    const body = collectData();
    csrfFetch(`/admin/event/${eventId}`, {
      method: 'PATCH',
      body
    })
      .catch(() => {})
      .finally(() => {
        isDirty = false;
      });
  }

  let autosaveTimer;
  let isDirty = false;
  function markDirty() {
    isDirty = true;
    queueAutosave();
  }
  function queueAutosave() {
    if (!isDirty) return;
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(save, 800);
  }

  document.addEventListener('current-event-changed', (e) => {
    const { uid = '', name = '' } = e.detail || {};
    eventId = uid;
    clearTimeout(autosaveTimer);
    isDirty = false;
    clearForm();
    currentEventSelects.forEach((select) => {
      syncCurrentEventSelect(select, uid);
    });
    if (eventSettingsHeading) {
      eventSettingsHeading.textContent = name
        ? `${name} – ${eventSettingsHeading.dataset.title}`
        : eventSettingsHeading.dataset.title;
    }
    if (uid) {
      loadConfig(uid).catch(() => {
        window.location.href = withBase(`/admin/event-config?event=${uid}`);
      });
    } else {
      applyRules();
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-current-event-select]').forEach((select) => {
      initializeCurrentEventSelect(select);
    });
    if (eventId) {
      loadConfig(eventId);
    } else {
      applyRules();
    }
    puzzleWordEnabled?.addEventListener('change', applyRules);
    publishBtn?.addEventListener('click', (e) => { e.preventDefault(); save(); });
    document.querySelectorAll('form input, form textarea, form select').forEach((el) => {
      el.addEventListener('input', markDirty);
      el.addEventListener('change', markDirty);
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
