document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('resultsTableBody');
  const refreshBtn = document.getElementById('resultsRefreshBtn');

  function formatTime(ts) {
    const d = new Date(ts * 1000);
    const pad = n => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function render(groups) {
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!groups.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 5;
      td.textContent = 'Keine Daten';
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }
    groups.forEach(g => {
      const head = document.createElement('tr');
      const th = document.createElement('th');
      th.colSpan = 5;
      th.textContent = g.name;
      head.appendChild(th);
      tbody.appendChild(head);

      g.entries.forEach(r => {
        const tr = document.createElement('tr');
        const cells = [
          r.attempt,
          r.catalog,
          `${r.correct}/${r.total}`,
          formatTime(r.time)
        ];
        const nameCell = document.createElement('td');
        nameCell.textContent = r.name;
        tr.appendChild(nameCell);
        cells.forEach(c => {
          const td = document.createElement('td');
          td.textContent = c;
          tr.appendChild(td);
        });
        tbody.appendChild(tr);
      });
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
