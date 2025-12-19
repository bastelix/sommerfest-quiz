/* global UIkit */
import { applyLazyImage } from './lazy-images.js';
import { ResultsDataService, computeRankings } from './results-data-service.js';
import { formatTimestamp, formatPointsCell, insertSoftHyphens, escapeHtml, formatDuration } from './results-utils.js';

const TABLE_COLUMN_COUNT = 9;

const parseOptionalNumber = (value) => {
  if (value === null || value === undefined) return null;
  if (typeof value === 'string' && value.trim() === '') return null;
  const num = Number(value);
  return Number.isFinite(num) ? num : null;
};

const parseBooleanFlag = (value) => {
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    if (normalized === '' || normalized === '0' || normalized === 'false' || normalized === 'off' || normalized === 'no') {
      return false;
    }
    return true;
  }
  if (typeof value === 'number') {
    return value > 0;
  }
  if (typeof value === 'boolean') {
    return value;
  }
  return Boolean(value);
};

const getPuzzleWordEnabled = () => {
  const cfg = window.quizConfig || {};
  if (Object.prototype.hasOwnProperty.call(cfg, 'puzzleWordEnabled')) {
    return parseBooleanFlag(cfg.puzzleWordEnabled);
  }
  if (Object.prototype.hasOwnProperty.call(cfg, 'puzzle_word_enabled')) {
    return parseBooleanFlag(cfg.puzzle_word_enabled);
  }
  return null;
};

