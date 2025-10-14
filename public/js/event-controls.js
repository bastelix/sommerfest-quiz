import {
  setCurrentEvent,
  switchPending,
  lastSwitchFailed,
  resetSwitchState
} from './event-switcher.js';

const currentScript = document.currentScript;
const basePath = window.basePath || (currentScript ? currentScript.dataset.base || '' : '');
const withBase = path => basePath + path;

function notify(message, status = 'primary') {
  if (typeof UIkit !== 'undefined' && UIkit.notification) {
    UIkit.notification({ message, status, pos: 'top-center' });
  } else {
    // eslint-disable-next-line no-alert
    alert(message);
  }
}

function initEventControls() {
  const root = document.querySelector('[data-event-controls]');
  if (!root) return;

  const eventSelect = root.querySelector('#eventSelect');
  const eventSelectWrap = root.querySelector('#eventSelectWrap');
  const eventSearchInput = root.querySelector('#eventSearchInput');
  const eventOpenBtn = root.querySelector('#eventOpenBtn');
  const fetchErrorMessage = root.dataset.fetchError || 'Events could not be loaded';

  if (!eventSelect) return;

  let currentEventUid = eventSelect.dataset.currentEvent || '';
  const initialConfig = window.quizConfig || {};
  if (!currentEventUid && initialConfig.event_uid) {
    currentEventUid = initialConfig.event_uid;
  }
  if (!window.quizConfig) {
    window.quizConfig = {};
  }
  if (currentEventUid && !window.quizConfig.event_uid) {
    window.quizConfig.event_uid = currentEventUid;
  }

  function updateOpenButton(uid) {
    if (eventOpenBtn) {
      eventOpenBtn.disabled = !uid;
    }
  }

  function applySearchFilter() {
    if (!eventSearchInput) return;
    const term = eventSearchInput.value.toLowerCase();
    Array.from(eventSelect.options).forEach(opt => {
      if (!opt.value) return;
      const match = opt.textContent.toLowerCase().includes(term);
      opt.style.display = match ? '' : 'none';
    });
  }

  function populateEventSelect(list) {
    if (!eventSelect) return;
    eventSelect.innerHTML = '';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = eventSelect.dataset.placeholder || '';
    eventSelect.appendChild(placeholder);

    if (Array.isArray(list)) {
      list.forEach(ev => {
        if (!ev || !ev.uid) return;
        const opt = document.createElement('option');
        opt.value = ev.uid;
        opt.textContent = ev.name || ev.uid;
        eventSelect.appendChild(opt);
      });
    }

    const hasCurrent = Array.from(eventSelect.options).some(opt => opt.value === currentEventUid);
    if (!hasCurrent) {
      currentEventUid = '';
      window.quizConfig.event_uid = '';
    }

    eventSelect.value = currentEventUid || '';
    eventSelect.dispatchEvent(new Event('change'));
    if (eventSelectWrap) eventSelectWrap.hidden = false;
    if (eventSearchInput) {
      eventSearchInput.hidden = !(Array.isArray(list) && list.length >= 10);
      eventSearchInput.value = '';
      applySearchFilter();
    }
    updateOpenButton(currentEventUid);
  }

  function fetchEvents() {
    return fetch(withBase('/events.json'), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' }
    })
      .then(resp => {
        if (!resp.ok) {
          throw new Error('HTTP ' + resp.status);
        }
        return resp.json();
      })
      .then(events => {
        populateEventSelect(Array.isArray(events) ? events : []);
        resetSwitchState();
        return events;
      })
      .catch(err => {
        notify(err.message || fetchErrorMessage, 'danger');
      });
  }

  eventSelect.addEventListener('change', () => {
    const uid = eventSelect.value;
    const name = eventSelect.options[eventSelect.selectedIndex]?.textContent || '';
    updateOpenButton(uid);
    if (switchPending || lastSwitchFailed) {
      eventSelect.value = currentEventUid || '';
      updateOpenButton(currentEventUid);
      return;
    }
    if (uid && uid !== currentEventUid) {
      setCurrentEvent(uid, name)
        .then(cfg => {
          currentEventUid = uid;
          window.quizConfig = cfg || {};
          window.quizConfig.event_uid = uid;
        })
        .catch(err => {
          notify(err.message || 'Fehler beim Wechseln des Events', 'danger');
          eventSelect.value = currentEventUid || '';
          updateOpenButton(currentEventUid);
        });
    }
  });

  eventSearchInput?.addEventListener('input', () => {
    applySearchFilter();
  });

  eventOpenBtn?.addEventListener('click', () => {
    const uid = eventSelect.value;
    if (uid) {
      window.open(withBase('/?event=' + encodeURIComponent(uid)), '_blank');
    }
  });

  document.addEventListener('current-event-changed', e => {
    const uid = e.detail?.uid || '';
    currentEventUid = uid;
    if (uid) {
      window.quizConfig = e.detail?.config || {};
      window.quizConfig.event_uid = uid;
    }
    if (eventSelect && eventSelect.value !== uid) {
      eventSelect.value = uid || '';
      updateOpenButton(uid);
    }
  });

  fetchEvents();
}

document.addEventListener('DOMContentLoaded', initEventControls);
