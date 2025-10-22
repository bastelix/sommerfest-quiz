(function(){
  const currentScript = document.currentScript;
  const basePath = window.basePath || (currentScript ? currentScript.dataset.base || '' : '');
  const withBase = (p) => basePath + p;
  let eventId = document.body?.dataset.eventId || currentScript?.dataset.eventId || window.eventId || '';
  let switchEpoch = 0;
  const currentEventSelects = new Set();
  const MODULE_META = {
    rankings: {
      title: 'Live-Rankings',
    },
    results: {
      title: 'Scoreboard',
    },
    questions: {
      title: 'Knackpunkte',
    },
    info: {
      title: 'Event-Infos',
    },
    media: {
      title: 'Livestream & Highlights',
    },
  };
  const DEFAULT_DASHBOARD_MODULES = [
    { id: 'rankings', enabled: true },
    { id: 'results', enabled: true },
    { id: 'questions', enabled: false },
    { id: 'info', enabled: false },
    { id: 'media', enabled: false },
  ];
  let dashboardModulesState = DEFAULT_DASHBOARD_MODULES.map((module) => ({ ...module }));
  let dashboardToken = '';
  let currentEventSlug = '';

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
  const countdownEnabled = document.getElementById('countdownEnabled');
  const countdownInput = document.getElementById('countdown');
  const logoInput = document.getElementById('logo');
  const logoPreview = document.getElementById('logoPreview');
  const publishBtn = document.querySelector('.event-config-sidebar .uk-button-primary');
  const eventSettingsHeading = document.getElementById('eventSettingsHeading');
  const dashboardEnabledInput = document.getElementById('dashboardEnabled');
  const dashboardModulesInput = document.getElementById('dashboardModules');
  const dashboardModuleList = document.getElementById('dashboardModuleList');
  const dashboardRankingLimitInput = document.getElementById('dashboardRankingLimit');
  const dashboardRefreshInput = document.getElementById('dashboardRefreshInterval');
  const dashboardInfoInput = document.getElementById('dashboardInfo');
  const dashboardMediaInput = document.getElementById('dashboardMediaUrl');
  const dashboardShareLink = document.getElementById('dashboardShareLink');
  const dashboardShareStatus = document.getElementById('dashboardShareStatus');
  const dashboardTokenGenerate = document.getElementById('dashboardTokenGenerate');
  const dashboardTokenRevoke = document.getElementById('dashboardTokenRevoke');
  const dashboardLinkCopy = document.getElementById('dashboardLinkCopy');

  function notify(message, status = 'success') {
    if (typeof UIkit !== 'undefined' && typeof UIkit.notification === 'function') {
      UIkit.notification({ message, status });
    }
  }

  function copyToClipboard(text) {
    if (!text) {
      return Promise.reject(new Error('Leerer Text'));
    }
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      return navigator.clipboard.writeText(text);
    }
    return new Promise((resolve, reject) => {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'fixed';
      textarea.style.top = '-9999px';
      document.body.appendChild(textarea);
      textarea.select();
      try {
        const successful = document.execCommand('copy');
        document.body.removeChild(textarea);
        if (successful) {
          resolve();
        } else {
          reject(new Error('Copy command failed'));
        }
      } catch (err) {
        document.body.removeChild(textarea);
        reject(err);
      }
    });
  }

  function normalizeDashboardModules(modules) {
    const defaults = DEFAULT_DASHBOARD_MODULES.map((module) => ({ ...module }));
    const known = new Map(defaults.map((module) => [module.id, module.enabled]));
    const result = [];
    const seen = new Set();
    if (Array.isArray(modules)) {
      modules.forEach((entry) => {
        if (entry && typeof entry === 'object') {
          const id = String(entry.id || '');
          if (!id || !known.has(id) || seen.has(id)) return;
          const enabled = entry.enabled !== undefined
            ? !!entry.enabled
            : known.get(id);
          result.push({ id, enabled: !!enabled });
          seen.add(id);
        } else if (typeof entry === 'string' && known.has(entry) && !seen.has(entry)) {
          result.push({ id: entry, enabled: !!known.get(entry) });
          seen.add(entry);
        }
      });
    }
    defaults.forEach((module) => {
      if (!seen.has(module.id)) {
        result.push({ ...module });
      }
    });
    return result;
  }

  function updateDashboardModuleInput() {
    if (!dashboardModulesInput) return;
    try {
      dashboardModulesInput.value = JSON.stringify(dashboardModulesState);
    } catch (err) {
      dashboardModulesInput.value = '[]';
    }
  }

  function renderDashboardModules() {
    if (!dashboardModuleList) return;
    dashboardModuleList.innerHTML = '';
    dashboardModulesState.forEach((module, index) => {
      const row = document.createElement('div');
      row.className = 'dashboard-module-row uk-flex uk-flex-middle uk-margin-small';
      row.dataset.moduleId = module.id;

      const labelWrap = document.createElement('div');
      labelWrap.className = 'uk-flex-1';
      const label = document.createElement('label');
      label.className = 'uk-flex uk-flex-middle uk-flex-between';

      const textSpan = document.createElement('span');
      textSpan.textContent = MODULE_META[module.id]?.title || module.id;
      label.appendChild(textSpan);

      const toggle = document.createElement('input');
      toggle.type = 'checkbox';
      toggle.checked = !!module.enabled;
      toggle.addEventListener('change', () => {
        module.enabled = toggle.checked;
        updateDashboardModuleInput();
        applyDashboardModuleRules();
        markDirty();
      });
      label.appendChild(toggle);
      labelWrap.appendChild(label);
      row.appendChild(labelWrap);

      const controls = document.createElement('div');
      controls.className = 'uk-button-group';

      const upBtn = document.createElement('button');
      upBtn.type = 'button';
      upBtn.className = 'uk-icon-button';
      upBtn.setAttribute('uk-icon', 'chevron-up');
      upBtn.disabled = index === 0;
      upBtn.addEventListener('click', () => {
        if (index === 0) return;
        const target = dashboardModulesState.splice(index, 1)[0];
        dashboardModulesState.splice(index - 1, 0, target);
        renderDashboardModules();
        updateDashboardModuleInput();
        applyDashboardModuleRules();
        markDirty();
      });
      controls.appendChild(upBtn);

      const downBtn = document.createElement('button');
      downBtn.type = 'button';
      downBtn.className = 'uk-icon-button';
      downBtn.setAttribute('uk-icon', 'chevron-down');
      downBtn.disabled = index === dashboardModulesState.length - 1;
      downBtn.addEventListener('click', () => {
        if (index === dashboardModulesState.length - 1) return;
        const target = dashboardModulesState.splice(index, 1)[0];
        dashboardModulesState.splice(index + 1, 0, target);
        renderDashboardModules();
        updateDashboardModuleInput();
        applyDashboardModuleRules();
        markDirty();
      });
      controls.appendChild(downBtn);

      row.appendChild(controls);
      dashboardModuleList.appendChild(row);
    });
    updateDashboardModuleInput();
  }

  function computeShareUrl(tokenValue) {
    if (!tokenValue || !currentEventSlug) return '';
    const baseUrl = window.baseUrl || `${window.location.origin}${basePath}`;
    return `${baseUrl.replace(/\/$/, '')}/events/${encodeURIComponent(currentEventSlug)}/dashboard?token=${encodeURIComponent(tokenValue)}`;
  }

  function updateDashboardShareState() {
    const enabled = dashboardEnabledInput ? dashboardEnabledInput.checked : false;
    const hasEvent = !!eventId;
    const hasToken = dashboardToken !== '';
    const link = enabled && hasToken ? computeShareUrl(dashboardToken) : '';
    if (dashboardShareLink) {
      dashboardShareLink.value = link;
    }
    if (dashboardShareStatus) {
      if (!hasEvent) {
        dashboardShareStatus.textContent = 'Bitte zuerst ein Event auswählen.';
      } else if (!enabled) {
        dashboardShareStatus.textContent = 'Aktiviere das Dashboard, um einen öffentlichen Link zu erzeugen.';
      } else if (!hasToken) {
        dashboardShareStatus.textContent = 'Noch kein Link erzeugt.';
      } else {
        const suffix = dashboardToken.slice(-6);
        dashboardShareStatus.textContent = `Link aktiv (Token …${suffix}).`;
      }
    }
    if (dashboardTokenGenerate) {
      dashboardTokenGenerate.disabled = !hasEvent;
    }
    if (dashboardTokenRevoke) {
      dashboardTokenRevoke.disabled = !enabled || !hasToken || !hasEvent;
    }
    if (dashboardLinkCopy) {
      dashboardLinkCopy.disabled = !enabled || !hasToken || !hasEvent;
    }
  }

  function applyDashboardModuleRules() {
    const dashboardEnabled = dashboardEnabledInput ? dashboardEnabledInput.checked : false;
    const modulesById = new Map(dashboardModulesState.map((module) => [module.id, module.enabled]));
    document.querySelectorAll('[data-dashboard-section]').forEach((section) => {
      const disableSection = !dashboardEnabled;
      section.querySelectorAll('input, textarea, select, button').forEach((el) => {
        if (el === dashboardModulesInput) return;
        if (!el.dataset.moduleField) {
          el.disabled = disableSection;
        }
      });
      section.classList.toggle('uk-disabled', disableSection);
    });
    document.querySelectorAll('[data-module-field]').forEach((el) => {
      const moduleId = el.dataset.moduleField || '';
      const moduleEnabled = dashboardEnabled && modulesById.get(moduleId) !== false;
      el.disabled = !moduleEnabled;
      const wrapper = el.closest(`[data-module-field-wrapper="${moduleId}"]`) || el.closest('[data-module-field-wrapper]');
      if (wrapper) {
        wrapper.classList.toggle('module-disabled', !moduleEnabled && dashboardEnabled);
      }
    });
  }

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
    applyDashboardModuleRules();
    updateDashboardShareState();
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
    dashboardModulesState = DEFAULT_DASHBOARD_MODULES.map((module) => ({ ...module }));
    dashboardToken = '';
    currentEventSlug = '';
    renderDashboardModules();
    updateDashboardShareState();
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
        dashboardModulesState = normalizeDashboardModules(config?.dashboardModules);
        dashboardToken = typeof config?.dashboardShareToken === 'string' ? config.dashboardShareToken : '';
        currentEventSlug = typeof event?.slug === 'string' ? event.slug : '';
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
        renderDashboardModules();
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
    }
    dashboardEnabledInput?.addEventListener('change', () => {
      applyRules();
      markDirty();
    });
    puzzleWordEnabled?.addEventListener('change', applyRules);
    countdownEnabled?.addEventListener('change', applyRules);
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
    dashboardTokenGenerate?.addEventListener('click', () => {
      if (!eventId) {
        notify('Bitte zuerst ein Event auswählen.', 'warning');
        return;
      }
      dashboardTokenGenerate.disabled = true;
      if (dashboardShareStatus) {
        dashboardShareStatus.textContent = 'Erzeuge Link …';
      }
      csrfFetch(`/admin/event/${encodeURIComponent(eventId)}/dashboard-token`, {
        method: 'POST',
      })
        .then((res) => {
          if (!res.ok) {
            throw new Error('Link konnte nicht erzeugt werden.');
          }
          return res.json();
        })
        .then((payload) => {
          const tokenValue = typeof payload?.token === 'string' ? payload.token : '';
          if (!tokenValue) {
            throw new Error('Antwort enthielt kein Token.');
          }
          dashboardToken = tokenValue;
          updateDashboardShareState();
          notify('Neuer Dashboard-Link erstellt.', 'success');
        })
        .catch((err) => {
          console.error(err);
          notify(err.message || 'Link konnte nicht erzeugt werden.', 'danger');
        })
        .finally(() => {
          updateDashboardShareState();
        });
    });
    dashboardTokenRevoke?.addEventListener('click', () => {
      if (!eventId) {
        notify('Bitte zuerst ein Event auswählen.', 'warning');
        return;
      }
      dashboardTokenRevoke.disabled = true;
      if (dashboardShareStatus) {
        dashboardShareStatus.textContent = 'Deaktiviere Link …';
      }
      csrfFetch(`/admin/event/${encodeURIComponent(eventId)}/dashboard-token`, {
        method: 'DELETE',
      })
        .then((res) => {
          if (!res.ok && res.status !== 204) {
            throw new Error('Link konnte nicht deaktiviert werden.');
          }
          dashboardToken = '';
          updateDashboardShareState();
          notify('Dashboard-Link deaktiviert.', 'primary');
        })
        .catch((err) => {
          console.error(err);
          notify(err.message || 'Link konnte nicht deaktiviert werden.', 'danger');
        })
        .finally(() => {
          updateDashboardShareState();
        });
    });
    dashboardLinkCopy?.addEventListener('click', () => {
      const link = dashboardShareLink?.value || '';
      if (!link) {
        notify('Kein aktiver Link verfügbar.', 'warning');
        return;
      }
      dashboardLinkCopy.disabled = true;
      copyToClipboard(link)
        .then(() => {
          notify('Dashboard-Link kopiert.', 'success');
        })
        .catch((err) => {
          console.error(err);
          notify('Link konnte nicht kopiert werden.', 'danger');
        })
        .finally(() => {
          updateDashboardShareState();
        });
    });
  });
})();
