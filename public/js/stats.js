/* global UIkit */
document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('statsTableBody');
  const filter = document.getElementById('statsFilter');
  const filterSuggestions = document.getElementById('statsFilterList');
  const refreshBtn = document.getElementById('statsRefreshBtn');
  const pagination = document.getElementById('statsPagination');
  const basePath = window.basePath || '';
  const withBase = path => basePath + path;
  const PAGE_SIZE = 25;
  const TABLE_COLUMN_COUNT = 10;

  function formatQuestionPoints(points, maxPoints) {
    const pts = Number.isFinite(points) ? points : Number.parseInt(points, 10);
    const normalizedPts = Number.isFinite(pts) ? pts : 0;
    const max = Number.isFinite(maxPoints) ? maxPoints : Number.parseInt(maxPoints, 10);
    if (Number.isFinite(max) && max > 0) {
      return `${normalizedPts}/${max}`;
    }
    return String(normalizedPts);
  }

  function parseOptionalNumber(value) {
    if (value === null || value === undefined) return null;
    if (typeof value === 'string' && value.trim() === '') return null;
    const num = Number(value);
    return Number.isFinite(num) ? num : null;
  }

  function formatEfficiencyValue(value) {
    const numeric = parseOptionalNumber(value);
    if (numeric === null) {
      return '–';
    }
    const clamped = Math.max(0, Math.min(numeric, 1));
    const percent = Math.round(clamped * 1000) / 10;
    const str = Number.isFinite(percent) ? percent.toString() : '0';
    return `${str.replace('.', ',')} %`;
  }

  let data = [];
  let catalogMap = null;
  let activeRequestId = 0;
  let filteredData = [];
  let currentPage = 1;

  function sanitizePageNumber(num, totalPages) {
    let n = Number.parseInt(num, 10);
    if (Number.isNaN(n) || n < 1) {
      n = 1;
    }
    if (Number.isFinite(totalPages) && totalPages >= 1 && n > totalPages) {
      n = totalPages;
    }
    return n;
  }

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

  function fetchCatalogMap(eventUid) {
    if (catalogMap) return Promise.resolve(catalogMap);
    const query = eventUid ? `?event=${encodeURIComponent(eventUid)}` : '';
    return fetch(withBase(`/kataloge/catalogs.json${query}`), { headers: { 'Accept': 'application/json' } })
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
      td.colSpan = TABLE_COLUMN_COUNT;
      td.textContent = 'Keine Daten';
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }
    rows.forEach(r => {
      const tr = document.createElement('tr');
      const basePointsText = formatQuestionPoints(r.points ?? 0, r.questionPoints ?? r.question_points ?? 0);
      const finalPointsRaw = r.finalPoints ?? r.final_points;
      const finalPointsValue = parseOptionalNumber(finalPointsRaw) ?? parseOptionalNumber(r.points) ?? 0;
      const normalizedFinal = Math.max(0, Math.round(finalPointsValue));
      const finalPointsText = formatQuestionPoints(normalizedFinal, r.questionPoints ?? r.question_points ?? 0);
      let efficiencyValue = parseOptionalNumber(r.efficiency);
      if (efficiencyValue === null) {
        const correctFlag = parseOptionalNumber(r.correct);
        if (correctFlag !== null) {
          efficiencyValue = correctFlag > 0 ? 1 : 0;
        }
      }
      const efficiencyText = formatEfficiencyValue(efficiencyValue);
      const correctNumeric = parseOptionalNumber(r.correct);
      const isCorrect = typeof r.isCorrect === 'boolean'
        ? r.isCorrect
        : (correctNumeric !== null ? correctNumeric > 0 : false);
      const cells = [
        r.name,
        r.attempt,
        r.catalogName || r.catalog,
        r.prompt || '',
        r.answer_text || '',
        isCorrect ? '✓' : '✗',
        basePointsText,
        finalPointsText,
        efficiencyText
      ];
      cells.forEach(c => {
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
      td.colSpan = TABLE_COLUMN_COUNT;
      td.textContent = 'Kein Event ausgewählt';
      tr.appendChild(td);
      tbody.appendChild(tr);
    }
    if (filter) {
      filter.value = '';
    }
    if (filterSuggestions) {
      filterSuggestions.innerHTML = '';
    }
    filteredData = [];
    currentPage = 1;
    updatePagination();
  }

  function updateFilterSuggestions() {
    if (!filterSuggestions) return;
    const names = Array.from(new Set(
      data
        .map(r => (typeof r.name === 'string' ? r.name.trim() : (r.name ? String(r.name) : '')))
        .filter(Boolean)
    )).sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
    filterSuggestions.innerHTML = '';
    names.forEach(n => {
      const opt = document.createElement('option');
      opt.value = n;
      filterSuggestions.appendChild(opt);
    });
  }

  function updatePagination() {
    if (!pagination) return;
    const totalPages = Math.ceil(filteredData.length / PAGE_SIZE);
    pagination.innerHTML = '';
    pagination.classList.toggle('uk-hidden', totalPages <= 1);
    if (totalPages <= 1) {
      return;
    }

    currentPage = sanitizePageNumber(currentPage, totalPages);

    const appendItem = (label, page, { disabled = false, active = false } = {}) => {
      const li = document.createElement('li');
      if (disabled) li.classList.add('uk-disabled');
      if (active) li.classList.add('uk-active');
      const a = document.createElement('a');
      a.href = '#';
      a.dataset.page = String(page);
      a.textContent = label;
      li.appendChild(a);
      pagination.appendChild(li);
    };

    const prevPage = currentPage > 1 ? currentPage - 1 : 1;
    appendItem('«', prevPage, { disabled: currentPage === 1 });

    for (let i = 1; i <= totalPages; i += 1) {
      appendItem(String(i), i, { active: currentPage === i });
    }

    const nextPage = currentPage < totalPages ? currentPage + 1 : totalPages;
    appendItem('»', nextPage, { disabled: currentPage === totalPages });
  }

  function renderCurrentPage() {
    const totalPages = Math.ceil(filteredData.length / PAGE_SIZE);
    currentPage = sanitizePageNumber(currentPage, totalPages);
    const start = (currentPage - 1) * PAGE_SIZE;
    const pageRows = filteredData.slice(start, start + PAGE_SIZE);
    renderTable(pageRows);
  }

  function applyFilter() {
    const query = filter && filter.value ? filter.value.trim().toLowerCase() : '';
    if (!query) {
      filteredData = data.slice();
    } else {
      filteredData = data.filter(r => {
        if (!r || r.name === undefined || r.name === null) return false;
        const value = typeof r.name === 'string' ? r.name : String(r.name);
        return value.toLowerCase().includes(query);
      });
    }
    currentPage = 1;
    updatePagination();
    renderCurrentPage();
  }

  function load() {
    const currentEventUid = window.getActiveEventId ? window.getActiveEventId() : '';
    if (!currentEventUid) {
      data = [];
      renderNoEvent();
      return;
    }
    const requestId = ++activeRequestId;
    const previousQuery = filter ? filter.value : '';
    Promise.all([
      fetchCatalogMap(currentEventUid),
      fetch(withBase(`/question-results.json?event_uid=${encodeURIComponent(currentEventUid)}`))
        .then(r => r.json())
    ])
      .then(([catMap, rows]) => {
        if (requestId !== activeRequestId || currentEventUid !== (window.getActiveEventId ? window.getActiveEventId() : '')) {
          return;
        }
        rows.forEach(r => {
          if (!r.catalogName && catMap[r.catalog]) r.catalogName = catMap[r.catalog];
        });
        data = rows;
        updateFilterSuggestions();
        if (filter) {
          filter.value = previousQuery;
        }
        applyFilter();
      })
      .catch(err => console.error(err));
  }

  filter?.addEventListener('input', applyFilter);
  pagination?.addEventListener('click', e => {
    const target = e.target.closest('a[data-page]');
    if (!target) return;
    e.preventDefault();
    const totalPages = Math.ceil(filteredData.length / PAGE_SIZE);
    if (totalPages <= 0) return;
    const requested = sanitizePageNumber(target.dataset.page, totalPages);
    if (requested === currentPage) return;
    currentPage = requested;
    renderCurrentPage();
    updatePagination();
  });
  refreshBtn?.addEventListener('click', e => {
    e.preventDefault();
    load();
  });

  document.addEventListener('current-event-changed', (e) => {
    if (e.detail?.pending) {
      data = [];
      renderNoEvent();
      return;
    }
    catalogMap = null;
    if (filter) {
      filter.value = '';
    }
    if (filterSuggestions) {
      filterSuggestions.innerHTML = '';
    }
    filteredData = [];
    currentPage = 1;
    updatePagination();
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
