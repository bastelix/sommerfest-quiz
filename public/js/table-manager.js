function TableManager(options = {}) {
  this.tbody = options.tbody;
  this.columns = options.columns || [];
  this.onEdit = options.onEdit;
  this.onDelete = options.onDelete;
  this.sortable = options.sortable || false;
  this.mobileCards = options.mobileCards || null; // { container, render }
  this.onReorder = options.onReorder || null;
  this.list = [];
  this.currentPage = 1;
  this.perPage = 10;
  this.paginationEl = null;

  if (this.sortable && window.UIkit && UIkit.util) {
    if (this.tbody) {
      UIkit.util.on(this.tbody, 'moved', () => {
        this.updateOrder(this.tbody);
        this.render(this.list);
        if (this.onReorder) this.onReorder(this.list);
      });
    }
    if (this.mobileCards && this.mobileCards.container) {
      UIkit.util.on(this.mobileCards.container, 'moved', () => {
        this.updateOrder(this.mobileCards.container);
        this.render(this.list);
        if (this.onReorder) this.onReorder(this.list);
      });
    }
  }
}

TableManager.prototype.addRow = function(data) {
  if (!this.tbody) return null;
  const row = document.createElement('tr');
  row.className = data.className || '';
  if (data.id) row.dataset.id = data.id;
  row.setAttribute('role', 'row');

  this.columns.forEach(col => {
    let cell;
    if (col.render) {
      cell = col.render(data, this);
    } else {
      cell = document.createElement('td');
      if (col.className) cell.className = col.className;
      cell.textContent = data[col.key] != null ? data[col.key] : '';
    }
    if (cell) row.appendChild(cell);
  });

  this.tbody.appendChild(row);

  if (this.mobileCards && this.mobileCards.container && typeof this.mobileCards.render === 'function') {
    const card = this.mobileCards.render(data, this);
    if (card) {
      if (data.id) card.dataset.id = data.id;
      this.mobileCards.container.appendChild(card);
    }
  }
  return row;
};

TableManager.prototype.render = function(list) {
  this.list = Array.isArray(list) ? list.slice() : [];
  const totalPages = Math.max(1, Math.ceil(this.list.length / this.perPage));
  if (this.currentPage > totalPages) this.currentPage = totalPages;
  const start = (this.currentPage - 1) * this.perPage;
  const segment = this.list.slice(start, start + this.perPage);
  if (this.tbody) this.tbody.innerHTML = '';
  if (this.mobileCards && this.mobileCards.container) this.mobileCards.container.innerHTML = '';
  segment.forEach(item => this.addRow(item));
  this.updatePagination();
};

TableManager.prototype.updatePagination = function() {
  if (!this.paginationEl) return;
  const total = Math.max(1, Math.ceil(this.list.length / this.perPage));
  const createItem = (p, label, disabled = false, active = false) => {
    const li = document.createElement('li');
    if (disabled) li.classList.add('uk-disabled');
    if (active) li.classList.add('uk-active');
    const a = document.createElement('a');
    a.href = '#';
    a.innerHTML = label;
    if (!disabled) {
      a.addEventListener('click', e => {
        e.preventDefault();
        this.currentPage = p;
        this.render(this.list);
      });
    }
    li.appendChild(a);
    return li;
  };
  this.paginationEl.innerHTML = '';
  this.paginationEl.appendChild(createItem(this.currentPage - 1, '<span uk-pagination-previous></span>', this.currentPage === 1));
  for (let i = 1; i <= total; i++) {
    this.paginationEl.appendChild(createItem(i, String(i), false, i === this.currentPage));
  }
  this.paginationEl.appendChild(createItem(this.currentPage + 1, '<span uk-pagination-next></span>', this.currentPage === total));
};

TableManager.prototype.bindPagination = function(el, perPage = 10) {
  this.paginationEl = el;
  this.perPage = perPage;
  this.updatePagination();
};

TableManager.prototype.updateOrder = function(container) {
  const ids = Array.from(container.querySelectorAll('[data-id]')).map(el => el.dataset.id);
  const start = (this.currentPage - 1) * this.perPage;
  const segment = ids.map(id => this.list.find(item => item.id === id)).filter(Boolean);
  this.list.splice(start, segment.length, ...segment);
};

window.TableManager = TableManager;
