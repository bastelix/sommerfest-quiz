/* global UIkit */
let catalogCount = 0;
document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('resultsTableBody');
  const wrongBody = document.getElementById('wrongTableBody');
  const refreshBtn = document.getElementById('resultsRefreshBtn');
  const grid = document.getElementById('rankingGrid');
  const pagination = document.getElementById('resultsPagination');

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
            const a = document.createElement('a');
            a.className = 'uk-inline';
            a.href = r.photo;
            a.dataset.caption = 'Beweisfoto';
            a.dataset.attrs = 'class: uk-inverse-light';

            const img = document.createElement('img');
            img.src = r.photo;
            img.alt = 'Beweisfoto';
            img.className = 'proof-thumb';

            a.appendChild(img);
            td.appendChild(a);
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
    if (page < 1) page = 1;
    if (page > total) page = total;
    const start = (page - 1) * PAGE_SIZE;
    const slice = resultsData.slice(start, start + PAGE_SIZE);
    renderTable(slice);
    currentPage = page;
  }

  function updatePagination() {
    if (!pagination) return;
    const total = Math.ceil(resultsData.length / PAGE_SIZE);
    if (total <= 1) { pagination.innerHTML = ''; return; }
    let html = '';
    const prevClass = currentPage === 1 ? 'uk-disabled' : '';
    html += `<li class="${prevClass}"><a href="#" data-page="${currentPage - 1}">&laquo;</a></li>`;
    for (let i = 1; i <= total; i++) {
      const cls = currentPage === i ? 'uk-active' : '';
      html += `<li class="${cls}"><a href="#" data-page="${i}">${i}</a></li>`;
    }
    const nextClass = currentPage === total ? 'uk-disabled' : '';
    html += `<li class="${nextClass}"><a href="#" data-page="${currentPage + 1}">&raquo;</a></li>`;
    pagination.innerHTML = html;
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
      const prev = sMap.get(r.catalog);
      const ratio = r.total ? r.correct / r.total : 0;
      if (!prev || ratio > (prev.correct / prev.total)) {
        sMap.set(r.catalog, { correct: r.correct, total: r.total });
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
      let corr = 0;
      let tot = 0;
      map.forEach(v => { corr += v.correct; tot += v.total; });
      const ratio = tot ? corr / tot : 0;
      totalScores.push({ name, value: Math.round(ratio * 100) + '%', raw: ratio });
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
        tooltip: 'Top 3 Teams/Spieler mit der besten Trefferquote'
      }
    ];
    const MAX_ITEMS = 3;
    cards.forEach(card => {
      const col = document.createElement('div');
      const c = document.createElement('div');
      c.className = 'uk-card uk-card-default uk-card-body';
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
    return fetch('/kataloge/catalogs.json', { headers: { 'Accept': 'application/json' } })
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

  function load() {
    Promise.all([
      fetchCatalogMap(),
      fetch('/results.json').then(r => r.json()),
      fetch('/question-results.json').then(r => r.json())
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
        updatePagination();
      }
    }
  });

  if (refreshBtn && typeof UIkit !== 'undefined') {
    UIkit.icon(refreshBtn);
  }

  load();
});
