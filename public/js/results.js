document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('resultsTableBody');
  const refreshBtn = document.getElementById('resultsRefreshBtn');
  const grid = document.getElementById('rankingGrid');
  const pagination = document.getElementById('resultsPagination');

  const PAGE_SIZE = 10;
  let groupData = [];
  let currentPage = 1;

  function formatTime(ts) {
    const d = new Date(ts * 1000);
    const pad = n => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function formatDuration(sec) {
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    const pad = n => n.toString().padStart(2, '0');
    return (h ? h + ':' + pad(m) : m) + ':' + pad(s);
  }

  function renderTable(groups) {
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!groups.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 7;
      td.textContent = 'Keine Daten';
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }
    groups.forEach(g => {
      const head = document.createElement('tr');
      const th = document.createElement('th');
      th.colSpan = 7;
      th.textContent = g.name;
      head.appendChild(th);
      tbody.appendChild(head);

      g.entries.forEach(r => {
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
    });
  }

  function renderPage(page) {
    const total = Math.ceil(groupData.length / PAGE_SIZE) || 1;
    if (page < 1) page = 1;
    if (page > total) page = total;
    const start = (page - 1) * PAGE_SIZE;
    const slice = groupData.slice(start, start + PAGE_SIZE);
    renderTable(slice);
    currentPage = page;
  }

  function updatePagination() {
    if (!pagination) return;
    const total = Math.ceil(groupData.length / PAGE_SIZE);
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

    const totalCats = catalogs.size;
    const catFin = [];
    catTimes.forEach((map, name) => {
      if (map.size === totalCats) {
        const arr = Array.from(map.values());
        const finished = Math.max(...arr);
        catFin.push({ name, value: formatTime(finished), raw: finished });
      }
    });
    catFin.sort((a, b) => a.raw - b.raw);
    const catalogList = catFin.slice(0, 3);

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
    return fetch('/kataloge/catalogs.json')
      .then(r => r.json())
      .then(list => {
        const map = {};
        if (Array.isArray(list)) {
          list.forEach(c => {
            const name = c.name || c.id || '';
            if (c.uid) map[c.uid] = name;
            if (c.id) map[c.id] = name;
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

  function load() {
    Promise.all([fetchCatalogMap(), fetch('/results.json').then(r => r.json())])
      .then(([catMap, rows]) => {
        rows.forEach(r => {
          if (catMap[r.catalog]) r.catalog = catMap[r.catalog];
        });
        const map = new Map();
        rows.forEach(row => {
          if (!map.has(row.name)) {
            map.set(row.name, []);
          }
          map.get(row.name).push(row);
        });
        const groups = Array.from(map.entries()).map(([name, list]) => {
          list.sort((a, b) => b.time - a.time);
          return { name, time: list[0]?.time || 0, entries: list };
        });
        groups.sort((a, b) => b.time - a.time);
        groupData = groups;
        currentPage = 1;
        renderPage(currentPage);
        updatePagination();

        const rankings = computeRankings(rows);
        renderRankings(rankings);
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