document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('resultsTableBody');
  const wrongBody = document.getElementById('wrongTableBody');
  const refreshBtn = document.getElementById('resultsRefreshBtn');
  const grid = document.getElementById('rankingGrid');
  const pagination = document.getElementById('resultsPagination');
  const filterInput = document.getElementById('resultsFilter');
  const filterSuggestions = document.getElementById('resultsFilterList');
  const basePath = window.basePath || '';
  const withBase = path => basePath + path;
  const params = new URLSearchParams(window.location.search);
  let fallbackEventUid = window.getActiveEventId ? window.getActiveEventId() : (params.get('event') || '');
  let activeRequestId = 0;
  const dataService = new ResultsDataService({ basePath });

  const PAGE_SIZE = 10;
  let resultsData = [];
  let filteredResults = [];
  let currentPage = 1;

  function sanitizePageNumber(num, total) {
    let n = parseInt(num, 10);
    if (Number.isNaN(n) || n < 1) n = 1;
    if (total && n > total) n = total;
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
        applyLazyImage(img, withBase(newPath), { forceLoad: true });
        if (link) {
          link.href = withBase(newPath);
          if (link.dataset && link.dataset.caption) {
            const safePath = escapeHtml(newPath);
            link.dataset.caption = link.dataset.caption
              .replace(/data-path='[^']*'/, `data-path='${safePath}'`);
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
      const basePointsCell = formatPointsCell(r.points ?? r.correct ?? 0, r.max_points ?? 0);
      const resolvedFinalPoints = (() => {
        const direct = parseOptionalNumber(r.finalPoints ?? r.final_points);
        if (direct !== null) return Math.max(0, Math.round(direct));
        const legacy = parseOptionalNumber(r.points);
        if (legacy !== null) return Math.max(0, Math.round(legacy));
        const correct = parseOptionalNumber(r.correct);
        return Math.max(0, Math.round(correct !== null ? correct : 0));
      })();
      const finalPointsCell = formatPointsCell(resolvedFinalPoints, r.max_points ?? 0);
      const cells = [
        r.attempt,
        r.catalogName || r.catalog,
        `${r.correct}/${r.total}`,
        basePointsCell,
        finalPointsCell,
        formatTimestamp(r.time),
        formatTimestamp(r.puzzleTime),
        null
      ];
      const nameCell = document.createElement('td');
      nameCell.textContent = r.name;
      tr.appendChild(nameCell);
      cells.forEach((c, idx) => {
        const td = document.createElement('td');
        if (idx === cells.length - 1) {
          if (r.photo) {
            const wrap = document.createElement('span');
            wrap.className = 'photo-wrapper';

            const a = document.createElement('a');
            const safePhoto = escapeHtml(r.photo);
            a.className = 'uk-inline rotate-link';
            a.href = withBase(r.photo);
            a.dataset.caption = `<button class='uk-icon-button lightbox-rotate-btn' type='button' uk-icon='history' data-path='${safePhoto}' aria-label='Drehen'></button>`;
            a.dataset.attrs = 'class: uk-inverse-light';

            const img = document.createElement('img');
            img.alt = 'Beweisfoto';
            img.className = 'proof-thumb';
            applyLazyImage(img, withBase(r.photo));

            const btn = document.createElement('button');
            btn.className = 'uk-icon-button photo-rotate-btn';
            btn.type = 'button';
            btn.setAttribute('uk-icon', 'history');
            btn.addEventListener('click', (e) => {
              e.preventDefault();
              rotatePhoto(r.photo, img, a);
            });
            btn.dataset.rotateListenerAttached = '1';

            a.appendChild(img);
            wrap.appendChild(a);
            td.appendChild(wrap);
          }
        } else {
          td.textContent = c;
        }
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
  }

  function updateFilterSuggestions() {
    if (!filterSuggestions) return;
    const names = Array.from(new Set(
      resultsData
        .map(r => {
          if (!r) return '';
          if (typeof r.name === 'string') return r.name.trim();
          if (r.name !== undefined && r.name !== null) return String(r.name).trim();
          return '';
        })
        .filter(Boolean)
    )).sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
    filterSuggestions.innerHTML = '';
    names.forEach(name => {
      const option = document.createElement('option');
      option.value = name;
      filterSuggestions.appendChild(option);
    });
  }

  function renderWrongTable(rows) {
    if (!wrongBody) return;
    wrongBody.innerHTML = '';
    if (!rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 3;
      td.textContent = 'Keine Daten';
      tr.appendChild(td);
      wrongBody.appendChild(tr);
      return;
    }
    rows.forEach(r => {
      const tr = document.createElement('tr');
      const tdName = document.createElement('td');
      tdName.textContent = r.name;
      const tdCat = document.createElement('td');
      tdCat.textContent = r.catalogName || r.catalog;
      const tdQ = document.createElement('td');
      tdQ.textContent = insertSoftHyphens(r.prompt || '');
      tr.appendChild(tdName);
      tr.appendChild(tdCat);
      tr.appendChild(tdQ);
      wrongBody.appendChild(tr);
    });
  }

  function renderPage(page) {
    const total = Math.ceil(filteredResults.length / PAGE_SIZE);
    const safeTotal = total > 0 ? total : 0;
    const nextPage = sanitizePageNumber(page, safeTotal);
    const start = (nextPage - 1) * PAGE_SIZE;
    const slice = filteredResults.slice(start, start + PAGE_SIZE);
    renderTable(slice);
    currentPage = nextPage;
    initRotateButtons();
    refreshLightboxes();
  }

  function updatePagination() {
    if (!pagination) return;
    const total = Math.ceil(filteredResults.length / PAGE_SIZE);
    pagination.innerHTML = '';
    pagination.classList.toggle('uk-hidden', total <= 1);
    if (total <= 1) {
      currentPage = sanitizePageNumber(1, total);
      return;
    }
    currentPage = sanitizePageNumber(currentPage, total);
    const prevLi = document.createElement('li');
    if (currentPage === 1) prevLi.classList.add('uk-disabled');
    const prevA = document.createElement('a');
    prevA.href = '#';
    prevA.dataset.page = String(currentPage > 1 ? currentPage - 1 : 1);
    prevA.textContent = '«';
    prevLi.appendChild(prevA);
    pagination.appendChild(prevLi);

    for (let i = 1; i <= total; i++) {
      const li = document.createElement('li');
      if (currentPage === i) li.classList.add('uk-active');
      const a = document.createElement('a');
      a.href = '#';
      a.dataset.page = String(i);
      a.textContent = String(i);
      li.appendChild(a);
      pagination.appendChild(li);
    }

    const nextLi = document.createElement('li');
    if (currentPage === total) nextLi.classList.add('uk-disabled');
    const nextA = document.createElement('a');
    nextA.href = '#';
    nextA.dataset.page = String(currentPage < total ? currentPage + 1 : total);
    nextA.textContent = '»';
    nextLi.appendChild(nextA);
    pagination.appendChild(nextLi);
  }

  function applyFilter() {
    const query = filterInput && filterInput.value
      ? filterInput.value.trim().toLowerCase()
      : '';
    if (!query) {
      filteredResults = resultsData.slice();
    } else {
      filteredResults = resultsData.filter(row => {
        if (!row || row.name === undefined || row.name === null) return false;
        const value = typeof row.name === 'string' ? row.name : String(row.name);
        return value.toLowerCase().includes(query);
      });
    }
    currentPage = 1;
    updatePagination();
    renderPage(currentPage);
  }

  function renderRankings(rankings, options = {}) {
    if (!grid) return;
    grid.innerHTML = '';
    const hasPuzzleEntries = Array.isArray(rankings.puzzleList) && rankings.puzzleList.length > 0;
    const puzzleOption = options.puzzleWordEnabled;
    const puzzleEnabled = (puzzleOption === null || puzzleOption === undefined)
      ? hasPuzzleEntries
      : Boolean(puzzleOption);
    const cards = [];
    if (puzzleEnabled) {
      cards.push({
        title: 'Rätselmeister',
        list: rankings.puzzleList || [],
        tooltip: 'Top 3 Teams/Spieler, die das Rätselwort am schnellsten gelöst haben'
      });
    }
    const hasCatalogRankings = Array.isArray(rankings.catalogList) && rankings.catalogList.length > 0;
    if (hasCatalogRankings) {
      cards.push({
        title: 'Ranking-Champions',
        list: rankings.catalogList || [],
        tooltip: 'Top 3 Teams nach gelösten Fragen (Tie-Breaker: Punkte, Gesamtzeit)'
      });
    }
    cards.push({
      title: 'Highscore-Champions',
      list: rankings.pointsList || [],
      tooltip: 'Top 3 Teams/Spieler mit den meisten Punkten'
    });
    const MAX_ITEMS = 3;
    cards.forEach(card => {
      const col = document.createElement('div');
      const c = document.createElement('div');
      c.className = 'uk-card qr-card uk-card-body';
      const h = document.createElement('h4');
      h.className = 'uk-card-title';
      h.append(document.createTextNode(card.title));
      if (card.tooltip) {
        const icon = document.createElement('span');
        icon.className = 'uk-margin-small-left';
        icon.setAttribute('uk-icon', 'icon: question');
        icon.setAttribute('uk-tooltip', `title: ${card.tooltip}; pos: right`);
        h.appendChild(icon);
        if (typeof UIkit !== 'undefined') {
          UIkit.icon(icon);
        }
      }
      c.appendChild(h);
      const ol = document.createElement('ol');
      // show numbering manually, so suppress default list style
      ol.className = 'uk-list';
      for (let i = 0; i < MAX_ITEMS; i++) {
        const li = document.createElement('li');
        const item = card.list[i];
        if (item) {
          const gridItem = document.createElement('div');
          gridItem.className = 'uk-grid-small';
          gridItem.setAttribute('uk-grid', '');

          const teamDiv = document.createElement('div');
          teamDiv.className = 'uk-width-expand ranking-team';
          teamDiv.setAttribute('uk-leader', '');
          teamDiv.style.setProperty('--uk-leader-fill-content', ' ');
          const extras = [];
          if (Number.isFinite(item.solved)) {
            extras.push(`${item.solved} gelöst`);
          }
          if (Number.isFinite(item.points)) {
            extras.push(`${item.points} Punkte`);
          }
          const extraText = extras.length ? ` – ${extras.join(' • ')}` : '';
          teamDiv.textContent = `${i + 1}. ${item.name}${extraText}`;

          const timeDiv = document.createElement('div');
          const durationValue = item && Number.isFinite(item.duration) ? formatDuration(item.duration) : null;
          const finishedValue = item && Number.isFinite(item.finished) ? formatTimestamp(item.finished) : null;
          const valueText = durationValue || finishedValue || item.value || '–';
          timeDiv.textContent = valueText;

          gridItem.appendChild(teamDiv);
          gridItem.appendChild(timeDiv);
          li.appendChild(gridItem);
        } else {
          li.textContent = '-';
        }
        ol.appendChild(li);
      }
      c.appendChild(ol);
      col.appendChild(c);
      grid.appendChild(col);
    });
  }

  function renderNoEvent() {
    if (grid) {
      grid.innerHTML = '<p>Kein Event ausgewählt</p>';
    }
    if (tbody) {
      tbody.innerHTML = '';
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = TABLE_COLUMN_COUNT;
      td.textContent = 'Kein Event ausgewählt';
      tr.appendChild(td);
      tbody.appendChild(tr);
    }
    if (wrongBody) {
      wrongBody.innerHTML = '';
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 3;
      td.textContent = 'Kein Event ausgewählt';
      tr.appendChild(td);
      wrongBody.appendChild(tr);
    }
    if (filterInput) {
      filterInput.value = '';
    }
    if (filterSuggestions) {
      filterSuggestions.innerHTML = '';
    }
    resultsData = [];
    filteredResults = [];
    currentPage = 1;
    updatePagination();
  }

  const getCurrentEventUid = () => {
    return window.getActiveEventId ? window.getActiveEventId() : fallbackEventUid;
  };

  function load() {
    const currentEventUid = getCurrentEventUid();
    if (!currentEventUid) {
      renderNoEvent();
      return;
    }
    const previousQuery = filterInput ? filterInput.value : '';
    const requestId = ++activeRequestId;
    dataService.setEventUid(currentEventUid);
    dataService.load()
      .then(({ rows, questionRows, catalogCount }) => {
        if (requestId !== activeRequestId || currentEventUid !== getCurrentEventUid()) {
          return;
        }
        resultsData = rows;
        updateFilterSuggestions();
        if (filterInput) {
          filterInput.value = previousQuery;
        }
        applyFilter();

        const rankings = computeRankings(rows, questionRows, catalogCount);
        renderRankings(rankings, {
          puzzleWordEnabled: getPuzzleWordEnabled(),
          catalogCount,
        });

        const wrongOnly = questionRows.filter((row) => !parseBooleanFlag(row.isCorrect ?? row.correct));
        renderWrongTable(wrongOnly);
      })
      .catch(err => {
        if (requestId === activeRequestId) {
          console.error(err);
        }
      });
  }

  filterInput?.addEventListener('input', () => {
    applyFilter();
  });

  refreshBtn?.addEventListener('click', e => {
    e.preventDefault();
    load();
  });

  pagination?.addEventListener('click', e => {
    const target = e.target;
    if (!(target instanceof Element)) return;
    const link = target.closest('a[data-page]');
    if (!link) return;
    e.preventDefault();
    const totalPages = Math.ceil(filteredResults.length / PAGE_SIZE);
    if (totalPages <= 0) return;
    const requested = sanitizePageNumber(link.dataset.page, totalPages);
    if (requested === currentPage) return;
    renderPage(requested);
    updatePagination();
  });

  function initRotateButtons() {
    document.querySelectorAll('.photo-rotate-btn').forEach(btn => {
      if (btn.dataset.rotateListenerAttached === '1') {
        return;
      }
      const wrap = btn.closest('.photo-wrapper');
      const img = wrap ? wrap.querySelector('img') : null;
      const link = wrap ? wrap.querySelector('a') : null;
      const path = btn.dataset.path || (link ? link.getAttribute('href') : '');
      if (!img || !path) return;
      btn.addEventListener('click', e => {
        e.preventDefault();
        rotatePhoto(path, img, link);
      });
      btn.dataset.rotateListenerAttached = '1';
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
            const safePath = escapeHtml(newPath);
            links.forEach(a => {
              a.href = newPath;
              if (a.dataset && a.dataset.caption) {
                a.dataset.caption = a.dataset.caption
                  .replace(/data-path='[^']*'/, `data-path='${safePath}'`);
              }
            });
            btn.dataset.path = newPath;
          }
        });
      }
    };
    document.body.addEventListener('click', window.lightboxRotateHandler, { capture: true });
  }

  document.addEventListener('event:changed', e => {
    const detail = e.detail || {};
    if (detail.pending) {
      fallbackEventUid = '';
      dataService.setEventUid('');
      renderNoEvent();
      return;
    }
    fallbackEventUid = typeof detail.uid === 'string' ? detail.uid : fallbackEventUid;
    dataService.setEventUid(fallbackEventUid);
    resultsData = [];
    filteredResults = [];
    currentPage = 1;
    load();
  });

  load();
});
