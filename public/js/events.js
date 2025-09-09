import {
  setCurrentEvent,
  switchPending,
  lastSwitchFailed,
  resetSwitchError,
  markSwitchFailed
} from './event-switcher.js';
const currentScript = document.currentScript;
const basePath = window.basePath || (currentScript ? currentScript.dataset.base || '' : '');
const withBase = (p) => basePath + p;

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
  return fetch(withBase(path), { credentials: 'same-origin', ...options, headers });
};

const notify = (msg, status = 'primary') => {
  if (typeof UIkit !== 'undefined' && UIkit.notification) {
    UIkit.notification({ message: msg, status });
  } else {
    alert(msg);
  }
};

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.copy-link').forEach((btn) => {
    btn.addEventListener('click', () => {
      const link = btn.dataset.link;
      if (navigator.clipboard?.writeText) {
        navigator.clipboard
          .writeText(link)
          .then(() => notify('Link kopiert', 'success'))
          .catch(() => notify('Kopieren fehlgeschlagen', 'danger'));
      } else {
        notify('Zwischenablage nicht verfügbar', 'warning');
      }
    });
  });

  const catalogsWrap = document.getElementById('catalogsTableWrap');
  const catalogsBody = document.getElementById('catalogsTableBody');
  const catalogsTable = document.getElementById('catalogsTable');
  const startLabel = catalogsTable?.dataset.startLabel || 'Start';

  document.querySelectorAll('.event-start').forEach((btn) => {
    btn.addEventListener('click', () => {
      const uid = btn.dataset.uid;
      csrfFetch(`/kataloge/catalogs.json?event=${encodeURIComponent(uid)}`, {
        headers: { Accept: 'application/json' }
      })
        .then((r) => r.json())
        .then((list) => {
          if (!catalogsBody || !Array.isArray(list)) return;
          catalogsBody.innerHTML = '';
          list.forEach((cat) => {
            const tr = document.createElement('tr');
            const tdName = document.createElement('td');
            tdName.textContent = cat.name || cat.slug || '';
            const tdAction = document.createElement('td');
            tdAction.className = 'uk-table-shrink';
            const startBtn = document.createElement('button');
            startBtn.type = 'button';
            startBtn.className = 'uk-button uk-button-primary';
            startBtn.textContent = startLabel;
            startBtn.addEventListener('click', () => {
              const slug = cat.slug || cat.uid || '';
              if (slug) {
                window.open(withBase(`/?event=${encodeURIComponent(uid)}&katalog=${encodeURIComponent(slug)}`), '_blank');
              }
            });
            tdAction.appendChild(startBtn);
            tr.appendChild(tdName);
            tr.appendChild(tdAction);
            catalogsBody.appendChild(tr);
          });
          if (catalogsWrap) catalogsWrap.hidden = false;
        })
        .catch(() => {
          if (catalogsWrap) catalogsWrap.hidden = true;
        });
    });
  });

  const selectWrap = document.getElementById('eventSelectWrap');
  const eventSelect = document.getElementById('eventSelect');
  const eventOpenBtn = document.getElementById('eventOpenBtn');
  const eventTitle = document.getElementById('eventTitle');
  const eventNotice = document.getElementById('eventNotice');
  const eventButtons = document.querySelectorAll('[data-event-btn]');
  const isAdminPage = document.body?.classList.contains('admin-page');
  let currentEventUid = '';
  const params = new URLSearchParams(window.location.search);
  const pageEventUid = params.get('event') || '';
  if (isAdminPage) {
    currentEventUid = eventSelect?.value || pageEventUid;
  }

  const updateEventButtons = (uid) => {
    eventButtons.forEach((btn) => {
      btn.disabled = !uid;
    });
  };

  function populate(list) {
    if (!eventSelect) return;
    eventSelect.innerHTML = '';
    if (!Array.isArray(list) || list.length === 0) {
      currentEventUid = '';
      window.quizConfig = {};
      if (selectWrap) selectWrap.hidden = true;
      if (eventTitle) eventTitle.hidden = false;
      if (eventNotice) {
        eventNotice.textContent = eventNotice.dataset.empty || 'Keine Veranstaltungen vorhanden';
        eventNotice.hidden = false;
      }
      updateEventButtons('');
      return;
    }
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = eventSelect.dataset.placeholder || '';
    eventSelect.appendChild(placeholder);
    list.forEach((ev) => {
      const opt = document.createElement('option');
      opt.value = ev.uid;
      opt.textContent = ev.name;
      if (ev.uid === currentEventUid) opt.selected = true;
      eventSelect.appendChild(opt);
    });
    const hasCurrent = list.some((ev) => ev.uid === currentEventUid);
    if (!hasCurrent) {
      currentEventUid = '';
      window.quizConfig = {};
    }
    if (selectWrap) selectWrap.hidden = false;
    if (eventTitle) eventTitle.hidden = true;
    if (currentEventUid) {
      if (eventNotice) eventNotice.hidden = true;
      updateEventButtons(currentEventUid);
      if (!switchPending && !lastSwitchFailed) {
        eventSelect.dispatchEvent(new Event('change'));
      }
    } else {
      eventSelect.value = '';
      updateEventButtons('');
      if (eventNotice) {
        eventNotice.textContent = eventNotice.dataset.required || '';
        eventNotice.hidden = false;
      }
    }
  }

  if (eventSelect && !isAdminPage) {
    const initialOptionCount = eventSelect.options.length;
    const warnIfEmpty = (list) => {
      if (initialOptionCount === 0 && (!Array.isArray(list) || list.length === 0)) {
        const msg = eventNotice?.dataset.empty || 'Keine Veranstaltungen vorhanden';
        notify(msg, 'warning');
      }
    };

    csrfFetch('/events.json', { headers: { Accept: 'application/json' } })
      .then((r) => {
        if (!r.ok) throw new Error('HTTP error');
        return r.json();
      })
      .then((events) => {
        currentEventUid = pageEventUid;
        const cfgPromise = currentEventUid
          ? csrfFetch(`/events/${encodeURIComponent(currentEventUid)}/config.json`).then((r) => r.json()).catch(() => ({}))
          : Promise.resolve({});
        cfgPromise
          .then((cfg) => {
            window.quizConfig = currentEventUid ? cfg : {};
            resetSwitchError();
            populate(events);
            warnIfEmpty(events);
          })
          .catch(() => {
            window.quizConfig = {};
            markSwitchFailed();
            populate(events);
            warnIfEmpty(events);
          });
      })
      .catch(() => {
        const msg = eventNotice?.dataset.error || 'Veranstaltungen konnten nicht geladen werden';
        notify(msg, 'danger');
      });
  } else if (eventSelect) {
    updateEventButtons(eventSelect.value);
  }

  eventSelect?.addEventListener('change', (e) => {
    const uid = eventSelect.value;
    const name = eventSelect.options[eventSelect.selectedIndex]?.textContent || '';
    updateEventButtons(uid);
    const urlEventUid = new URLSearchParams(window.location.search).get('event') || '';
    if (switchPending) {
      eventSelect.value = currentEventUid;
      updateEventButtons(currentEventUid);
      return;
    }
    if (e.isTrusted && uid && uid !== currentEventUid && uid !== urlEventUid) {
      setCurrentEvent(uid, name)
        .then((cfg) => {
          currentEventUid = uid;
          window.quizConfig = uid ? cfg : {};
          resetSwitchError();
          location.search = '?event=' + uid;
        })
        .catch((err) => {
          markSwitchFailed();
          notify(err.message || 'Fehler beim Wechseln des Events', 'danger');
          eventSelect.value = currentEventUid;
          updateEventButtons(currentEventUid);
        });
    }
  });

  document.addEventListener('current-event-changed', (e) => {
    const uid = e.detail.uid || '';
    currentEventUid = uid;
    updateEventButtons(uid);
  });

  eventOpenBtn?.addEventListener('click', () => {
    const uid = eventSelect?.value;
    if (uid) {
      window.open(withBase('/?event=' + uid), '_blank');
    }
  });
});
