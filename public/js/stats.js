/* global UIkit */
document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('statsTableBody');
  const filter = document.getElementById('statsFilter');
  const refreshBtn = document.getElementById('statsRefreshBtn');
  const basePath = window.basePath || '';
  const withBase = path => basePath + path;

  let data = [];
  let catalogMap = null;

  function rotatePhotoImpl(path, img, link) {
    const cleanPath = path.replace(/\?.*$/, '');
    return fetch(withBase('/photos/rotate'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ path: cleanPath })
    })
      .then(r => { if (!r.ok) throw new Error('rotate'); })
      .then(() => {
        const t = Date.now();
        const newPath = `${cleanPath}?t=${t}`;
        img.src = withBase(newPath);
        if (link) {
          link.href = withBase(newPath);
          if (link.dataset && link.dataset.caption) {
            link.dataset.caption = link.dataset.caption
              .replace(/data-path='[^']*'/, `data-path='${newPath}'`);
          }
        }
        refreshLightboxes();
        return newPath;
      })
      .catch(() => {});
  }

  function refreshLightboxes() {
    if (typeof UIkit === 'undefined') return;
    document.querySelectorAll('[uk-lightbox]').forEach(el => {
      const lightbox = UIkit.getComponent(el, 'lightbox');
      if (lightbox) {
        lightbox.items = UIkit.lightbox(el).items;
      }
    });
  }

  const rotatePhoto = window.rotatePhoto || rotatePhotoImpl;
  if (!window.rotatePhoto) {
    window.rotatePhoto = rotatePhoto;
  }

  function fetchCatalogMap() {
    if (catalogMap) return Promise.resolve(catalogMap);
    return fetch(withBase('/kataloge/catalogs.json'), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(list => {
        const map = {};
        if (Array.isArray(list)) {
          list.forEach(c => {
            const name = c.name || '';
            if (c.uid) map[c.uid] = name;
            if (c.sort_order) map[c.sort_order] = name;
            if (c.slug) map[c.slug] = name;
          });
        }
        catalogMap = map;
        return map;
      })
      .catch(() => {
        catalogMap = {};
        return catalogMap;
      });
  }

  function renderTable(rows) {
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 7;
      td.textContent = 'Keine Daten';
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }
    rows.forEach(r => {
      const tr = document.createElement('tr');
      const cells = [
        r.name,
        r.attempt,
        r.catalogName || r.catalog,
        r.prompt || '',
        r.answer_text || '',
        r.correct ? '✓' : '✗'
      ];
      cells.forEach((c, idx) => {
        const td = document.createElement('td');
        td.textContent = c;
        tr.appendChild(td);
      });
      const photoTd = document.createElement('td');
      if (r.photo) {
        const wrap = document.createElement('span');
        wrap.className = 'photo-wrapper';

        const a = document.createElement('a');
        a.className = 'uk-inline rotate-link';
        a.href = withBase(r.photo);
        a.dataset.caption = `<button class='uk-icon-button lightbox-rotate-btn' type='button' uk-icon='history' data-path='${r.photo}' aria-label='Drehen'></button>`;
        a.dataset.attrs = 'class: uk-inverse-light';

        const img = document.createElement('img');
        img.src = withBase(r.photo);
        img.alt = 'Beweisfoto';
        img.className = 'proof-thumb';

        a.appendChild(img);
        wrap.appendChild(a);
        photoTd.appendChild(wrap);
      }
      tr.appendChild(photoTd);
      tbody.appendChild(tr);
    });
  }

  function renderNoEvent() {
    if (tbody) {
      tbody.innerHTML = '';
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 7;
      td.textContent = 'Kein Event ausgewählt';
      tr.appendChild(td);
      tbody.appendChild(tr);
    }
    if (filter) {
      filter.innerHTML = '<option value="">Alle</option>';
    }
  }

  function updateFilterOptions() {
    if (!filter) return;
    const names = Array.from(new Set(data.map(r => r.name))).sort();
    filter.innerHTML = '<option value="">Alle</option>';
    names.forEach(n => {
      const opt = document.createElement('option');
      opt.value = n;
      opt.textContent = n;
      filter.appendChild(opt);
    });
  }

  function applyFilter() {
    const name = filter && filter.value ? filter.value : '';
    const rows = name ? data.filter(r => r.name === name) : data;
    renderTable(rows);
  }

  function load() {
    const currentEventUid = (window.quizConfig || {}).event_uid || '';
    if (!currentEventUid) {
      data = [];
      renderNoEvent();
      return;
    }
    Promise.all([
      fetchCatalogMap(),
      fetch(withBase('/question-results.json')).then(r => r.json())
    ])
      .then(([catMap, rows]) => {
        rows.forEach(r => {
          if (!r.catalogName && catMap[r.catalog]) r.catalogName = catMap[r.catalog];
        });
        data = rows;
        updateFilterOptions();
        applyFilter();
      })
      .catch(err => console.error(err));
  }

  filter?.addEventListener('change', applyFilter);
  refreshBtn?.addEventListener('click', e => {
    e.preventDefault();
    load();
  });

  function initRotateButtons() {
    document.querySelectorAll('.photo-rotate-btn').forEach(btn => {
      const wrap = btn.closest('.photo-wrapper');
      const img = wrap ? wrap.querySelector('img') : null;
      const link = wrap ? wrap.querySelector('a') : null;
      const path = btn.dataset.path || (link ? link.getAttribute('href') : '');
      if (!img || !path) return;
      btn.addEventListener('click', e => {
        e.preventDefault();
        rotatePhoto(path, img, link);
      });
    });
  }

  if (refreshBtn && typeof UIkit !== 'undefined') {
    UIkit.icon(refreshBtn);
  }

  if (!window.lightboxRotateHandler) {
    window.lightboxRotateHandler = e => {
      const btn = e.target.closest('.lightbox-rotate-btn');
      if (!btn) return;
      e.preventDefault();
      const path = btn.dataset.path || '';
      const panel = document.querySelector('.uk-lightbox-panel .uk-active');
      const img = panel ? panel.querySelector('picture img, img') :
                  document.querySelector('.uk-lightbox-items .uk-active img') ||
                  document.querySelector('.uk-lightbox-panel img.uk-inverse-light');
      if (img && path) {
        const clean = path.replace(/\?.*$/, '');
        const links = document.querySelectorAll(`a.rotate-link[href^='${clean}']`);
        const link = links[0] || null;
        rotatePhoto(path, img, link).then(newPath => {
          if (newPath) {
            links.forEach(a => {
              a.href = newPath;
              if (a.dataset && a.dataset.caption) {
                a.dataset.caption = a.dataset.caption
                  .replace(/data-path='[^']*'/, `data-path='${newPath}'`);
              }
            });
            btn.dataset.path = newPath;
          }
        });
      }
    };
    document.body.addEventListener('click', window.lightboxRotateHandler, { capture: true });
  }

  load();
});
