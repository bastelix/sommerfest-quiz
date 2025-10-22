(function(){
  const currentScript = document.currentScript;
  const basePath = window.basePath || (currentScript ? currentScript.dataset.base || '' : '');
  const withBase = (p) => basePath + p;
  let eventId = document.body?.dataset.eventId || currentScript?.dataset.eventId || window.eventId || '';
  let switchEpoch = 0;
  const currentEventSelects = new Set();
  const notify = (message, status = 'primary') => {
    if (window.UIkit?.notification) {
      UIkit.notification({ message, status });
    }
  };
  const modulesList = document.querySelector('[data-dashboard-modules]');
  const modulesInput = document.getElementById('dashboardModules');
  const shareInputs = {
    public: document.querySelector('[data-share-link="public"]'),
    sponsor: document.querySelector('[data-share-link="sponsor"]')
  };
  let currentShareToken = '';
  let currentSponsorToken = '';
  let currentEventSlug = '';
  const DEFAULT_MODULES = [
    { id: 'header', enabled: true },
    { id: 'rankings', enabled: true, options: { metrics: ['points', 'puzzle', 'catalog'] } },
    { id: 'results', enabled: true },
    { id: 'wrongAnswers', enabled: false },
    { id: 'infoBanner', enabled: false },
    { id: 'media', enabled: false },
  ];
  const METRIC_KEYS = ['points', 'puzzle', 'catalog'];

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
        const epoch = ++switchEpoch;
        const detailPayload = { uid, name, config, epoch };
        document.dispatchEvent(new CustomEvent('event:changed', { detail: detailPayload }));
        document.dispatchEvent(new CustomEvent('current-event-changed', { detail: detailPayload }));
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
      if (el.hasAttribute('data-skip-save')) return;
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

  function readModulesFromDom() {
    if (!modulesList) return [];
    const modules = [];
    modulesList.querySelectorAll('[data-module-id]').forEach((item) => {
      const id = item.dataset.moduleId || '';
      if (!id) return;
      const toggle = item.querySelector('[data-module-toggle]');
      const enabled = toggle ? toggle.checked : true;
      const entry = { id, enabled };
      if (id === 'rankings') {
        const metrics = [];
        item.querySelectorAll('[data-module-metric]').forEach((metricEl) => {
          if (metricEl.checked) {
            const value = metricEl.value || '';
            if (value && !metrics.includes(value)) {
              metrics.push(value);
            }
          }
        });
        entry.options = { metrics: metrics.length ? metrics : METRIC_KEYS };
      }
      modules.push(entry);
    });
    return modules;
  }

  function applyModules(modules) {
    if (!modulesList) return;
    const configured = Array.isArray(modules) && modules.length ? modules : DEFAULT_MODULES;
    const map = new Map();
    modulesList.querySelectorAll('[data-module-id]').forEach((item) => {
      map.set(item.dataset.moduleId, item);
    });
    configured.forEach((module) => {
      const item = map.get(module.id);
      if (!item) return;
      modulesList.appendChild(item);
      const toggle = item.querySelector('[data-module-toggle]');
      if (toggle) toggle.checked = !!module.enabled;
      if (module.id === 'rankings') {
        const metrics = Array.isArray(module.options?.metrics) && module.options.metrics.length
          ? module.options.metrics
          : METRIC_KEYS;
        item.querySelectorAll('[data-module-metric]').forEach((metricEl) => {
          metricEl.checked = metrics.includes(metricEl.value);
        });
      }
    });
    DEFAULT_MODULES.forEach((module) => {
      if (!configured.some((entry) => entry.id === module.id)) {
        const item = map.get(module.id);
        if (!item) return;
        modulesList.appendChild(item);
        const toggle = item.querySelector('[data-module-toggle]');
        if (toggle) toggle.checked = !!module.enabled;
        if (module.id === 'rankings') {
          item.querySelectorAll('[data-module-metric]').forEach((metricEl) => {
            metricEl.checked = METRIC_KEYS.includes(metricEl.value);
          });
        }
      }
    });
    updateModulesInput(false);
  }

  function updateModulesInput(mark = false) {
    if (!modulesInput) return;
    const modules = readModulesFromDom();
    try {
      modulesInput.value = JSON.stringify(modules);
    } catch (err) {
      modulesInput.value = '[]';
    }
    if (mark) {
      markDirty();
    }
  }

  function buildShareLink(variant) {
    const token = variant === 'sponsor' ? currentSponsorToken : currentShareToken;
    if (!token || !currentEventSlug) return '';
    const path = `/event/${encodeURIComponent(currentEventSlug)}/dashboard/${encodeURIComponent(token)}`;
    const url = new URL(withBase(path), window.location.origin);
    if (variant === 'sponsor') {
      url.searchParams.set('variant', 'sponsor');
    }
    return url.toString();
  }

  function updateShareInputs() {
    if (shareInputs.public) {
      shareInputs.public.value = buildShareLink('public');
    }
    if (shareInputs.sponsor) {
      shareInputs.sponsor.value = buildShareLink('sponsor');
    }
  }

  const puzzleWordEnabled = document.getElementById('puzzleWordEnabled');
  const puzzleWord = document.getElementById('puzzleWord');
  const puzzleFeedback = document.getElementById('puzzleFeedback');
  const countdownEnabled = document.getElementById('countdownEnabled');
  const countdownInput = document.getElementById('countdown');
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
    if (countdownEnabled && countdownInput) {
      const enabledCountdown = countdownEnabled.checked;
      countdownInput.disabled = !enabledCountdown;
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
    currentShareToken = '';
    currentSponsorToken = '';
    currentEventSlug = '';
    if (modulesList) {
      applyModules([]);
    }
    updateShareInputs();
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
          if (Array.isArray(value)) return;
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
        currentEventSlug = event?.slug || currentEventSlug;
        currentShareToken = config?.dashboardShareToken || '';
        currentSponsorToken = config?.dashboardSponsorToken || '';
        if (modulesList) {
          applyModules(config?.dashboardModules || []);
        }
        updateShareInputs();
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

  document.addEventListener('event:changed', (e) => {
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
      if (modulesList) {
        applyModules([]);
      }
      updateShareInputs();
    }
    puzzleWordEnabled?.addEventListener('change', applyRules);
    countdownEnabled?.addEventListener('change', applyRules);
    publishBtn?.addEventListener('click', (e) => { e.preventDefault(); save(); });
    document.querySelectorAll('form input, form textarea, form select').forEach((el) => {
      if (el.hasAttribute('data-skip-save')) return;
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
    modulesList?.addEventListener('change', (event) => {
      if (event.target.matches('[data-module-toggle], [data-module-metric]')) {
        updateModulesInput(true);
      }
    });
    modulesList?.addEventListener('moved', () => {
      updateModulesInput(true);
    });
    document.querySelectorAll('[data-copy-link]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const variant = btn.dataset.copyLink === 'sponsor' ? 'sponsor' : 'public';
        const input = shareInputs[variant];
        if (!input || !input.value) {
          notify('Kein Link verfügbar', 'warning');
          return;
        }
        if (navigator.clipboard?.writeText) {
          navigator.clipboard
            .writeText(input.value)
            .then(() => notify('Link kopiert', 'success'))
            .catch(() => notify('Kopieren fehlgeschlagen', 'danger'));
        } else {
          input.select();
          try {
            document.execCommand('copy');
            notify('Link kopiert', 'success');
          } catch (err) {
            notify('Kopieren fehlgeschlagen', 'danger');
          }
        }
      });
    });
    document.querySelectorAll('[data-rotate-token]').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (!eventId) {
          notify('Kein Event ausgewählt', 'warning');
          return;
        }
        const variant = btn.dataset.rotateToken === 'sponsor' ? 'sponsor' : 'public';
        btn.disabled = true;
        csrfFetch(`/admin/event/${eventId}/dashboard-token`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ variant })
        })
          .then((res) => {
            if (!res.ok) throw new Error('rotate-failed');
            return res.json();
          })
          .then((payload) => {
            const token = payload?.token || '';
            if (variant === 'sponsor') {
              currentSponsorToken = token;
            } else {
              currentShareToken = token;
            }
            updateShareInputs();
            notify('Neues Token erstellt', 'success');
          })
          .catch(() => {
            notify('Token konnte nicht erneuert werden', 'danger');
          })
          .finally(() => {
            btn.disabled = false;
          });
      });
    });
    if (modulesInput && !modulesInput.value) {
      updateModulesInput(false);
    }
  });
})();
