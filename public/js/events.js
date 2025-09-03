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
  document.querySelectorAll('.toggle-publish').forEach((btn) => {
    btn.addEventListener('click', () => {
      const uid = btn.dataset.uid;
      const published = btn.dataset.published === 'true';
      csrfFetch(`/events/${uid}/publish`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ published: !published })
      })
        .then((resp) => {
          if (resp.ok) {
            btn.dataset.published = (!published).toString();
            btn.textContent = !published ? 'Nicht veröffentlichen' : 'Veröffentlichen';
          } else {
            notify('Fehler beim Aktualisieren', 'danger');
          }
        })
        .catch(() => notify('Fehler beim Aktualisieren', 'danger'));
    });
  });
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

  const selectWrap = document.getElementById('eventSelectWrap');
  const eventSelect = document.getElementById('eventSelect');
  const eventOpenBtn = document.getElementById('eventOpenBtn');
  const eventTitle = document.getElementById('eventTitle');
  let activeEventUid = '';
  const params = new URLSearchParams(window.location.search);
  const pageEventUid = params.get('event') || '';

  function populate(list) {
    if (!eventSelect) return;
    eventSelect.innerHTML = '';
    if (!Array.isArray(list) || list.length === 0) {
      if (selectWrap) selectWrap.hidden = true;
      if (eventTitle) eventTitle.hidden = false;
      return;
    }
    list.forEach((ev) => {
      const opt = document.createElement('option');
      opt.value = ev.uid;
      opt.textContent = ev.name;
      if (ev.uid === activeEventUid) opt.selected = true;
      eventSelect.appendChild(opt);
    });
    if (selectWrap) selectWrap.hidden = false;
    if (eventTitle) eventTitle.hidden = true;
    eventSelect.dispatchEvent(new Event('change'));
  }

  function setActive(uid) {
    activeEventUid = uid;
    csrfFetch('/config.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event_uid: uid })
    })
      .then((resp) => {
        if (resp.ok) {
          window.location.reload();
        } else {
          notify('Fehler beim Speichern', 'danger');
        }
      })
      .catch(() => notify('Fehler beim Speichern', 'danger'));
  }

  if (eventSelect) {
    const cfgUrl = pageEventUid ? `/events/${pageEventUid}/config.json` : '/config.json';
    Promise.all([
      csrfFetch(cfgUrl).then((r) => r.json()).catch(() => ({})),
      csrfFetch('/events.json', { headers: { Accept: 'application/json' } }).then((r) => r.json()).catch(() => [])
    ]).then(([cfg, events]) => {
      activeEventUid = cfg.event_uid || '';
      window.quizConfig = cfg;
      populate(events);
    }).catch(() => {});
  }

  eventSelect?.addEventListener('change', () => {
    const uid = eventSelect.value;
    if (uid && uid !== activeEventUid) {
      setActive(uid);
    }
  });

  eventOpenBtn?.addEventListener('click', () => {
    const uid = eventSelect?.value;
    if (uid) {
      window.open(withBase('/?event=' + uid), '_blank');
    }
  });
});
