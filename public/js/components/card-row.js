/**
 * card-row – shared helpers for sortable card rows.
 *
 * These utilities create the standard DOM elements used across all
 * admin card-list editors (questions, page blocks, footer blocks,
 * menu cards, menu tree).
 */

/**
 * Creates a drag handle div (.card-row__drag) with a UIKit grid icon.
 * @returns {HTMLDivElement}
 */
export function createDragHandle() {
  const el = document.createElement('div');
  el.className = 'card-row__drag';
  el.dataset.dragHandle = 'true';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('uk-icon', 'table');
  return el;
}

/**
 * Creates a coloured type badge (.card-row__badge + badge-{color}).
 * @param {string} abbr      Short label shown inside the badge (e.g. "MC")
 * @param {string} colorClass  One of: badge-blue, badge-green, badge-red,
 *                             badge-orange, badge-purple, badge-gray, badge-muted
 * @param {string} [title]   Optional tooltip text
 * @returns {HTMLDivElement}
 */
export function createTypeBadge(abbr, colorClass, title) {
  const el = document.createElement('div');
  el.className = `card-row__badge ${colorClass || 'badge-muted'}`;
  el.textContent = abbr || '?';
  if (title) el.title = title;
  return el;
}

/**
 * Creates a standard info block (.card-row__info) with title and optional meta.
 * @param {string} title
 * @param {string} [meta]
 * @returns {HTMLDivElement}
 */
export function createInfoBlock(title, meta) {
  const el = document.createElement('div');
  el.className = 'card-row__info';
  const titleEl = document.createElement('div');
  titleEl.className = 'card-row__title';
  titleEl.textContent = title || '';
  el.appendChild(titleEl);
  if (meta !== undefined && meta !== null) {
    const metaEl = document.createElement('div');
    metaEl.className = 'card-row__meta';
    metaEl.textContent = meta;
    el.appendChild(metaEl);
  }
  return el;
}

/**
 * Creates a single icon action button for use inside .card-row__actions.
 * @param {string}   icon      UIKit icon name (e.g. 'pencil', 'trash', 'copy')
 * @param {string}   label     Accessible aria-label text
 * @param {Function} onClick   Click handler
 * @param {string}   [extraClass]  Additional CSS class (e.g. 'btn-delete')
 * @returns {HTMLButtonElement}
 */
export function createActionButton(icon, label, onClick, extraClass) {
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.setAttribute('uk-icon', icon);
  btn.setAttribute('aria-label', label);
  if (extraClass) btn.className = extraClass;
  if (typeof onClick === 'function') btn.addEventListener('click', onClick);
  return btn;
}

/**
 * Creates the summary row (.card-row__summary) and appends the given children.
 * @param {...(HTMLElement|null)} children
 * @returns {HTMLDivElement}
 */
export function createCardSummary(...children) {
  const el = document.createElement('div');
  el.className = 'card-row__summary';
  children.forEach(child => { if (child) el.appendChild(child); });
  return el;
}

/**
 * Wires UIKit sortable on a list element and keeps an in-memory array in sync.
 *
 * @param {HTMLElement}  listEl      The <ul> or container that gets uk-sortable.
 * @param {Function}     getItems    () => currentArray  – reads current ordered data
 * @param {Function}     setItems    (newArray) => void  – writes the reordered data
 * @param {string}       [idAttr]    data-* attribute on each list item holding the
 *                                   item's id (default: 'blockId')
 */
export function wireCardSortable(listEl, getItems, setItems, idAttr = 'blockId') {
  const dataAttr = idAttr.replace(/([A-Z])/g, '-$1').toLowerCase();
  listEl.setAttribute('uk-sortable', 'handle: [data-drag-handle]');
  listEl.addEventListener('moved', () => {
    const items = getItems();
    const ids = [...listEl.querySelectorAll(`[data-${dataAttr}]`)].map(
      el => el.dataset[idAttr]
    );
    const itemMap = new Map(items.map(item => [String(item.id), item]));
    const reordered = ids.map(id => itemMap.get(String(id))).filter(Boolean);
    if (reordered.length === items.length) {
      setItems(reordered);
    }
  });
}
