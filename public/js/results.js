document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('resultsTableBody');
  const refreshBtn = document.getElementById('resultsRefreshBtn');
  const grid = document.getElementById('rankingGrid');

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

  function render(groups) {
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
          r.catalog,
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
              const img = document.createElement('img');
              img.src = r.photo;
              img.alt = 'Beweisfoto';
              img.className = 'proof-thumb';
              td.appendChild(img);
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

  function computeRankings(rows) {
    const puzzle = new Map();
    const startTimes = new Map();
    const catalogs = new Set();
    const times = new Map();
    const scores = new Map();

    rows.forEach(r => {
      catalogs.add(r.catalog);

      if (r.puzzleTime) {
        const prev = puzzle.get(r.name);
        if (!prev || r.puzzleTime < prev) puzzle.set(r.name, r.puzzleTime);
      }

      const st = startTimes.get(r.name);
      if (st === undefined || r.time < st) {
        startTimes.set(r.name, r.time);
      }

      let tMap = times.get(r.name);
      if (!tMap) { tMap = new Map(); times.set(r.name, tMap); }
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
    puzzle.forEach((time, name) => {
      const start = startTimes.get(name);
      if (start !== undefined && time >= start) {
        const duration = time - start;
        puzzleArr.push({ name, value: formatDuration(duration), raw: duration });
      }
    });
    puzzleArr.sort((a, b) => a.raw - b.raw);
    const puzzleList = puzzleArr.slice(0, 3);

    const totalCats = catalogs.size;
    const catDur = [];
    times.forEach((map, name) => {
      if (map.size === totalCats) {
        const arr = Array.from(map.values());
        const duration = Math.max(...arr) - Math.min(...arr);
        catDur.push({ name, value: formatDuration(duration), raw: duration });
      }
    });
    catDur.sort((a, b) => a.raw - b.raw);
    const catalogList = catDur.slice(0, 3);

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
      { title: 'Schnellstes Rätselwort', list: rankings.puzzleList },
      { title: 'Alle Kataloge am schnellsten', list: rankings.catalogList },
      { title: 'Meiste Punkte', list: rankings.pointsList }
    ];
    cards.forEach(card => {
      const col = document.createElement('div');
      const c = document.createElement('div');
      c.className = 'uk-card uk-card-default uk-card-body';
      const h = document.createElement('h4');
      h.className = 'uk-card-title';
      h.textContent = card.title;
      c.appendChild(h);
      const ol = document.createElement('ol');
      ol.className = 'uk-list uk-list-decimal';
      if (card.list.length === 0) {
        const li = document.createElement('li');
        li.textContent = 'Keine Daten';
        ol.appendChild(li);
      } else {
        card.list.forEach(item => {
          const li = document.createElement('li');
          li.textContent = `${item.name} – ${item.value}`;
          ol.appendChild(li);
        });
      }
      c.appendChild(ol);
      col.appendChild(c);
      grid.appendChild(col);
    });
  }

  function load() {
    fetch('/results.json')
      .then(r => r.json())
      .then(rows => {
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
        render(groups);

        const rankings = computeRankings(rows);
        renderRankings(rankings);
      })
      .catch(err => console.error(err));
  }

  refreshBtn?.addEventListener('click', e => {
    e.preventDefault();
    load();
  });

  if (refreshBtn && typeof UIkit !== 'undefined') {
    UIkit.icon(refreshBtn);
  }

  load();
});
