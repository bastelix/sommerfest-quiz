export default class TableManager {
  constructor({ tbody, columns = [], sortable = false, mobileCards = null, onEdit = null, onDelete = null, onReorder = null } = {}) {
    this.tbody = tbody;
    this.columns = columns;
    this.sortable = sortable;
    this.mobileCards = mobileCards;
    this.onEdit = onEdit;
    this.onDelete = onDelete;
    this.onReorder = onReorder;
    this.thead = this.tbody?.closest('table')?.querySelector('thead');
    this.data = [];
    if (this.sortable) {
      this.#initSortable();
    }
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
    const ids = Array.from(this.tbody.children).map(r => r.dataset.id);
    this.data = ids.map(id => this.data.find(d => d.id === id)).filter(Boolean);
    if (typeof this.onReorder === 'function') {
      this.onReorder();
    }
  }

  render(data = []) {
    this.data = Array.isArray(data) ? data : [];
    if (this.tbody) {
      this.tbody.innerHTML = '';
    }
    if (this.mobileCards?.container) {
      this.mobileCards.container.innerHTML = '';
    }
    this.data.forEach(item => this.addRow(item));
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

  addRow(item) {
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

    if (this.pagination) {
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
      const labelText = col.label || this.thead?.querySelector(`th[data-key="${col.key}"]`)?.textContent;
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
}

