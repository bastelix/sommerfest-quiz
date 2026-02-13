/**
 * Card-based Menu Editor
 *
 * Replaces the dense table editor with a visual card-based approach
 * modeled after the footer block editor UX. Supports hierarchy,
 * inline editing, drag-and-drop, and live preview.
 *
 * Requires: marketing-menu-common.js (loaded before this script)
 */
(function () {
  'use strict';

  const CONTAINER_SEL = '[data-marketing-menu-cards]';

  /* ── Layout type badge config ─────────────────────────────── */
  const LAYOUT_TYPES = {
    link:     { abbr: 'L', cls: 'menu-card__type--link',     label: 'Link' },
    dropdown: { abbr: 'D', cls: 'menu-card__type--dropdown',  label: 'Dropdown' },
    mega:     { abbr: 'M', cls: 'menu-card__type--mega',      label: 'Mega' },
    column:   { abbr: 'C', cls: 'menu-card__type--column',    label: 'Spalte' },
  };

  const ICON_OPTIONS = [
    '', 'home', 'info', 'question', 'star', 'heart', 'settings',
    'users', 'cart', 'mail', 'phone', 'location', 'calendar',
    'image', 'file-text', 'search', 'world', 'tag', 'lock',
    'credit-card', 'social', 'comments', 'bolt',
  ];

  /* ── Helpers ──────────────────────────────────────────────── */
  const { apiFetch, resolveNamespace, withNamespace, setFeedback, hideFeedback } =
    window.marketingMenuCommon || {};

  const esc = (s) => {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  };
  const escAttr = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');

  const normalizeId = (v) => (v === null || v === undefined || v === '') ? null : Number(v);

  /* href validation (same as tree editor) */
  const HREF_RE = /^(\/[^\s]*|#[^\s]*|\?[^\s]*|https?:\/\/|mailto:|tel:)/;
  const validateHref = (href) => {
    if (!href) return true;
    return HREF_RE.test(href);
  };

  /* ── State ────────────────────────────────────────────────── */
  const state = {
    menuId: null,
    items: [],           // flat list from API
    byId: new Map(),     // id → item
    dirty: new Set(),    // modified item ids
    originalJson: '',    // snapshot for cancel
    menus: [],
    basePath: '',
    namespace: '',
    locale: '',
    editingId: null,     // currently expanded inline editor
  };

  let container = null;
  let cardsList = null;
  let feedbackEl = null;
  let previewTree = null;
  let previewEmpty = null;
  let saveBtn = null;
  let cancelBtn = null;

  /* ── Initialization ───────────────────────────────────────── */
  function init() {
    container = document.querySelector(CONTAINER_SEL);
    if (!container) return;

    state.basePath = container.dataset.basePath || '';
    state.namespace = container.dataset.namespace || '';
    state.locale = container.dataset.locale || '';
    state.menuId = normalizeId(container.dataset.selectedMenuId);

    try { state.menus = JSON.parse(container.dataset.menus || '[]'); } catch { state.menus = []; }

    cardsList = container.querySelector('[data-menu-cards-list]');
    feedbackEl = container.querySelector('[data-menu-cards-feedback]');
    previewTree = container.querySelector('[data-menu-cards-preview]');
    previewEmpty = container.querySelector('[data-menu-cards-preview-empty]');
    saveBtn = container.querySelector('[data-menu-cards-save]');
    cancelBtn = container.querySelector('[data-menu-cards-cancel]');

    if (saveBtn) saveBtn.addEventListener('click', saveAll);
    if (cancelBtn) cancelBtn.addEventListener('click', cancelChanges);

    container.querySelector('[data-menu-cards-add]')
      ?.addEventListener('click', () => addItem(null));
    container.querySelector('[data-menu-cards-export]')
      ?.addEventListener('click', exportMenu);
    container.querySelector('[data-menu-cards-import]')
      ?.addEventListener('click', () => container.querySelector('[data-menu-cards-import-input]')?.click());
    container.querySelector('[data-menu-cards-import-input]')
      ?.addEventListener('change', importMenu);
    container.querySelector('[data-menu-cards-generate-ai]')
      ?.addEventListener('click', generateAI);

    /* Listen for menu selection changes from overview widget */
    const menuSelect = document.getElementById('menuDefinitionSelect');
    if (menuSelect) {
      menuSelect.addEventListener('change', () => {
        const newId = normalizeId(menuSelect.value);
        if (newId && newId !== state.menuId) {
          if (state.dirty.size > 0 && !confirm('Ungespeicherte Änderungen verwerfen?')) {
            menuSelect.value = state.menuId;
            return;
          }
          state.menuId = newId;
          loadItems();
        }
      });
    }

    /* Listen for menu list updates */
    document.addEventListener('marketing-menu:list-updated', (e) => {
      if (e.detail?.menus) state.menus = e.detail.menus;
    });

    /* Locale filter */
    const localeSelect = document.getElementById('menuLocaleSelect');
    if (localeSelect) {
      localeSelect.addEventListener('change', () => {
        state.locale = localeSelect.value;
        loadItems();
      });
    }

    if (state.menuId) loadItems();
    else renderEmpty();

    container.dataset.menuCardsInitialized = 'true';
  }

  /* ── API ──────────────────────────────────────────────────── */
  function apiPath(path) {
    return withNamespace(`${state.basePath}${path}`, container);
  }

  async function loadItems() {
    if (!state.menuId) { renderEmpty(); return; }

    hideFeedback(feedbackEl);
    try {
      let url = `${state.basePath}/admin/menus/${state.menuId}/items`;
      url = withNamespace(url, container);
      if (state.locale) url += `&locale=${encodeURIComponent(state.locale)}`;

      const res = await apiFetch(url);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      state.items = (data.items || data || []).map(normalizeItem);
      rebuildIndex();
      state.originalJson = JSON.stringify(state.items);
      state.dirty.clear();
      state.editingId = null;
      renderCards();
      renderPreview();
      updateButtons();
    } catch (err) {
      setFeedback(feedbackEl, 'Menüeinträge konnten nicht geladen werden.', 'danger');
      console.error('[menu-cards] loadItems error:', err);
    }
  }

  function normalizeItem(raw) {
    return {
      id: raw.id,
      menuId: raw.menu_id ?? raw.menuId ?? state.menuId,
      parentId: normalizeId(raw.parent_id ?? raw.parentId),
      label: raw.label ?? '',
      href: raw.href ?? '',
      icon: raw.icon ?? '',
      position: raw.position ?? 0,
      isExternal: !!(raw.is_external ?? raw.isExternal),
      locale: raw.locale ?? '',
      isActive: raw.is_active !== undefined ? !!raw.is_active : (raw.isActive !== undefined ? !!raw.isActive : true),
      layout: raw.layout ?? 'link',
      detailTitle: raw.detail_title ?? raw.detailTitle ?? '',
      detailText: raw.detail_text ?? raw.detailText ?? '',
      detailSubline: raw.detail_subline ?? raw.detailSubline ?? '',
      isStartpage: !!(raw.is_startpage ?? raw.isStartpage),
    };
  }

  function rebuildIndex() {
    state.byId.clear();
    state.items.forEach((item) => state.byId.set(item.id, item));
  }

  /* ── Rendering ────────────────────────────────────────────── */
  function renderEmpty() {
    if (!cardsList) return;
    cardsList.innerHTML = '<div class="menu-cards-empty">Bitte ein Menü auswählen oder anlegen.</div>';
    if (previewEmpty) previewEmpty.hidden = false;
    if (previewTree) previewTree.innerHTML = '';
  }

  function renderCards() {
    if (!cardsList) return;
    const tree = buildTree(state.items);
    if (tree.length === 0) {
      cardsList.innerHTML = '<div class="menu-cards-empty">Keine Menüeinträge vorhanden. Klicke «Eintrag hinzufügen».</div>';
      setupSortable(cardsList);
      return;
    }
    cardsList.innerHTML = tree.map((node) => renderCardNode(node, 0)).join('');
    setupSortable(cardsList);
    attachCardEvents();
    /* Re-open editing if was editing */
    if (state.editingId && state.byId.has(state.editingId)) {
      toggleInlineEditor(state.editingId, true);
    }
  }

  function buildTree(items) {
    const roots = [];
    const childrenMap = new Map();
    items.forEach((item) => {
      const pid = item.parentId;
      if (!pid || !state.byId.has(pid)) {
        roots.push(item);
      } else {
        if (!childrenMap.has(pid)) childrenMap.set(pid, []);
        childrenMap.get(pid).push(item);
      }
    });
    const sortByPos = (a, b) => (a.position ?? 0) - (b.position ?? 0);
    roots.sort(sortByPos);
    childrenMap.forEach((children) => children.sort(sortByPos));

    function toNode(item) {
      const kids = (childrenMap.get(item.id) || []).map(toNode);
      return { item, children: kids };
    }
    return roots.map(toNode);
  }

  function renderCardNode(node, depth) {
    const { item, children } = node;
    const lt = LAYOUT_TYPES[item.layout] || LAYOUT_TYPES.link;
    const inactiveClass = item.isActive ? '' : ' menu-card--inactive';
    const meta = buildMeta(item);

    let html = `<div class="menu-card${inactiveClass}" data-card-id="${item.id}" data-parent-id="${item.parentId ?? ''}">`;
    html += `<div class="menu-card__summary">`;
    html += `<span class="menu-card__drag" title="Ziehen zum Sortieren"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="5" cy="3" r="1.5"/><circle cx="11" cy="3" r="1.5"/><circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/><circle cx="5" cy="13" r="1.5"/><circle cx="11" cy="13" r="1.5"/></svg></span>`;
    html += `<span class="menu-card__type ${lt.cls}" title="${esc(lt.label)}">${lt.abbr}</span>`;
    html += `<div class="menu-card__info"><div class="menu-card__title">${esc(item.label) || '<em>Ohne Label</em>'}</div><div class="menu-card__meta">${esc(meta)}</div></div>`;
    html += `<div class="menu-card__actions">`;
    html += `<button type="button" data-card-edit="${item.id}" title="Bearbeiten"><span uk-icon="icon: pencil; ratio: 0.8"></span></button>`;
    html += `<button type="button" data-card-toggle-active="${item.id}" title="${item.isActive ? 'Deaktivieren' : 'Aktivieren'}" class="${item.isActive ? '' : 'btn-active--off'}"><span uk-icon="icon: ${item.isActive ? 'eye' : 'eye-slash'}; ratio: 0.8"></span></button>`;
    html += `<button type="button" data-card-add-child="${item.id}" title="Untereintrag hinzufügen"><span uk-icon="icon: plus-circle; ratio: 0.8"></span></button>`;
    html += `<button type="button" data-card-delete="${item.id}" title="Löschen" class="btn-delete"><span uk-icon="icon: trash; ratio: 0.8"></span></button>`;
    html += `</div></div>`; // end summary

    /* Inline edit area (hidden by default) */
    html += `<div class="menu-card__edit-area" data-card-edit-area="${item.id}" hidden>`;
    html += buildEditFields(item);
    html += `</div>`;

    /* Children container */
    if (children.length > 0) {
      html += `<div class="menu-card__children" data-children-of="${item.id}">`;
      html += children.map((child) => renderCardNode(child, depth + 1)).join('');
      html += `</div>`;
    } else {
      html += `<div class="menu-card__children" data-children-of="${item.id}"></div>`;
    }

    html += `</div>`; // end menu-card
    return html;
  }

  function buildMeta(item) {
    const parts = [];
    if (item.href) parts.push(item.href);
    if (item.locale) parts.push(item.locale);
    if (item.icon) parts.push('Icon: ' + item.icon);
    if (item.isExternal) parts.push('extern');
    if (item.isStartpage) parts.push('Startseite');
    return parts.join(' · ') || '—';
  }

  function buildEditFields(item) {
    let h = '';
    /* Row 1: Label + Link */
    h += `<div class="edit-row uk-margin-small-bottom">`;
    h += `<div><label class="uk-form-label">Label</label><input class="uk-input uk-form-small" type="text" data-field="label" value="${escAttr(item.label)}" maxlength="128"></div>`;
    h += `<div><label class="uk-form-label">Link</label><input class="uk-input uk-form-small" type="text" data-field="href" value="${escAttr(item.href)}" placeholder="/pfad oder https://..."></div>`;
    h += `</div>`;

    /* Row 2: Layout + Icon + Locale */
    h += `<div class="edit-row edit-row--three uk-margin-small-bottom">`;
    h += `<div><label class="uk-form-label">Layout</label><select class="uk-select uk-form-small" data-field="layout">`;
    ['link', 'dropdown', 'mega', 'column'].forEach((l) => {
      h += `<option value="${l}" ${item.layout === l ? 'selected' : ''}>${LAYOUT_TYPES[l]?.label || l}</option>`;
    });
    h += `</select></div>`;
    h += `<div><label class="uk-form-label">Icon</label><select class="uk-select uk-form-small" data-field="icon">`;
    ICON_OPTIONS.forEach((ic) => {
      h += `<option value="${ic}" ${item.icon === ic ? 'selected' : ''}>${ic || '(keins)'}</option>`;
    });
    h += `</select></div>`;
    h += `<div><label class="uk-form-label">Locale</label><input class="uk-input uk-form-small" type="text" data-field="locale" value="${escAttr(item.locale)}" maxlength="8" placeholder="de"></div>`;
    h += `</div>`;

    /* Toggles */
    h += `<div class="edit-toggles uk-margin-small-bottom">`;
    h += `<label><input class="uk-checkbox" type="checkbox" data-field="isActive" ${item.isActive ? 'checked' : ''}> Aktiv</label>`;
    h += `<label><input class="uk-checkbox" type="checkbox" data-field="isStartpage" ${item.isStartpage ? 'checked' : ''}> Startseite</label>`;
    h += `<label><input class="uk-checkbox" type="checkbox" data-field="isExternal" ${item.isExternal ? 'checked' : ''}> Externer Link</label>`;
    h += `</div>`;

    /* Advanced toggle */
    h += `<button type="button" class="menu-card__advanced-toggle uk-margin-small-bottom" data-advanced-toggle="${item.id}"><span uk-icon="icon: chevron-down; ratio: 0.65"></span> SEO & Erweitert</button>`;
    h += `<div class="menu-card__advanced-body" data-advanced-body="${item.id}" hidden>`;
    h += `<div class="edit-row uk-margin-small-bottom">`;
    h += `<div><label class="uk-form-label">Detail-Titel</label><input class="uk-input uk-form-small" type="text" data-field="detailTitle" value="${escAttr(item.detailTitle)}" maxlength="255"></div>`;
    h += `<div><label class="uk-form-label">Detail-Subline</label><input class="uk-input uk-form-small" type="text" data-field="detailSubline" value="${escAttr(item.detailSubline)}" maxlength="255"></div>`;
    h += `</div>`;
    h += `<div class="uk-margin-small-bottom"><label class="uk-form-label">Detail-Text</label><textarea class="uk-textarea uk-form-small" data-field="detailText" rows="3" maxlength="2000">${esc(item.detailText)}</textarea></div>`;
    h += `</div>`; // end advanced body

    /* Parent select */
    const parentOptions = state.items
      .filter((i) => i.id !== item.id && i.parentId !== item.id)
      .map((i) => `<option value="${i.id}" ${item.parentId === i.id ? 'selected' : ''}>${esc(i.label || '(Ohne Label)')} (ID ${i.id})</option>`)
      .join('');
    h += `<div class="uk-margin-small-bottom"><label class="uk-form-label">Übergeordnet</label><select class="uk-select uk-form-small" data-field="parentId"><option value="">— Hauptebene —</option>${parentOptions}</select></div>`;

    return h;
  }

  /* ── Events ───────────────────────────────────────────────── */
  function attachCardEvents() {
    if (!cardsList) return;

    /* Delegate all clicks */
    cardsList.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;

      const editId = btn.dataset.cardEdit;
      if (editId) { toggleInlineEditor(Number(editId)); return; }

      const toggleId = btn.dataset.cardToggleActive;
      if (toggleId) { toggleActive(Number(toggleId)); return; }

      const addChildId = btn.dataset.cardAddChild;
      if (addChildId) { addItem(Number(addChildId)); return; }

      const deleteId = btn.dataset.cardDelete;
      if (deleteId) { deleteItem(Number(deleteId)); return; }

      const advToggle = btn.dataset.advancedToggle;
      if (advToggle) {
        const body = cardsList.querySelector(`[data-advanced-body="${advToggle}"]`);
        if (body) body.hidden = !body.hidden;
        return;
      }
    });

    /* Delegate input changes */
    cardsList.addEventListener('input', (e) => {
      const field = e.target.dataset.field;
      if (!field) return;
      const editArea = e.target.closest('[data-card-edit-area]');
      if (!editArea) return;
      const itemId = Number(editArea.dataset.cardEditArea);
      applyFieldChange(itemId, field, e.target);
    });
    cardsList.addEventListener('change', (e) => {
      const field = e.target.dataset.field;
      if (!field) return;
      const editArea = e.target.closest('[data-card-edit-area]');
      if (!editArea) return;
      const itemId = Number(editArea.dataset.cardEditArea);
      applyFieldChange(itemId, field, e.target);
    });
  }

  function applyFieldChange(itemId, field, inputEl) {
    const item = state.byId.get(itemId);
    if (!item) return;

    if (inputEl.type === 'checkbox') {
      item[field] = inputEl.checked;
    } else if (field === 'parentId') {
      item.parentId = normalizeId(inputEl.value);
    } else {
      item[field] = inputEl.value;
    }

    state.dirty.add(itemId);
    updateButtons();

    /* Live-update the card summary without full re-render */
    const card = cardsList.querySelector(`[data-card-id="${itemId}"]`);
    if (card) {
      const titleEl = card.querySelector(':scope > .menu-card__summary .menu-card__title');
      if (titleEl) titleEl.innerHTML = esc(item.label) || '<em>Ohne Label</em>';
      const metaEl = card.querySelector(':scope > .menu-card__summary .menu-card__meta');
      if (metaEl) metaEl.textContent = buildMeta(item);
      const typeEl = card.querySelector(':scope > .menu-card__summary .menu-card__type');
      if (typeEl && field === 'layout') {
        const lt = LAYOUT_TYPES[item.layout] || LAYOUT_TYPES.link;
        typeEl.className = `menu-card__type ${lt.cls}`;
        typeEl.textContent = lt.abbr;
        typeEl.title = lt.label;
      }
      if (field === 'isActive') {
        card.classList.toggle('menu-card--inactive', !item.isActive);
      }
    }

    /* If parent changed, re-render the full tree */
    if (field === 'parentId') {
      renderCards();
      renderPreview();
    } else {
      renderPreview();
    }
  }

  function toggleInlineEditor(itemId, forceOpen) {
    const editArea = cardsList.querySelector(`[data-card-edit-area="${itemId}"]`);
    if (!editArea) return;

    const isHidden = editArea.hidden;
    const shouldOpen = forceOpen !== undefined ? forceOpen : isHidden;

    /* Close any previously open editor */
    if (state.editingId && state.editingId !== itemId) {
      const prevArea = cardsList.querySelector(`[data-card-edit-area="${state.editingId}"]`);
      if (prevArea) {
        prevArea.hidden = true;
        prevArea.closest('.menu-card')?.classList.remove('menu-card--editing');
      }
    }

    editArea.hidden = !shouldOpen;
    editArea.closest('.menu-card')?.classList.toggle('menu-card--editing', shouldOpen);
    state.editingId = shouldOpen ? itemId : null;
  }

  function toggleActive(itemId) {
    const item = state.byId.get(itemId);
    if (!item) return;
    item.isActive = !item.isActive;
    state.dirty.add(itemId);
    updateButtons();
    renderCards();
    renderPreview();
  }

  /* ── CRUD ─────────────────────────────────────────────────── */
  async function addItem(parentId) {
    if (!state.menuId) return;
    hideFeedback(feedbackEl);

    const siblings = state.items.filter((i) =>
      parentId ? i.parentId === parentId : (!i.parentId || !state.byId.has(i.parentId))
    );
    const maxPos = siblings.reduce((max, i) => Math.max(max, i.position ?? 0), -1);

    const body = {
      label: 'Neuer Eintrag',
      href: '',
      layout: 'link',
      icon: '',
      locale: state.locale || 'de',
      is_active: true,
      is_external: false,
      is_startpage: false,
      position: maxPos + 1,
      parent_id: parentId || null,
    };

    try {
      const res = await apiFetch(apiPath(`/admin/menus/${state.menuId}/items`), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const created = await res.json();
      const newItem = normalizeItem(created.item || created);
      state.items.push(newItem);
      rebuildIndex();
      state.originalJson = JSON.stringify(state.items);
      renderCards();
      renderPreview();
      toggleInlineEditor(newItem.id, true);
      setFeedback(feedbackEl, 'Eintrag hinzugefügt.', 'success');
      setTimeout(() => hideFeedback(feedbackEl), 2000);
    } catch (err) {
      setFeedback(feedbackEl, 'Fehler beim Anlegen: ' + err.message, 'danger');
      console.error('[menu-cards] addItem error:', err);
    }
  }

  async function deleteItem(itemId) {
    if (!state.menuId) return;
    const item = state.byId.get(itemId);
    if (!item) return;

    /* Check for children */
    const children = state.items.filter((i) => i.parentId === itemId);
    const msg = children.length > 0
      ? `"${item.label || 'Eintrag'}" und ${children.length} Untereintrag/Untereinträge wirklich löschen?`
      : `"${item.label || 'Eintrag'}" wirklich löschen?`;
    if (!confirm(msg)) return;

    hideFeedback(feedbackEl);
    try {
      const res = await apiFetch(apiPath(`/admin/menus/${state.menuId}/items/${itemId}`), {
        method: 'DELETE',
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      /* Remove item and all descendants */
      const removeIds = new Set([itemId]);
      let changed = true;
      while (changed) {
        changed = false;
        state.items.forEach((i) => {
          if (i.parentId && removeIds.has(i.parentId) && !removeIds.has(i.id)) {
            removeIds.add(i.id);
            changed = true;
          }
        });
      }
      state.items = state.items.filter((i) => !removeIds.has(i.id));
      rebuildIndex();
      state.dirty.forEach((id) => { if (removeIds.has(id)) state.dirty.delete(id); });
      state.originalJson = JSON.stringify(state.items);

      if (state.editingId && removeIds.has(state.editingId)) state.editingId = null;
      renderCards();
      renderPreview();
      updateButtons();
      setFeedback(feedbackEl, 'Eintrag gelöscht.', 'success');
      setTimeout(() => hideFeedback(feedbackEl), 2000);
    } catch (err) {
      setFeedback(feedbackEl, 'Fehler beim Löschen: ' + err.message, 'danger');
      console.error('[menu-cards] deleteItem error:', err);
    }
  }

  /* ── Save / Cancel ────────────────────────────────────────── */
  async function saveAll() {
    if (state.dirty.size === 0) return;
    hideFeedback(feedbackEl);

    const dirtyIds = [...state.dirty];
    let saved = 0;
    let errors = 0;

    for (const id of dirtyIds) {
      const item = state.byId.get(id);
      if (!item) continue;

      if (!validateHref(item.href)) {
        setFeedback(feedbackEl, `Ungültiger Link für "${item.label}": ${item.href}`, 'warning');
        toggleInlineEditor(id, true);
        return;
      }

      try {
        const res = await apiFetch(apiPath(`/admin/menus/${state.menuId}/items/${id}`), {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            label: item.label,
            href: item.href,
            icon: item.icon,
            layout: item.layout,
            locale: item.locale,
            is_active: item.isActive,
            is_external: item.isExternal,
            is_startpage: item.isStartpage,
            position: item.position,
            parent_id: item.parentId,
            detail_title: item.detailTitle,
            detail_text: item.detailText,
            detail_subline: item.detailSubline,
          }),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        saved++;
        state.dirty.delete(id);
      } catch (err) {
        errors++;
        console.error(`[menu-cards] saveItem ${id} error:`, err);
      }
    }

    if (errors > 0) {
      setFeedback(feedbackEl, `${saved} gespeichert, ${errors} Fehler.`, 'warning');
    } else {
      setFeedback(feedbackEl, `${saved} Einträge gespeichert.`, 'success');
      setTimeout(() => hideFeedback(feedbackEl), 2500);
    }
    state.originalJson = JSON.stringify(state.items);
    updateButtons();
  }

  function cancelChanges() {
    if (state.dirty.size === 0) return;
    if (!confirm('Alle ungespeicherten Änderungen verwerfen?')) return;
    try {
      state.items = JSON.parse(state.originalJson).map(normalizeItem);
      rebuildIndex();
    } catch {
      /* fallback: reload from server */
      loadItems();
      return;
    }
    state.dirty.clear();
    state.editingId = null;
    renderCards();
    renderPreview();
    updateButtons();
  }

  function updateButtons() {
    const hasDirty = state.dirty.size > 0;
    if (saveBtn) saveBtn.disabled = !hasDirty;
    if (cancelBtn) cancelBtn.disabled = !hasDirty;
  }

  /* ── Drag & Drop (UIKit Sortable) ─────────────────────────── */
  function setupSortable(root) {
    /* Root level sortable */
    if (typeof UIkit !== 'undefined' && cardsList) {
      try {
        UIkit.sortable(cardsList, {
          group: 'menu-cards',
          handle: '.menu-card__drag',
          animation: 150,
        });

        UIkit.util.on(cardsList, 'moved', () => collectOrder(null, cardsList));
        UIkit.util.on(cardsList, 'added', () => collectOrder(null, cardsList));
      } catch (e) {
        console.warn('[menu-cards] UIkit sortable init error:', e);
      }

      /* Children sortable containers */
      cardsList.querySelectorAll('[data-children-of]').forEach((childContainer) => {
        try {
          UIkit.sortable(childContainer, {
            group: 'menu-cards',
            handle: '.menu-card__drag',
            animation: 150,
          });
          const parentId = Number(childContainer.dataset.childrenOf);
          UIkit.util.on(childContainer, 'moved', () => collectOrder(parentId, childContainer));
          UIkit.util.on(childContainer, 'added', (e) => collectOrder(parentId, childContainer));
        } catch (e) {
          console.warn('[menu-cards] child sortable error:', e);
        }
      });
    }
  }

  function collectOrder(parentId, containerEl) {
    const cards = containerEl.querySelectorAll(':scope > .menu-card');
    cards.forEach((card, index) => {
      const id = Number(card.dataset.cardId);
      const item = state.byId.get(id);
      if (!item) return;

      const newParentId = parentId;
      if (item.position !== index || item.parentId !== newParentId) {
        item.position = index;
        item.parentId = newParentId;
        state.dirty.add(id);
      }
    });
    updateButtons();
    renderPreview();
  }

  /* ── Preview ──────────────────────────────────────────────── */
  function renderPreview() {
    if (!previewTree) return;
    const tree = buildTree(state.items.filter((i) => i.isActive));

    if (tree.length === 0) {
      previewTree.innerHTML = '';
      if (previewEmpty) previewEmpty.hidden = false;
      return;
    }
    if (previewEmpty) previewEmpty.hidden = true;

    function renderBranch(nodes) {
      let html = '<ul class="menu-preview-nav">';
      for (const node of nodes) {
        const { item, children } = node;
        let cls = 'preview-item';
        if (!item.isActive) cls += ' preview-item--inactive';
        if (item.isExternal) cls += ' preview-item--external';
        const badge = item.layout !== 'link'
          ? `<span class="preview-badge preview-badge--${item.layout}">${(LAYOUT_TYPES[item.layout]?.label || item.layout)}</span>`
          : '';
        html += `<li class="${cls}">`;
        html += `<a href="javascript:void(0)">${esc(item.label)}${badge}</a>`;
        if (children.length > 0) {
          html += renderBranch(children);
        }
        html += `</li>`;
      }
      html += '</ul>';
      return html;
    }

    previewTree.innerHTML = renderBranch(tree);
  }

  /* ── AI Generation ────────────────────────────────────────── */
  async function generateAI() {
    if (!state.menuId) return;
    const pageId = container.dataset.pageId;
    if (!pageId) {
      setFeedback(feedbackEl, 'Keine Seite zugeordnet für KI-Generierung.', 'warning');
      return;
    }
    if (!confirm('Menü automatisch aus Seiteninhalt generieren? Bestehende Einträge werden ersetzt.')) return;

    hideFeedback(feedbackEl);
    setFeedback(feedbackEl, 'KI generiert Menü…', 'primary');

    try {
      const res = await apiFetch(apiPath(`/admin/pages/${pageId}/menu/ai`), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ menuId: state.menuId }),
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setFeedback(feedbackEl, 'Menü wurde generiert.', 'success');
      setTimeout(() => hideFeedback(feedbackEl), 3000);
      await loadItems();
    } catch (err) {
      setFeedback(feedbackEl, 'KI-Generierung fehlgeschlagen: ' + err.message, 'danger');
      console.error('[menu-cards] generateAI error:', err);
    }
  }

  /* ── Export / Import ──────────────────────────────────────── */
  function exportMenu() {
    if (!state.menuId || state.items.length === 0) return;
    const menu = state.menus.find((m) => m.id === state.menuId);
    const data = {
      menu: menu ? { label: menu.label, locale: menu.locale } : {},
      items: state.items.map((item) => ({
        label: item.label,
        href: item.href,
        icon: item.icon,
        layout: item.layout,
        locale: item.locale,
        is_active: item.isActive,
        is_external: item.isExternal,
        is_startpage: item.isStartpage,
        position: item.position,
        parent_id: item.parentId,
        detail_title: item.detailTitle,
        detail_text: item.detailText,
        detail_subline: item.detailSubline,
      })),
    };
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `menu-${state.menuId}-export.json`;
    a.click();
    URL.revokeObjectURL(a.href);
  }

  async function importMenu(e) {
    const file = e.target.files?.[0];
    if (!file || !state.menuId) return;
    e.target.value = '';

    try {
      const text = await file.text();
      const data = JSON.parse(text);
      const importItems = data.items || [];
      if (importItems.length === 0) {
        setFeedback(feedbackEl, 'Import-Datei enthält keine Einträge.', 'warning');
        return;
      }
      if (!confirm(`${importItems.length} Einträge importieren? Bestehende Einträge bleiben erhalten.`)) return;

      hideFeedback(feedbackEl);
      setFeedback(feedbackEl, 'Importiere…', 'primary');

      /* Create items one by one, mapping parent IDs */
      const idMap = new Map(); // old parent_id → new id
      for (const raw of importItems) {
        const parentId = raw.parent_id ? (idMap.get(raw.parent_id) || null) : null;
        const body = { ...raw, parent_id: parentId };
        const res = await apiFetch(apiPath(`/admin/menus/${state.menuId}/items`), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const created = await res.json();
        const newItem = created.item || created;
        if (raw.id) idMap.set(raw.id, newItem.id);
      }

      setFeedback(feedbackEl, `${importItems.length} Einträge importiert.`, 'success');
      setTimeout(() => hideFeedback(feedbackEl), 3000);
      await loadItems();
    } catch (err) {
      setFeedback(feedbackEl, 'Import fehlgeschlagen: ' + err.message, 'danger');
      console.error('[menu-cards] importMenu error:', err);
    }
  }

  /* ── Bootstrap ────────────────────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
