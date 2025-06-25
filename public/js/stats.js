/* global UIkit */
document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('statsTableBody');
  const filter = document.getElementById('statsFilter');
  const refreshBtn = document.getElementById('statsRefreshBtn');

  let data = [];
  let catalogMap = null;

  function fetchCatalogMap() {
    if (catalogMap) return Promise.resolve(catalogMap);
    return fetch('/kataloge/catalogs.json', { headers: { 'Accept': 'application/json' } })
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
        photoTd.appendChild(a);
      }
      tr.appendChild(photoTd);
      tbody.appendChild(tr);
    });
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
    Promise.all([
      fetchCatalogMap(),
      fetch('/question-results.json').then(r => r.json())
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

  if (refreshBtn && typeof UIkit !== 'undefined') {
    UIkit.icon(refreshBtn);
  }

  load();
});
