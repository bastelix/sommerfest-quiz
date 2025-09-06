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

  if (eventSelect) {
    csrfFetch('/events.json', { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .catch(() => [])
      .then((events) => {
        activeEventUid = pageEventUid || (events[0]?.uid || '');
        const cfgPromise = activeEventUid
          ? csrfFetch(`/events/${encodeURIComponent(activeEventUid)}/config.json`).then((r) => r.json()).catch(() => ({}))
          : Promise.resolve({});
        cfgPromise
          .then((cfg) => {
            window.quizConfig = cfg;
            populate(events);
          })
          .catch(() => {
            window.quizConfig = {};
            populate(events);
          });
      })
      .catch(() => {});
  }

  eventSelect?.addEventListener('change', () => {
    const uid = eventSelect.value;
    if (uid && uid !== activeEventUid) {
      const url = new URL(window.location.href);
      url.searchParams.set('event', uid);
      window.location.href = url.toString();
    }
  });

  eventOpenBtn?.addEventListener('click', () => {
    const uid = eventSelect?.value;
    if (uid) {
      window.open(withBase('/?event=' + uid), '_blank');
    }
  });
});
