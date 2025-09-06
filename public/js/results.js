/* global UIkit */
let catalogCount = 0;
document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('resultsTableBody');
  const wrongBody = document.getElementById('wrongTableBody');
  const refreshBtn = document.getElementById('resultsRefreshBtn');
  const grid = document.getElementById('rankingGrid');
  const pagination = document.getElementById('resultsPagination');
  const basePath = window.basePath || '';
  const withBase = path => basePath + path;

  const PAGE_SIZE = 10;
  let resultsData = [];
  let currentPage = 1;

  function formatTime(ts) {
    const d = new Date(ts * 1000);
    const pad = n => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function insertSoftHyphens(text) {
    return text ? text.replace(/\/-/g, '\u00AD') : '';
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

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
        img.src = withBase(newPath);
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
      td.colSpan = 7;
      td.textContent = 'Keine Daten';
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }
    rows.forEach(r => {
      const tr = document.createElement('tr');
      const cells = [
        r.attempt,
        r.catalogName || r.catalog,
        `${r.correct}/${r.total}`,
        formatTime(r.time),
        r.puzzleTime ? formatTime(r.puzzleTime) : '',
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
            img.src = withBase(r.photo);
            img.alt = 'Beweisfoto';
            img.className = 'proof-thumb';

            const btn = document.createElement('button');
            btn.className = 'uk-icon-button photo-rotate-btn';
            btn.type = 'button';
            btn.setAttribute('uk-icon', 'history');
            btn.addEventListener('click', (e) => {
              e.preventDefault();
              rotatePhoto(r.photo, img, a);
            });

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
    const total = Math.ceil(resultsData.length / PAGE_SIZE) || 1;
    page = sanitizePageNumber(page, total);
    const start = (page - 1) * PAGE_SIZE;
    const slice = resultsData.slice(start, start + PAGE_SIZE);
    renderTable(slice);
    currentPage = page;
  }

  function updatePagination() {
    if (!pagination) return;
    const total = Math.ceil(resultsData.length / PAGE_SIZE);
    if (total <= 1) { pagination.textContent = ''; return; }
    pagination.textContent = '';
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

  function computeRankings(rows) {
    const catalogs = new Set();
    const puzzleTimes = new Map();
    const catTimes = new Map();
    const scores = new Map();

    rows.forEach(r => {
      catalogs.add(r.catalog);

      if (r.puzzleTime) {
        const prev = puzzleTimes.get(r.name);
        if (!prev || r.puzzleTime < prev) puzzleTimes.set(r.name, r.puzzleTime);
      }

      let tMap = catTimes.get(r.name);
      if (!tMap) { tMap = new Map(); catTimes.set(r.name, tMap); }
      const prevTime = tMap.get(r.catalog);
      if (prevTime === undefined || r.time < prevTime) {
        tMap.set(r.catalog, r.time);
      }

      let sMap = scores.get(r.name);
      if (!sMap) { sMap = new Map(); scores.set(r.name, sMap); }
      const prevScore = sMap.get(r.catalog);
      if (prevScore === undefined || r.correct > prevScore) {
        sMap.set(r.catalog, r.correct);
      }
    });

    const puzzleArr = [];
    puzzleTimes.forEach((time, name) => {
      puzzleArr.push({ name, value: formatTime(time), raw: time });
    });
    puzzleArr.sort((a, b) => a.raw - b.raw);
    const puzzleList = puzzleArr.slice(0, 3);

    const totalCats = catalogCount || catalogs.size;
    const finishers = [];
    catTimes.forEach((map, name) => {
      if (map.size === totalCats) {
        let last = -Infinity;
        map.forEach(t => { if (t > last) last = t; });
        finishers.push({ name, finished: last });
      }
    });
    finishers.sort((a, b) => a.finished - b.finished);
    const catalogList = finishers.slice(0, 3).map(item => ({
      name: item.name,
      value: formatTime(item.finished),
      raw: item.finished
    }));

    const totalScores = [];
    scores.forEach((map, name) => {
      const total = Array.from(map.values()).reduce((sum, v) => sum + v, 0);
      totalScores.push({ name, value: total, raw: total });
    });
    totalScores.sort((a, b) => b.raw - a.raw);
    const pointsList = totalScores.slice(0, 3);

    return { puzzleList, catalogList, pointsList };
  }


  function renderRankings(rankings) {
    if (!grid) return;
    grid.innerHTML = '';
    const cards = [
      {
        title: 'Rätselwort-Bestzeit',
        list: rankings.puzzleList,
        tooltip: 'Top 3 Platzierungen für das schnellste Lösen des Rätselworts'
      },
      {
        title: 'Katalogmeister',
        list: rankings.catalogList,
        tooltip: 'Top 3 Teams/Spieler, die alle Fragenkataloge am schnellsten bearbeitet haben'
      },
      {
        title: 'Highscore-Champions',
        list: rankings.pointsList,
        tooltip: 'Top 3 Teams/Spieler mit den meisten Punkten'
      },
    ];
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
            teamDiv.className = 'uk-width-expand';
            teamDiv.setAttribute('uk-leader', '');
            teamDiv.style.setProperty('--uk-leader-fill-content', ' ');
            teamDiv.textContent = `${i + 1}. ${item.name}`;

            const timeDiv = document.createElement('div');
            timeDiv.textContent = item.value;

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

  let catalogMap = null;

  function fetchCatalogMap() {
    if (catalogMap) return Promise.resolve(catalogMap);
    return fetch(withBase('/kataloge/catalogs.json'), {
      headers: { 'Accept': 'application/json' }
    })
      .then(r => r.json())
      .then(list => {
        const map = {};
        if (Array.isArray(list)) {
          catalogCount = list.length;
          list.forEach(c => {
            const name = c.name || '';
            if (c.uid) map[c.uid] = name;
            if (c.sort_order) map[c.sort_order] = name;
            if (c.slug) map[c.slug] = name;
          });
        } else {
          catalogCount = 0;
        }
        catalogMap = map;
        return map;
      })
      .catch(() => {
        catalogMap = {};
        return catalogMap;
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
      td.colSpan = 7;
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
    if (pagination) {
      pagination.textContent = '';
    }
  }

  function load() {
    const currentEventUid = (window.quizConfig || {}).event_uid || '';
    if (!currentEventUid) {
      renderNoEvent();
      return;
    }
    Promise.all([
      fetchCatalogMap(),
      fetch(withBase('/results.json')).then(r => r.json()),
      fetch(withBase('/question-results.json')).then(r => r.json())
    ])
      .then(([catMap, rows, qrows]) => {
        rows.forEach(r => {
          if (!r.catalogName && catMap[r.catalog]) r.catalogName = catMap[r.catalog];
          if (catMap[r.catalog]) r.catalog = catMap[r.catalog];
        });
        rows.sort((a, b) => b.time - a.time);
        resultsData = rows;
        currentPage = 1;
        renderPage(currentPage);
        initRotateButtons();
        refreshLightboxes();
        updatePagination();

        const rankings = computeRankings(rows);
        renderRankings(rankings);

        qrows.forEach(r => {
          if (!r.catalogName && catMap[r.catalog]) r.catalogName = catMap[r.catalog];
          if (catMap[r.catalog]) r.catalog = catMap[r.catalog];
        });
        const wrongOnly = qrows.filter(r => !r.correct);
        renderWrongTable(wrongOnly);
      })
      .catch(err => console.error(err));
  }

  refreshBtn?.addEventListener('click', e => {
    e.preventDefault();
    load();
  });

  pagination?.addEventListener('click', e => {
    const target = e.target;
    if (target instanceof HTMLElement && target.dataset.page) {
      e.preventDefault();
      const page = parseInt(target.dataset.page, 10);
      if (!Number.isNaN(page)) {
        renderPage(page);
        initRotateButtons();
        refreshLightboxes();
        updatePagination();
      }
    }
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

  load();
});
