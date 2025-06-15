document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('resultsTableBody');
  const refreshBtn = document.getElementById('resultsRefreshBtn');

  function formatTime(ts) {
    const d = new Date(ts * 1000);
    const pad = n => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function render(rows) {
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 5;
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
        r.catalog,
        `${r.correct}/${r.total}`,
        formatTime(r.time)
      ];
      cells.forEach(c => {
        const td = document.createElement('td');
        td.textContent = c;
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
  }

  function load() {
    fetch('/results.json')
      .then(r => r.json())
      .then(render)
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
