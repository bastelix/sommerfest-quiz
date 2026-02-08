import {
  setCurrentEvent,
  switchPending,
  lastSwitchFailed,
  resetSwitchState,
  markSwitchError
} from './event-switcher.js';
const currentScript = document.currentScript;
const basePath = window.basePath || (currentScript ? currentScript.dataset.base || '' : '');
const withBase = (p) => basePath + p;
const getStored = window.getStored || (() => null);
const getStoredForEvent = window.getStoredForEvent || (() => null);
const STORAGE_KEYS = window.STORAGE_KEYS || {};

const resolveEventNamespace = () => {
  const table = document.getElementById('eventsTable');
  if (table && table.dataset.eventNamespace) {
    return table.dataset.eventNamespace;
  }
  const indicator = document.querySelector('[data-event-namespace]');
  if (indicator && indicator.dataset.eventNamespace) {
    return indicator.dataset.eventNamespace;
  }
  const params = new URLSearchParams(window.location.search);
  return params.get('namespace') || '';
};

const appendNamespaceParam = (url) => {
  const ns = resolveEventNamespace();
  if (!ns) return url;
  const separator = url.includes('?') ? '&' : '?';
  return url + separator + 'namespace=' + encodeURIComponent(ns);
};

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
  const eventsTable = document.getElementById('eventsTable');
  const eventsBody = document.getElementById('eventsTableBody');
  const eventsFallback = document.getElementById('eventsTableFallback');
  const eventsLoadMore = document.getElementById('eventsLoadMore');
  const eventsDataScript = document.getElementById('events-data');

  const catalogsWrap = document.getElementById('catalogsTableWrap');
  const catalogsBody = document.getElementById('catalogsTableBody');
  const catalogsTable = document.getElementById('catalogsTable');
  const startLabel = catalogsTable?.dataset.startLabel || 'Start';
  const playedLabel = catalogsTable?.dataset.playedLabel || 'Played';
  const missingLabel = catalogsTable?.dataset.missingLabel || 'Open';

  const copyLabel = eventsTable?.dataset.copyLabel || 'Link kopieren';
  const copySuccess = eventsTable?.dataset.copySuccess || 'Link kopiert';
  const copyError = eventsTable?.dataset.copyError || 'Kopieren fehlgeschlagen';
  const copyWarning = eventsTable?.dataset.copyWarning || 'Zwischenablage nicht verfügbar';
  const parsedChunkSize = Number.parseInt(eventsTable?.dataset.chunkSize || '', 10);
  const chunkSize = Number.isNaN(parsedChunkSize) ? 25 : Math.max(1, parsedChunkSize);

  const handleCopy = (link) => {
    if (!link) return;
    if (navigator.clipboard?.writeText) {
      navigator.clipboard
        .writeText(link)
        .then(() => notify(copySuccess, 'success'))
        .catch(() => notify(copyError, 'danger'));
    } else {
      notify(copyWarning, 'warning');
    }
  };

  const attachCopyHandler = (button) => {
    if (!button) return;
    const link = button.dataset.link || '';
    button.addEventListener('click', () => handleCopy(link));
  };

  const readSolvedCatalogs = (uid) => {
    const solved = new Set();
    if (typeof getStored === 'function' && STORAGE_KEYS.QUIZ_SOLVED) {
      try {
        const stored = getStoredForEvent(STORAGE_KEYS.QUIZ_SOLVED, uid)
          || getStored(STORAGE_KEYS.QUIZ_SOLVED)
          || '[]';
        JSON.parse(stored).forEach((s) => solved.add(String(s).toLowerCase()));
      } catch (e) {
        /* ignore malformed storage */
      }
    }
    return solved;
  };

  const renderCatalogs = (uid, list, solved) => {
    if (!catalogsBody || !Array.isArray(list)) return;
    catalogsBody.innerHTML = '';
    list.forEach((cat) => {
      const tr = document.createElement('tr');
      const tdName = document.createElement('td');
      tdName.textContent = cat.name || cat.slug || '';
      const tdStatus = document.createElement('td');
      const key = (cat.slug || cat.uid || '').toString().toLowerCase();
      tdStatus.textContent = solved.has(key) ? playedLabel : missingLabel;
      const tdAction = document.createElement('td');
      tdAction.className = 'uk-table-shrink';
      const startBtn = document.createElement('button');
      startBtn.type = 'button';
      startBtn.className = 'uk-button uk-button-primary';
      startBtn.textContent = startLabel;
      startBtn.addEventListener('click', () => {
        const slug = cat.slug || cat.uid || '';
        if (slug) {
          window.open(
            withBase(`/?event=${encodeURIComponent(uid)}&katalog=${encodeURIComponent(slug)}`),
            '_blank'
          );
        }
      });
      tdAction.appendChild(startBtn);
      tr.appendChild(tdName);
      tr.appendChild(tdStatus);
      tr.appendChild(tdAction);
      catalogsBody.appendChild(tr);
    });
  };

  const handleEventStart = (uid) => {
    if (!uid) return;
    const solved = readSolvedCatalogs(uid);
    csrfFetch(`/kataloge/catalogs.json?event=${encodeURIComponent(uid)}`, {
      headers: { Accept: 'application/json' }
    })
      .then((r) => r.json())
      .then((list) => {
        renderCatalogs(uid, list, solved);
        if (catalogsWrap) catalogsWrap.hidden = false;
      })
      .catch(() => {
        if (catalogsWrap) catalogsWrap.hidden = true;
      });
  };

  const attachStartHandler = (button) => {
    if (!button) return;
    const uid = button.dataset.uid || '';
    button.addEventListener('click', () => handleEventStart(uid));
  };

  const createEventRow = (event) => {
    const uid = event?.uid || event?.id || '';
    if (!uid) return null;
    const tr = document.createElement('tr');
    const tdName = document.createElement('td');
    const nameLink = document.createElement('a');
    nameLink.href = withBase(`/?event=${encodeURIComponent(uid)}`);
    nameLink.className = 'uk-link-heading';
    nameLink.textContent = event.name || uid;
    tdName.appendChild(nameLink);

    const tdDescription = document.createElement('td');
    tdDescription.textContent = event.description || '';

    const tdAction = document.createElement('td');
    tdAction.className = 'uk-text-nowrap';

    const startBtn = document.createElement('button');
    startBtn.type = 'button';
    startBtn.className = 'uk-button uk-button-primary event-action event-start';
    startBtn.dataset.uid = uid;
    startBtn.textContent = startLabel;
    attachStartHandler(startBtn);

    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'uk-button uk-button-default event-action copy-link';
    copyBtn.dataset.link = withBase(`/?event=${encodeURIComponent(uid)}`);
    copyBtn.textContent = copyLabel;
    attachCopyHandler(copyBtn);

    tdAction.appendChild(startBtn);
    tdAction.appendChild(copyBtn);
    tr.appendChild(tdName);
    tr.appendChild(tdDescription);
    tr.appendChild(tdAction);
    return tr;
  };

  const parseEventsData = () => {
    if (!eventsDataScript) {
      return [];
    }
    try {
      const raw = eventsDataScript.textContent || eventsDataScript.innerText || '[]';
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  };

  let lazyRenderingActive = false;

  const initLazyRendering = (eventList) => {
    if (!eventsBody || eventList.length === 0) {
      return;
    }
    lazyRenderingActive = true;
    eventsBody.innerHTML = '';

    let nextIndex = 0;
    let isRendering = false;
    let observer;
    let scrollListener;

    const finalizeIfDone = () => {
      if (nextIndex >= eventList.length) {
        if (observer) {
          observer.disconnect();
        }
        if (scrollListener) {
          window.removeEventListener('scroll', scrollListener);
          window.removeEventListener('resize', scrollListener);
          scrollListener = undefined;
        }
        if (eventsLoadMore) {
          eventsLoadMore.hidden = true;
        }
      }
    };

    const appendChunk = () => {
      if (isRendering || nextIndex >= eventList.length) {
        return 0;
      }
      isRendering = true;
      const fragment = document.createDocumentFragment();
      let rendered = 0;
      const end = Math.min(nextIndex + chunkSize, eventList.length);
      for (let i = nextIndex; i < end; i += 1) {
        const row = createEventRow(eventList[i]);
        if (row) {
          fragment.appendChild(row);
          rendered += 1;
        }
      }
      eventsBody.appendChild(fragment);
      nextIndex = end;
      isRendering = false;
      if (rendered > 0 && eventsFallback) {
        eventsFallback.remove();
      }
      if (nextIndex >= eventList.length && eventsLoadMore) {
        eventsLoadMore.hidden = true;
      }
      return rendered;
    };

    const maybeLoadMoreIfInView = () => {
      if (!eventsLoadMore || eventsLoadMore.hidden) {
        finalizeIfDone();
        return;
      }
      const rect = eventsLoadMore.getBoundingClientRect();
      if (rect.top <= window.innerHeight + 48) {
        appendChunk();
        finalizeIfDone();
      }
    };

    const initialRendered = appendChunk();
    if (initialRendered === 0 && eventsFallback) {
      lazyRenderingActive = false;
      if (eventsLoadMore) {
        eventsLoadMore.hidden = true;
      }
      return;
    }
    if (eventsLoadMore && nextIndex < eventList.length) {
      eventsLoadMore.hidden = false;
      if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver((entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            appendChunk();
            finalizeIfDone();
          }
        });
        observer.observe(eventsLoadMore);
      } else {
        scrollListener = () => {
          maybeLoadMoreIfInView();
        };
        window.addEventListener('scroll', scrollListener, { passive: true });
        window.addEventListener('resize', scrollListener);
      }
      maybeLoadMoreIfInView();
    } else if (eventsLoadMore) {
      eventsLoadMore.hidden = true;
    }
  };

  const eventsData = parseEventsData();
  if (eventsData.length > 0) {
    initLazyRendering(eventsData);
  }

  if (!lazyRenderingActive) {
    if (eventsFallback) {
      eventsFallback.hidden = false;
    }
    document.querySelectorAll('.copy-link').forEach((btn) => attachCopyHandler(btn));
    document.querySelectorAll('.event-start').forEach((btn) => attachStartHandler(btn));
  }

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
  } else {
    currentEventUid = window.getActiveEventId ? window.getActiveEventId() : pageEventUid;
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
      eventSelect.dispatchEvent(new Event('change'));
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

    csrfFetch(appendNamespaceParam('/events.json'), { headers: { Accept: 'application/json' } })
      .then((r) => {
        if (!r.ok) throw new Error('HTTP error');
        return r.json();
      })
      .then((events) => {
        currentEventUid = window.getActiveEventId ? window.getActiveEventId() : pageEventUid;
        const cfgPromise = currentEventUid
          ? csrfFetch(`/events/${encodeURIComponent(currentEventUid)}/config.json`).then((r) => {
              if (!r.ok) throw new Error('HTTP error');
              return r.json();
            })
          : Promise.resolve({});
        cfgPromise
          .then((cfg) => {
            window.quizConfig = currentEventUid ? cfg : {};
            resetSwitchState();
            populate(events);
            warnIfEmpty(events);
          })
          .catch(() => {
            window.quizConfig = {};
            markSwitchError();
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
    if (lastSwitchFailed) {
      resetSwitchState();
    }
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
          const nextUrl = new URL(window.location.href);
          nextUrl.searchParams.set('event', uid);
          window.location.assign(nextUrl.toString());
        })
        .catch((err) => {
          notify(err.message || 'Fehler beim Wechseln des Events', 'danger');
          eventSelect.value = currentEventUid;
          updateEventButtons(currentEventUid);
        });
    }
  });

  document.addEventListener('event:changed', (e) => {
    const uid = e.detail.uid || '';
    const pending = e.detail.pending === true;
    if (pending) {
      currentEventUid = '';
      updateEventButtons('');
      return;
    }
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
