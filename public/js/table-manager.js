export default class TableManager {
  constructor({
    tbody,
    columns = [],
    sortable = false,
    mobileCards = null,
    onEdit = null,
    onDelete = null,
    onReorder = null,
    tableClasses = null,
    tableWrapperClasses = null,
    tbodyClasses = null,
    tableAttributes = null,
    tbodyAttributes = null
  } = {}) {
    this.tbody = tbody;
    this.columns = columns;
    this.sortable = sortable;
    this.mobileCards = mobileCards;
    this.onEdit = onEdit;
    this.onDelete = onDelete;
    this.onReorder = onReorder;
    this.table = this.tbody?.closest('table') || null;
    this.tableWrapper = this.table?.parentElement || null;
    this.thead = this.table?.querySelector('thead');
    this.columnLabelCache = new Map();
    this.data = [];
    this.filteredData = [];
    this.filterFn = null;

    this.#applyTableStructure({ tableClasses, tableWrapperClasses, tbodyClasses, tableAttributes, tbodyAttributes });

    if (this.sortable) {
      this.#initSortable();
    }
  }

  #applyTableStructure({ tableClasses, tableWrapperClasses, tbodyClasses, tableAttributes, tbodyAttributes }) {
    if (Array.isArray(tableClasses) && this.table?.classList) {
      tableClasses.filter(Boolean).forEach(cls => this.table.classList.add(cls));
    }
    if (Array.isArray(tableWrapperClasses) && this.tableWrapper?.classList) {
      tableWrapperClasses.filter(Boolean).forEach(cls => this.tableWrapper.classList.add(cls));
    }
    if (Array.isArray(tbodyClasses) && this.tbody?.classList) {
      tbodyClasses.filter(Boolean).forEach(cls => this.tbody.classList.add(cls));
    }
    if (tableAttributes && this.table) {
      Object.entries(tableAttributes).forEach(([key, value]) => {
        if (value === null || value === undefined || value === false) return;
        this.table.setAttribute(key, value === true ? '' : value);
      });
    }
    if (tbodyAttributes && this.tbody) {
      Object.entries(tbodyAttributes).forEach(([key, value]) => {
        if (value === null || value === undefined || value === false) return;
        this.tbody.setAttribute(key, value === true ? '' : value);
      });
    }
  }

  #getColumnLabel(col) {
    if (col.label) {
      return col.label;
    }
    if (!col.key) {
      return '';
    }
    if (this.columnLabelCache.has(col.key)) {
      return this.columnLabelCache.get(col.key);
    }
    const label = this.thead?.querySelector(`th[data-key="${col.key}"]`)?.textContent || '';
    this.columnLabelCache.set(col.key, label);
    return label;
  }

  #initSortable() {
    if (this.tbody) {
      if (!this.tbody.getAttribute('uk-sortable')) {
        this.tbody.setAttribute('uk-sortable', 'handle: .qr-handle; group: sortable-group');
      }
      if (typeof UIkit !== 'undefined') {
        UIkit.util.on(this.tbody, 'moved', () => this.#handleReorder());
      }
    }
    if (this.mobileCards?.container) {
      if (!this.mobileCards.container.getAttribute('uk-sortable')) {
        this.mobileCards.container.setAttribute('uk-sortable', 'handle: .qr-handle; group: sortable-group');
      }
      if (typeof UIkit !== 'undefined') {
        UIkit.util.on(this.mobileCards.container, 'moved', () => this.#handleReorder());
      }
    }
  }

  #handleReorder() {
    if (this.filterFn) {
      this.render();
      if (typeof this.onReorder === 'function') {
        this.onReorder();
      }
      return;
    }
    const ids = Array.from(this.tbody.children).map(r => r.dataset.id);
    const dataMap = new Map(this.data.map(d => [String(d.id), d]));
    const ordered = ids.map(id => dataMap.get(String(id))).filter(Boolean);
    if (ordered.length) {
      if (ordered.length === this.data.length) {
        this.data = ordered;
      } else {
        const seen = new Set(ids.map(String));
        const rest = this.data.filter(item => !seen.has(String(item.id)));
        this.data = ordered.concat(rest);
      }
    }
    if (typeof this.onReorder === 'function') {
      this.onReorder();
    }
  }

  render(data = null) {
    if (Array.isArray(data)) {
      this.data = data;
    } else if (data !== null) {
      this.data = [];
    }
    const source = Array.isArray(this.data) ? this.data : [];
    let filtered = source;
    if (typeof this.filterFn === 'function') {
      try {
        filtered = source.filter((item, index) => this.filterFn(item, index, source));
      } catch (err) {
        console.error('TableManager filter failed', err);
        filtered = source;
      }
    }
    this.filteredData = filtered;
    if (this.tbody) {
      this.tbody.innerHTML = '';
    }
    if (this.mobileCards?.container) {
      this.mobileCards.container.innerHTML = '';
    }
    this.filteredData.forEach(item => this.addRow(item, { skipPaginationUpdate: true }));
    if (this.pagination) {
      this.#updatePagination();
    }
  }

  setColumnLoading(key, loading = true) {
    const th = this.thead?.querySelector(`th[data-key="${key}"]`);
    const spinner = th?.querySelector('.qr-col-spinner');
    if (spinner) {
      spinner.hidden = !loading;
    }
  }

  addRow(item, { skipPaginationUpdate = false } = {}) {
    if (!this.tbody) return;
    const row = document.createElement('tr');
    row.setAttribute('role', 'row');
    if (item?.id !== undefined) {
      row.dataset.id = item.id;
    }

    if (this.sortable) {
      const handleCell = document.createElement('td');
      handleCell.setAttribute('role', 'gridcell');
      handleCell.className = 'uk-table-shrink';
      const handleBtn = document.createElement('button');
      handleBtn.type = 'button';
      handleBtn.className = 'qr-handle';
      handleBtn.setAttribute('uk-icon', 'icon: menu');
      handleBtn.setAttribute('aria-label', 'Verschieben');
      handleCell.appendChild(handleBtn);
      row.appendChild(handleCell);
    }

    this.columns.forEach((col, idx) => {
      const cell = document.createElement('td');
      cell.setAttribute('role', 'gridcell');
      if (col.className) {
        cell.className = col.className;
      }
      let content = '';
      if (typeof col.render === 'function') {
        content = col.render(item);
      } else if (col.key) {
        content = item[col.key];
      }
      if (col.editable) {
        cell.classList.add('qr-cell');
        cell.tabIndex = 0;
        cell.dataset.id = item.id;
        if (col.key) {
          cell.dataset.key = col.key;
        }
        const span = document.createElement('span');
        span.className = 'uk-text-truncate';
        if (content instanceof Node) {
          span.appendChild(content);
        } else {
          span.innerHTML = content ?? '';
        }
        cell.appendChild(span);
        const desc = document.createElement('span');
        const descId = `${this.tbody.id}-edit-${item.id}-${idx}`;
        desc.id = descId;
        desc.className = 'uk-hidden-visually';
        desc.textContent = col.ariaDesc || '';
        cell.setAttribute('aria-describedby', descId);
        cell.appendChild(desc);
        if (typeof this.onEdit === 'function') {
          cell.addEventListener('click', () => this.onEdit(cell));
        }
      } else {
        if (content instanceof Node) {
          cell.appendChild(content);
        } else {
          cell.innerHTML = content ?? '';
        }
      }
      if (cell.querySelector('.qr-action')) {
        cell.classList.add('uk-text-right');
      }
      row.appendChild(cell);
    });

    if (typeof this.onDelete === 'function') {
      const delCell = document.createElement('td');
      delCell.setAttribute('role', 'gridcell');
      delCell.className = 'uk-table-shrink uk-text-right';
      const delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.className = 'uk-icon-button qr-action';
      delBtn.setAttribute('uk-icon', 'trash');
      delBtn.setAttribute('aria-label', 'Löschen');
      delBtn.addEventListener('click', () => this.onDelete(item.id));
      delCell.appendChild(delBtn);
      row.appendChild(delCell);
    }

    this.tbody.appendChild(row);

    if (this.mobileCards?.container) {
      let card;
      if (typeof this.mobileCards.render === 'function') {
        card = this.mobileCards.render(item);
      } else {
        card = this.#renderDefaultCard(item);
      }
      if (card) {
        this.mobileCards.container.appendChild(card);
      }
    }

    if (this.pagination && !skipPaginationUpdate) {
      this.#updatePagination();
    }
  }

  #renderDefaultCard(item) {
    const li = document.createElement('li');
    li.className = 'qr-rowcard uk-flex uk-flex-middle uk-flex-between';
    li.setAttribute('role', 'row');
    if (item?.id !== undefined) {
      li.dataset.id = item.id;
    }
    if (this.sortable) {
      const handleBtn = document.createElement('button');
      handleBtn.type = 'button';
      handleBtn.className = 'qr-handle';
      handleBtn.setAttribute('uk-icon', 'icon: menu');
      handleBtn.setAttribute('aria-label', 'Verschieben');
      li.appendChild(handleBtn);
    }
    const contentWrap = document.createElement('div');
    contentWrap.className = 'uk-flex-1 qr-card-content';
    const actions = [];
    this.columns.forEach((col, idx) => {
      let c = '';
      if (typeof col.renderCard === 'function') {
        c = col.renderCard(item);
      } else if (typeof col.render === 'function') {
        c = col.render(item);
      } else if (col.key) {
        c = item[col.key];
      }
      const labelText = this.#getColumnLabel(col);
      let lbl = null;
      if (labelText) {
        lbl = document.createElement('span');
        lbl.className = 'qr-card-label';
        lbl.textContent = labelText;
      }
      if (col.editable) {
        if (lbl) contentWrap.appendChild(lbl);
        const wrapper = document.createElement('span');
        wrapper.className = 'qr-cell';
        wrapper.tabIndex = 0;
        const desc = document.createElement('span');
        const descId = `${this.tbody.id}-card-edit-${item.id}-${idx}`;
        desc.id = descId;
        desc.className = 'uk-hidden-visually';
        desc.textContent = col.ariaDesc || '';
        wrapper.setAttribute('aria-describedby', descId);
        if (c instanceof Node) {
          wrapper.appendChild(c);
        } else {
          const span = document.createElement('span');
          span.className = 'uk-text-truncate';
          span.innerHTML = c ?? '';
          wrapper.appendChild(span);
        }
        wrapper.appendChild(desc);
        wrapper.dataset.id = item.id;
        if (col.key) {
          wrapper.dataset.key = col.key;
        }
        if (typeof this.onEdit === 'function') {
          wrapper.addEventListener('click', () => this.onEdit(wrapper));
        }
        contentWrap.appendChild(wrapper);
      } else {
        if (c instanceof Node) {
          if (c.classList && c.classList.contains('qr-action')) {
            actions.push(c);
          } else {
            if (lbl) contentWrap.appendChild(lbl);
            contentWrap.appendChild(c);
          }
        } else {
          if (lbl) contentWrap.appendChild(lbl);
          const span = document.createElement('span');
          span.innerHTML = c ?? '';
          contentWrap.appendChild(span);
        }
      }
    });
    li.appendChild(contentWrap);
    if (typeof this.onDelete === 'function') {
      const delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.className = 'uk-icon-button qr-action uk-text-center';
      delBtn.setAttribute('uk-icon', 'trash');
      delBtn.setAttribute('aria-label', 'Löschen');
      delBtn.addEventListener('click', () => this.onDelete(item.id));
      actions.push(delBtn);
    }
    if (actions.length === 1) {
      li.appendChild(actions[0]);
    } else if (actions.length > 1) {
      const wrapper = document.createElement('div');
      wrapper.className = 'uk-inline qr-action-menu';
      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'uk-icon-button qr-action';
      toggle.setAttribute('uk-icon', 'more-vertical');
      toggle.setAttribute('aria-label', window.transActions || 'Aktionen');
      const dropdown = document.createElement('div');
      dropdown.className = 'uk-dropdown';
      dropdown.setAttribute('uk-dropdown', 'mode: click; pos: bottom-right');
      actions.forEach(btn => dropdown.appendChild(btn));
      wrapper.appendChild(toggle);
      wrapper.appendChild(dropdown);
      li.appendChild(wrapper);
    }
    return li;
  }

  bindPagination(el, perPage = 10) {
    this.pagination = { el, perPage, page: 1 };
    el.addEventListener('click', e => {
      const link = e.target.closest('a[data-page]');
      if (!link) return;
      e.preventDefault();
      const page = parseInt(link.dataset.page, 10);
      if (!isNaN(page)) {
        this.pagination.page = page;
        this.#updatePagination();
      }
    });
    this.#updatePagination();
  }

  #updatePagination() {
    const { el, perPage, page } = this.pagination;
    const rows = Array.from(this.tbody.children);
    const cards = this.mobileCards?.container ? Array.from(this.mobileCards.container.children) : [];
    const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
    const current = Math.min(page, totalPages);
    rows.forEach((r, idx) => {
      r.style.display = idx >= (current - 1) * perPage && idx < current * perPage ? '' : 'none';
    });
    cards.forEach((c, idx) => {
      c.style.display = idx >= (current - 1) * perPage && idx < current * perPage ? '' : 'none';
    });
    el.innerHTML = '';
    for (let i = 1; i <= totalPages; i++) {
      const li = document.createElement('li');
      if (i === current) {
        li.classList.add('uk-active');
      }
      const a = document.createElement('a');
      a.href = '#';
      a.textContent = i;
      a.setAttribute('data-page', i);
      li.appendChild(a);
      el.appendChild(li);
    }
  }

  getData() {
    return this.data;
  }

  setFilter(filterFn = null) {
    if (filterFn !== null && typeof filterFn !== 'function') {
      console.error('TableManager.setFilter expects a function or null');
      return;
    }
    this.filterFn = filterFn;
    if (this.pagination) {
      this.pagination.page = 1;
    }
    this.render();
  }

  getViewData() {
    return this.filteredData;
  }
}

