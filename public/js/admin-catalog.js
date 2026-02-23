/* global UIkit */

/**
 * Catalog / Questions editor module extracted from admin.js.
 *
 * @param {object} ctx  Dependencies injected by the host.
 * @returns {{ loadCatalogs: Function, applyCatalogList: Function, catalogs: Array, catalogManager: object|undefined, catSelect: HTMLElement|null, saveCatalogs: Function }}
 */
export function initCatalog(ctx) {
  const {
    apiFetch,
    notify,
    withBase,
    getCurrentEventUid,
    cfgInitial,
    cfgFields,
    registerCacheReset,
    TableManager,
    createCellEditor,
    appendNamespaceParam,
    transCatalogsFetchError,
    transCatalogsForbidden,
    commentTextarea,
    commentModal,
    catalogEditInput,
    catalogEditError,
    resultsResetModal,
    resultsResetConfirm,
  } = ctx;

  // Shared mutable reference – the caller owns `currentCommentItem` but we
  // need to read/write it from within the catalog module.
  const commentState = ctx.commentState; // { currentCommentItem }

  // ── Helpers (previously defined before the catalog section) ─────────────

  function slugify(text) {
    return text
      .toString()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/ß/g, 'ss')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
  }

  function getUsedIds() {
    const list = typeof catalogManager !== 'undefined' && catalogManager
      ? catalogManager.getData()
      : catalogs;
    return new Set(list.map(c => c.slug || c.sort_order));
  }

  function uniqueId(text) {
    let base = slugify(text);
    if (!base) return '';
    const used = getUsedIds();
    let id = base;
    let i = 2;
    while (used.has(id)) {
      id = base + '_' + i;
      i++;
    }
    return id;
  }

  function insertSoftHyphens(text) {
    return text ? text.replace(/\/-/g, '\u00AD') : '';
  }

  // ── Catalog / Questions state ──────────────────────────────────────────

  const container = document.getElementById('questions');
  const addBtn = document.getElementById('addBtn');
  const catSelect = document.getElementById('catalogSelect');
  const catalogList = document.getElementById('catalogList');
  const newCatBtn = document.getElementById('newCatBtn');
  let catalogs = [];
  let catalogFile = '';
  let initial = [];
  let undoStack = [];

  registerCacheReset(() => {
    catalogs = [];
    catalogManager?.render([]);
    if (catSelect) {
      catSelect.innerHTML = '';
      catSelect.value = '';
    }
    resetQuestionEditorState();
  });

  const parseBoolean = value => {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value > 0;
    if (typeof value === 'string') {
      const normalized = value.trim().toLowerCase();
      return ['1', 'true', 'yes', 'on'].includes(normalized);
    }
    return false;
  };

  function isCountdownFeatureEnabled() {
    const raw = cfgInitial.countdownEnabled ?? cfgInitial.countdown_enabled ?? false;
    return parseBoolean(raw);
  }

  function getDefaultCountdownSeconds() {
    const raw = cfgInitial.countdown ?? cfgInitial.defaultCountdown ?? 0;
    const parsed = Number.parseInt(raw, 10);
    if (!Number.isNaN(parsed) && parsed > 0) {
      return parsed;
    }
    return null;
  }

  function parseCountdownValue(value) {
    if (value === null || value === undefined) return null;
    const trimmed = String(value).trim();
    if (trimmed === '') return null;
    const parsed = Number.parseInt(trimmed, 10);
    if (Number.isNaN(parsed) || parsed < 0) {
      return null;
    }
    return parsed;
  }

  // Zähler für eindeutige Namen von Eingabefeldern
  let cardIndex = 0;

  container?.addEventListener('input', () => saveQuestions());
  container?.addEventListener('change', () => saveQuestions());
  if (container && window.UIkit && UIkit.util) {
    UIkit.util.on(container, 'moved', () => saveQuestions());
  }

  const catalogPaginationEl = document.getElementById('catalogsPagination');

  const commentPreviewScratch = document.createElement('div');
  const COMMENT_PREVIEW_LIMIT = 140;

  function extractCommentPreview(raw) {
    if (!raw) {
      return { preview: '', full: '' };
    }
    commentPreviewScratch.innerHTML = raw;
    const text = (commentPreviewScratch.textContent || commentPreviewScratch.innerText || '')
      .replace(/\s+/g, ' ')
      .trim();
    commentPreviewScratch.textContent = '';
    if (!text) {
      return { preview: '', full: '' };
    }
    if (text.length <= COMMENT_PREVIEW_LIMIT) {
      return { preview: text, full: text };
    }
    const slice = text.slice(0, COMMENT_PREVIEW_LIMIT + 1);
    const lastSpace = slice.lastIndexOf(' ');
    const base = lastSpace > 0 ? slice.slice(0, lastSpace) : slice.slice(0, COMMENT_PREVIEW_LIMIT);
    const preview = `${base.trimEnd()} …`;
    return { preview, full: text };
  }

  function renderCatalogComment(item) {
    const { preview, full } = extractCommentPreview(item?.comment);
    if (!preview) {
      return '';
    }
    const span = document.createElement('span');
    span.classList.add('uk-text-truncate');
    span.textContent = preview;
    if (full && full !== preview) {
      span.title = full;
    }
    return span;
  }

  const catalogColumns = [
    { key: 'slug', label: 'Slug', className: 'uk-table-shrink', editable: true },
    { key: 'name', label: 'Name', className: 'uk-table-expand', editable: true },
    { key: 'description', label: window.transDescription || 'Description', className: 'uk-table-expand', editable: true },
    { key: 'raetsel_buchstabe', label: window.transPuzzleLetter || 'Puzzle letter', className: 'uk-table-shrink', editable: true },
    {
      key: 'comment',
      label: window.transComment || 'Comment',
      className: 'uk-table-expand',
      editable: true,
      ariaDesc: window.transEditComment || 'Edit comment',
      render: renderCatalogComment
    },
    {
      className: 'uk-table-shrink',
      render: item => {
        const wrapper = document.createElement('div');
        wrapper.className = 'uk-flex uk-flex-middle uk-flex-right';

        const delBtn = document.createElement('button');
        delBtn.className = 'uk-icon-button qr-action uk-text-danger';
        delBtn.setAttribute('uk-icon', 'trash');
        delBtn.setAttribute('aria-label', window.transDelete || 'Delete');
        delBtn.setAttribute('uk-tooltip', 'title: ' + (window.transDelete || 'Delete') + '; pos: left');
        delBtn.addEventListener('click', () => deleteCatalogById(item.id));

        wrapper.appendChild(delBtn);
        return wrapper;
      },
      renderCard: item => {
        const wrapper = document.createElement('div');
        wrapper.className = 'uk-flex uk-flex-middle uk-flex-right qr-action';

        const delBtn = document.createElement('button');
        delBtn.className = 'uk-icon-button qr-action uk-text-danger';
        delBtn.setAttribute('uk-icon', 'trash');
        delBtn.setAttribute('aria-label', window.transDelete || 'Delete');
        delBtn.addEventListener('click', () => deleteCatalogById(item.id));

        wrapper.appendChild(delBtn);
        return wrapper;
      }
    }
  ];

  let catalogManager;
  let catalogEditor;
  if (catalogList) {
    catalogManager = new TableManager({
      tbody: catalogList,
      mobileCards: { container: document.getElementById('catalogCards') },
      sortable: true,
      columns: catalogColumns,
      onEdit: cell => {
        const key = cell?.dataset.key;
        if (key === 'comment') {
          const id = cell?.dataset.id;
          const list = catalogManager.getData();
          const cat = list.find(c => c.id === id);
          commentState.currentCommentItem = cat || null;
          if (commentTextarea) commentTextarea.value = cat?.comment || '';
          commentModal.show();
        } else {
          catalogEditError.hidden = true;
          catalogEditor.open(cell);
        }
      },
      onReorder: () => saveCatalogs(catalogManager.getData(), false, true)
    });
    catalogEditor = createCellEditor(catalogManager, {
      modalSelector: '#catalogEditModal',
      inputSelector: '#catalogEditInput',
      saveSelector: '#catalogEditSave',
      cancelSelector: '#catalogEditCancel',
      getTitle: key => catalogColumns.find(c => c.key === key)?.label || '',
      onSave: (list, item, key) => {
        const val = catalogEditInput.value.trim();
        if (key === 'slug') {
          item.slug = val;
        } else if (key === 'name') {
          item.name = val;
          if (item.new && !item.slug) {
            const idSlug = uniqueId(val);
            item.slug = idSlug;
          }
        } else if (key === 'description') {
          item.description = val;
        } else if (key === 'raetsel_buchstabe') {
          item.raetsel_buchstabe = val;
        }
        catalogManager.render(list);
        saveCatalogs(list, true);
      }
    });
    if (catalogPaginationEl) {
      catalogManager.bindPagination(catalogPaginationEl, 50);
    }
  }

  async function saveCatalogs(list = catalogManager?.getData() || [], show = false, reorder = false, retries = 1) {
    for (const item of list) {
      const currentId = item.slug?.trim() || '';
      const newFile = currentId ? currentId + '.json' : '';
      if (item.new) {
        let id = currentId;
        if (!id) {
          id = uniqueId(item.name || '');
        }
        if (!id) continue;
        try {
          await apiFetch('/kataloge/' + id + '.json', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: '[]'
          });
          item.new = false;
          item.file = id + '.json';
          item.slug = id;
        } catch (err) {
          console.error(err);
          notify(window.transErrorCreateFailed || 'Creation failed', 'danger');
        }
      } else if (currentId && item.file && item.file !== newFile) {
        try {
          const res = await apiFetch('/kataloge/' + item.file, { headers: { 'Accept': 'application/json' } });
          const content = await res.text();
          await apiFetch('/kataloge/' + newFile, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: content });
          await apiFetch('/kataloge/' + item.file, { method: 'DELETE' });
          item.file = newFile;
        } catch (err) {
          console.error(err);
          notify(window.transErrorRenameFailed || 'Rename failed', 'danger');
        }
      }
      item.file = newFile;
    }

    const data = list
      .map((c, idx) => ({
        uid: c.id,
        sort_order: idx + 1,
        slug: c.slug,
        file: c.slug ? c.slug + '.json' : '',
        name: c.name,
        description: c.description,
        raetsel_buchstabe: c.raetsel_buchstabe,
        comment: c.comment
      }))
      .filter(c => c.slug);

    try {
      const r = await apiFetch('/kataloge/catalogs.json', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      if (!r.ok) throw new Error(r.statusText);
      catalogs = data.map(c => ({ ...c, id: c.uid }));
      catSelect.innerHTML = '';
      catalogs.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name || c.sort_order || c.slug;
        catSelect.appendChild(opt);
      });
      if (!catalogFile && catalogs.length > 0) {
        catSelect.value = catalogs[0].id;
        loadCatalog(catSelect.value);
      }
      if (show && !reorder) notify(window.transCatalogListSaved || 'Catalogue list saved', 'success');
    } catch (err) {
      console.error(err);
      if (retries > 0) {
        notify(window.transErrorSaveRetry || 'Save failed, please try again\u2026', 'warning');
        setTimeout(() => saveCatalogs(list, show, reorder, retries - 1), 1000);
      } else {
        notify(window.transErrorSaveFailed || 'Save failed', 'danger');
      }
    }
  }

  const appendEventParam = (url) => {
    const currentEventUid = getCurrentEventUid();
    if (!currentEventUid) return url;
    const separator = url.includes('?') ? '&' : '?';
    return url + separator + 'event=' + encodeURIComponent(currentEventUid);
  };

  function loadCatalog(identifier) {
    const cat = catalogs.find(c => c.id === identifier || c.uid === identifier || (c.slug || c.sort_order) === identifier);
    if (!cat) return;
    catalogFile = cat.file;
    apiFetch(appendEventParam(appendNamespaceParam('/kataloge/' + catalogFile)), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(data => {
        initial = data;
        renderAll(initial);
        undoStack = [JSON.parse(JSON.stringify(initial))];
      })
      .catch(() => {
        initial = [];
        renderAll(initial);
        undoStack = [JSON.parse(JSON.stringify(initial))];
      });
  }

  function applyCatalogList(list = []) {
    const timestamp = Date.now();
    catalogs = (Array.isArray(list) ? list : []).map((item, index) => {
      const baseId = item?.id ?? item?.uid ?? item?.slug ?? item?.sort_order;
      const id = baseId !== undefined && baseId !== null && baseId !== ''
        ? String(baseId)
        : String(timestamp + index);
      return { ...item, id };
    });
    if (catSelect) {
      catSelect.innerHTML = '';
      catalogs.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name || c.sort_order || c.slug;
        catSelect.appendChild(opt);
      });
    }
    if (!catalogManager) return;
    catalogManager.render(catalogs);
    if (!catalogs.length) {
      if (catSelect) {
        catSelect.value = '';
      }
      resetQuestionEditorState();
      return;
    }
    if (catSelect) {
      const params = new URLSearchParams(window.location.search);
      const slug = params.get('katalog');
      const selected = catalogs.find(c => (c.slug || c.sort_order) === slug) || catalogs[0];
      if (selected) {
        catSelect.value = String(selected.id);
        loadCatalog(selected.id);
      }
    }
  }

  async function loadLegacyCatalogs() {
    const res = await apiFetch(appendEventParam(appendNamespaceParam('/kataloge/catalogs.json')), { headers: { 'Accept': 'application/json' } });
    if (!res.ok) {
      throw new Error(`Legacy catalogs request failed with status ${res.status}`);
    }
    const list = await res.json();
    applyCatalogList(list);
  }

  async function loadCatalogs() {
    const currentEventUid = getCurrentEventUid();
    if (!currentEventUid) {
      applyCatalogList([]);
      return;
    }
    catalogManager?.setColumnLoading('name', true);
    try {
      const res = await apiFetch(appendEventParam(appendNamespaceParam('/admin/catalogs/data')), { headers: { 'Accept': 'application/json' } });
      if (res.status === 404) {
        await loadLegacyCatalogs();
        return;
      }
      if (res.status === 401 || res.status === 403) {
        notify(transCatalogsForbidden, 'warning', 4000);
        return;
      }
      if (!res.ok) {
        throw new Error(`Admin catalogs request failed with status ${res.status}`);
      }
      const data = await res.json();
      if (data && typeof data === 'object' && data.useLegacy) {
        await loadLegacyCatalogs();
        return;
      }
      const list = data.items || data;
      applyCatalogList(list);
    } catch (err) {
      console.error(err);
      notify(transCatalogsFetchError, 'danger', 4000);
    } finally {
      catalogManager?.setColumnLoading('name', false);
    }
  }

  if (catalogList || catSelect) {
    loadCatalogs();

    if (catSelect) {
      catSelect.addEventListener('change', () => loadCatalog(catSelect.value));
    }
  }

  function deleteCatalogById(id) {
    const list = catalogManager.getData();
    const cat = list.find(c => c.id === id);
    if (!cat) return;
    if (cat.new || !cat.file) {
      catalogManager.render(list.filter(c => c.id !== id));
      return;
    }
    if (!confirm(window.transConfirmCatalogDelete || 'Really delete catalogue?')) return;
    apiFetch('/kataloge/' + cat.file, { method: 'DELETE' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        const updated = list.filter(c => c.id !== id);
        catalogManager.render(updated);
        catalogs = updated;
        const opt = catSelect.querySelector('option[value="' + id + '"]');
        opt?.remove();
        if (catalogs[0]) {
          if (catSelect.value === String(id)) {
            catSelect.value = catalogs[0].id;
            loadCatalog(catSelect.value);
          }
        } else {
          resetQuestionEditorState();
        }
        saveCatalogs(updated);
        notify(window.transCatalogDeleted || 'Catalogue deleted', 'success');
      })
      .catch(err => {
        console.error(err);
        notify(window.transErrorDeleteFailed || 'Delete failed', 'danger');
      });
  }

  // Rendert alle Fragen im Editor neu
  function renderAll(data) {
    if (!container) {
      return;
    }
    container.innerHTML = '';
    cardIndex = 0;
    data.forEach((q, i) => container.appendChild(createCard(q, i)));
  }

  function resetQuestionEditorState() {
    catalogFile = '';
    initial = [];
    renderAll(initial);
    undoStack = [JSON.parse(JSON.stringify(initial))];
  }

  // Erstellt ein Bearbeitungsformular für eine Frage (Block-Card-Pattern)
  function createCard(q, index = -1) {
    const card = document.createElement('div');
    card.className = 'question-block-card question-card';
    if (index >= 0) {
      card.dataset.index = String(index);
    }

    const TYPES = ['sort', 'assign', 'mc', 'swipe', 'photoText', 'flip'];
    const labelMap = {
      mc: window.transQuizTypeMc || 'Multiple Choice',
      assign: window.transQuizTypeAssign || 'Assign',
      sort: window.transQuizTypeSort || 'Sort',
      swipe: window.transQuizTypeSwipe || 'Swipe',
      photoText: window.transQuizTypePhotoText || 'Foto+Text',
      flip: window.transQuizTypeFlip || 'Wusstest du?'
    };
    const abbrMap = { mc: 'MC', assign: 'A', sort: 'S', swipe: 'T', photoText: 'P', flip: 'F' };
    const colorMap = { sort: '#1e87f0', assign: '#32d296', mc: '#f0506e', swipe: '#faa05a', flip: '#7c5cbf', photoText: '#6c757d' };
    const infoMap = {
      sort: window.transQuizInfoSort || 'Put items in the correct order.',
      assign: window.transQuizInfoAssign || 'Match terms to their definitions.',
      mc: window.transQuizInfoMc || 'Multiple choice (several answers possible).',
      swipe: window.transQuizInfoSwipe || 'Swipe cards left or right.',
      photoText: window.transQuizInfoPhotoText || 'Take a photo and enter the matching answer.',
      flip: window.transQuizInfoFlip || 'Question with a flippable answer card.'
    };

    // Hidden select preserved for collect() compatibility
    const typeSelect = document.createElement('select');
    typeSelect.className = 'type-select';
    typeSelect.style.display = 'none';
    TYPES.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t;
      opt.textContent = labelMap[t] || t;
      typeSelect.appendChild(opt);
    });
    typeSelect.value = q.type || 'mc';

    // ── Summary row ──────────────────────────────────────────────────────
    const summary = document.createElement('div');
    summary.className = 'card-row__summary';

    const dragHandle = document.createElement('div');
    dragHandle.className = 'card-row__drag';
    dragHandle.dataset.dragHandle = 'true';
    dragHandle.setAttribute('aria-hidden', 'true');
    dragHandle.setAttribute('uk-icon', 'table');
    summary.appendChild(dragHandle);

    const quizColorMap = { sort: 'badge-blue', assign: 'badge-green', mc: 'badge-red', swipe: 'badge-orange', flip: 'badge-purple', photoText: 'badge-gray' };
    const typeBadge = document.createElement('div');
    typeBadge.className = 'card-row__badge ' + (quizColorMap[q.type || 'mc'] || 'badge-muted');
    typeBadge.textContent = abbrMap[q.type || 'mc'] || '?';
    typeBadge.title = labelMap[q.type || 'mc'] || '';
    summary.appendChild(typeBadge);

    const numberBadge = document.createElement('span');
    numberBadge.className = 'question-block-card__number';
    numberBadge.textContent = index >= 0 ? String(index + 1) : '#';
    summary.appendChild(numberBadge);

    const infoEl = document.createElement('div');
    infoEl.className = 'card-row__info';
    const titleEl = document.createElement('div');
    titleEl.className = 'card-row__title';
    titleEl.textContent = q.prompt || (window.transNewQuestion || 'New question');
    const metaEl = document.createElement('div');
    metaEl.className = 'card-row__meta';
    infoEl.appendChild(titleEl);
    infoEl.appendChild(metaEl);
    summary.appendChild(infoEl);

    const actions = document.createElement('div');
    actions.className = 'card-row__actions';
    const editBtn = document.createElement('button');
    editBtn.setAttribute('uk-icon', 'pencil');
    editBtn.setAttribute('aria-label', window.transEditQuestion || 'Edit');
    editBtn.setAttribute('type', 'button');
    editBtn.className = 'btn-edit';
    const dupBtn = document.createElement('button');
    dupBtn.setAttribute('uk-icon', 'copy');
    dupBtn.setAttribute('aria-label', window.transDuplicate || 'Duplicate');
    dupBtn.setAttribute('type', 'button');
    dupBtn.className = 'btn-duplicate';
    const deleteBtn = document.createElement('button');
    deleteBtn.setAttribute('uk-icon', 'trash');
    deleteBtn.setAttribute('aria-label', window.transRemove || 'Remove');
    deleteBtn.setAttribute('type', 'button');
    deleteBtn.className = 'btn-delete';
    actions.appendChild(editBtn);
    actions.appendChild(dupBtn);
    actions.appendChild(deleteBtn);
    summary.appendChild(actions);
    card.appendChild(summary);

    // ── Edit area ────────────────────────────────────────────────────────
    const editArea = document.createElement('div');
    editArea.className = 'question-block-card__edit-area';
    if (index >= 0) editArea.classList.add('is-collapsed'); // existing cards start collapsed

    // Type selector grid
    const typeGrid = document.createElement('div');
    typeGrid.className = 'question-type-grid uk-margin-small-bottom';
    TYPES.forEach(t => {
      const opt = document.createElement('div');
      opt.className = 'question-type-option' + (typeSelect.value === t ? ' is-active' : '');
      opt.setAttribute('role', 'button');
      opt.setAttribute('tabindex', '0');
      opt.setAttribute('aria-label', labelMap[t] || t);
      const badge = document.createElement('div');
      badge.className = 'question-type-option__badge';
      badge.style.background = colorMap[t] || '#999';
      badge.textContent = abbrMap[t] || t;
      const lbl = document.createElement('span');
      lbl.textContent = labelMap[t] || t;
      opt.appendChild(badge);
      opt.appendChild(lbl);
      opt.addEventListener('click', () => {
        typeSelect.value = t;
        typeGrid.querySelectorAll('.question-type-option').forEach(o => o.classList.remove('is-active'));
        opt.classList.add('is-active');
        typeBadge.className = 'card-row__badge ' + (quizColorMap[t] || 'badge-muted');
        typeBadge.textContent = abbrMap[t] || '?';
        typeBadge.title = labelMap[t] || '';
        updateInfo();
        renderFields();
        updatePointsState();
        updatePreview();
        updateSummary();
      });
      opt.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); opt.click(); } });
      typeGrid.appendChild(opt);
    });
    editArea.appendChild(typeSelect);
    editArea.appendChild(typeGrid);

    const typeInfo = document.createElement('div');
    typeInfo.className = 'uk-alert-primary uk-margin-small-bottom type-info';
    editArea.appendChild(typeInfo);

    const prompt = document.createElement('textarea');
    prompt.className = 'uk-textarea uk-margin-small-bottom prompt';
    prompt.placeholder = window.transQuestionText || 'Question text';
    prompt.value = q.prompt || '';
    editArea.appendChild(prompt);

    const countdownEnabled = isCountdownFeatureEnabled();
    const defaultCountdown = getDefaultCountdownSeconds();
    const countdownId = `countdown-${cardIndex}`;
    const countdownGroup = document.createElement('div');
    countdownGroup.className = 'uk-margin-small-bottom';
    const countdownLabel = document.createElement('label');
    countdownLabel.className = 'uk-form-label';
    countdownLabel.setAttribute('for', countdownId);
    countdownLabel.textContent = window.transCountdownLabel || 'Time limit (seconds)';
    const countdownInput = document.createElement('input');
    countdownInput.className = 'uk-input countdown-input';
    countdownInput.type = 'number';
    countdownInput.min = '0';
    countdownInput.id = countdownId;
    const hasCountdown = Object.prototype.hasOwnProperty.call(q, 'countdown');
    if (hasCountdown && q.countdown !== null && q.countdown !== undefined) {
      countdownInput.value = String(q.countdown);
    }
    countdownInput.placeholder = defaultCountdown !== null ? (window.transCountdownDefault || 'Default: %ss').replace('%s', defaultCountdown) : (window.transCountdownPlaceholder || 'e.g. 45');
    countdownInput.disabled = !countdownEnabled;
    const countdownMeta = document.createElement('div');
    countdownMeta.className = 'uk-text-meta';
    const countdownDisabledHint = cfgFields.countdownEnabled
      ? (window.transCountdownEnableHintExtras || 'Enable countdown under "Extras" in the event settings to set a time limit.')
      : (window.transCountdownEnableHint || 'Enable countdown to set a time limit.');
    countdownMeta.textContent = countdownEnabled
      ? (window.transCountdownTimerHint || 'Leave empty for default, 0 disables the timer.')
      : countdownDisabledHint;
    countdownGroup.appendChild(countdownLabel);
    countdownGroup.appendChild(countdownInput);
    countdownGroup.appendChild(countdownMeta);
    editArea.appendChild(countdownGroup);

    const pointsId = `points-${cardIndex}`;
    const pointsGroup = document.createElement('div');
    pointsGroup.className = 'uk-margin-small-bottom question-points-group';
    const pointsLabel = document.createElement('label');
    pointsLabel.className = 'uk-form-label';
    pointsLabel.setAttribute('for', pointsId);
    pointsLabel.textContent = window.transPointsRange || 'Points (0\u201310000)';
    const pointsInput = document.createElement('input');
    pointsInput.className = 'uk-input points-input';
    pointsInput.type = 'number';
    pointsInput.id = pointsId;
    pointsInput.min = '0';
    pointsInput.max = '10000';
    pointsInput.step = '1';
    pointsInput.setAttribute('aria-label', window.transPointsPerQuestion || 'Points per question');
    const existingPoints = parseQuestionPoints(q.points);
    if (typeSelect.value === 'flip') {
      pointsInput.value = existingPoints !== null ? String(existingPoints) : '0';
    } else {
      pointsInput.value = existingPoints !== null ? String(existingPoints) : '1';
    }
    const pointsMeta = document.createElement('div');
    pointsMeta.className = 'uk-text-meta';
    pointsGroup.appendChild(pointsLabel);
    pointsGroup.appendChild(pointsInput);
    pointsGroup.appendChild(pointsMeta);
    let lastScorablePoints = existingPoints ?? 1;
    editArea.appendChild(pointsGroup);

    const fields = document.createElement('div');
    fields.className = 'fields';
    editArea.appendChild(fields);

    const previewLabel = document.createElement('div');
    previewLabel.className = 'question-block-card__preview-label';
    previewLabel.textContent = window.transPreview || 'Vorschau';
    editArea.appendChild(previewLabel);

    const preview = document.createElement('div');
    preview.className = 'uk-card qr-card uk-card-body question-preview';
    editArea.appendChild(preview);

    const collapseLink = document.createElement('button');
    collapseLink.type = 'button';
    collapseLink.className = 'question-block-card__collapse-btn';
    collapseLink.textContent = window.transCollapse || 'Einklappen';
    collapseLink.addEventListener('click', () => {
      editArea.classList.add('is-collapsed');
      editBtn.classList.remove('is-active');
    });
    editArea.appendChild(collapseLink);

    card.appendChild(editArea);

    // ── Edit toggle ───────────────────────────────────────────────────────
    function toggleEdit() {
      const opening = editArea.classList.contains('is-collapsed');
      editArea.classList.toggle('is-collapsed');
      editBtn.classList.toggle('is-active', !editArea.classList.contains('is-collapsed'));
      if (opening) updatePreview();
    }
    editBtn.addEventListener('click', toggleEdit);
    // Double-click on summary row also toggles edit
    summary.addEventListener('dblclick', e => {
      if (e.target.closest('.question-block-card__actions')) return;
      toggleEdit();
    });

    deleteBtn.addEventListener('click', () => {
      if (!confirm(window.transConfirmQuestionDelete || 'Frage wirklich löschen?')) return;
      const idx = card.dataset.index;
      if (idx !== undefined) {
        undoStack.push(JSON.parse(JSON.stringify(initial)));
        apiFetch('/kataloge/' + catalogFile + '/' + idx, { method: 'DELETE' })
          .then(r => {
            if (!r.ok) throw new Error(r.statusText);
            initial.splice(Number(idx), 1);
            renderAll(initial);
            saveQuestions(initial, true);
          })
          .catch(err => {
            console.error(err);
            notify(window.transErrorDeleteFailed || 'Delete failed', 'danger');
          });
      } else {
        card.remove();
        saveQuestions();
      }
    });

    dupBtn.addEventListener('click', () => {
      const data = collectSingle(card);
      if (!data) return;
      const clone = createCard(data, -1);
      card.after(clone);
      saveQuestions();
    });

    // ── updateInfo ────────────────────────────────────────────────────────
    function updateInfo() {
      typeInfo.textContent = (infoMap[typeSelect.value] || '') + ' ' + (window.transQuizInfoSoftHyphen || 'For small displays you can use "/-" as a hidden soft hyphen.');
    }
    updateInfo();

    // ── updateSummary ─────────────────────────────────────────────────────
    function updateSummary() {
      const t = typeSelect.value;
      typeBadge.className = 'question-block-card__icon question-block-card__icon--' + t;
      typeBadge.textContent = abbrMap[t] || '?';
      titleEl.textContent = prompt.value.trim() || (window.transNewQuestion || 'New question');
      const pts = getPointsValue(card, t);
      const ptsLabel = t === 'flip'
        ? (window.transNoScoring || 'No scoring')
        : (pts === 1 ? (window.transOnePoint || '1 point') : `${pts} ${window.transPoints || 'points'}`);
      let itemCount = '';
      if (t === 'sort') {
        const n = fields.querySelectorAll('.item-row').length;
        itemCount = n > 0 ? ` \u00b7 ${n} ${window.transEntriesAbbr || 'entries'}` : '';
      } else if (t === 'assign') {
        const n = fields.querySelectorAll('.term-row').length;
        itemCount = n > 0 ? ` \u00b7 ${n} ${window.transPairs || 'pairs'}` : '';
      } else if (t === 'mc') {
        const n = fields.querySelectorAll('.option-row').length;
        itemCount = n > 0 ? ` \u00b7 ${n} ${window.transOptionsAbbr || 'opts.'}` : '';
      } else if (t === 'swipe') {
        const n = fields.querySelectorAll('.card-row').length;
        itemCount = n > 0 ? ` \u00b7 ${n} ${window.transCards || 'cards'}` : '';
      }
      metaEl.textContent = (labelMap[t] || t) + ' \u00b7 ' + ptsLabel + itemCount;
    }
    updateSummary();

    // ── updatePointsState ─────────────────────────────────────────────────
    function updatePointsState() {
      const scorable = typeSelect.value !== 'flip';
      if (!scorable) {
        const parsed = parseQuestionPoints(pointsInput.value);
        if (parsed !== null) { lastScorablePoints = parsed; }
        pointsInput.value = '0';
        pointsInput.disabled = true;
        pointsMeta.textContent = window.transFlipNoScoring || 'This question type does not award points.';
      } else {
        pointsInput.disabled = false;
        const parsed = parseQuestionPoints(pointsInput.value);
        const fallback = Number.isFinite(lastScorablePoints) ? lastScorablePoints : 1;
        const value = parsed === null ? fallback : parsed;
        const normalized = normalizeQuestionPoints(value, true);
        pointsInput.value = String(normalized);
        lastScorablePoints = normalized;
        pointsMeta.textContent = window.transPointsHint || 'Points per question (0\u201310000). Empty defaults to 1 point.';
      }
    }

    pointsInput.addEventListener('input', () => {
      if (typeSelect.value !== 'flip') {
        const parsed = parseQuestionPoints(pointsInput.value);
        if (parsed !== null) {
          const normalized = normalizeQuestionPoints(parsed, true);
          if (String(normalized) !== pointsInput.value) { pointsInput.value = String(normalized); }
          lastScorablePoints = normalized;
        }
      }
      updatePreview();
      updateSummary();
    });

    pointsInput.addEventListener('blur', () => {
      if (typeSelect.value === 'flip') { return; }
      const parsed = parseQuestionPoints(pointsInput.value);
      const fallback = Number.isFinite(lastScorablePoints) ? lastScorablePoints : 1;
      const value = parsed === null ? fallback : parsed;
      const normalized = normalizeQuestionPoints(value, true);
      pointsInput.value = String(normalized);
      lastScorablePoints = normalized;
      updatePreview();
      updateSummary();
    });

    prompt.addEventListener('input', () => { updateSummary(); updatePreview(); });

    // ── Helper functions for type-specific fields ─────────────────────────
    function addItem(value = '') {
      const div = document.createElement('div');
      div.className = 'uk-flex uk-margin-small-bottom item-row';
      const input = document.createElement('input');
      input.className = 'uk-input item';
      input.type = 'text';
      input.value = value;
      input.setAttribute('aria-label', window.transItem || 'Item');
      const btn = document.createElement('button');
      btn.className = 'uk-icon-button uk-button-danger uk-button-small uk-margin-left';
      btn.setAttribute('uk-icon', 'trash');
      btn.setAttribute('aria-label', window.transRemove || 'Remove');
      btn.type = 'button';
      btn.onclick = () => { div.remove(); saveQuestions(); };
      div.appendChild(input);
      div.appendChild(btn);
      return div;
    }

    function addPair(term = '', def = '') {
      const row = document.createElement('div');
      row.className = 'uk-grid-small uk-margin-small-bottom term-row';
      row.setAttribute('uk-grid', '');
      const tInput = document.createElement('input');
      tInput.className = 'uk-input term';
      tInput.type = 'text';
      tInput.placeholder = window.transTerm || 'Term';
      tInput.value = term;
      tInput.setAttribute('aria-label', window.transTerm || 'Term');
      const dInput = document.createElement('input');
      dInput.className = 'uk-input definition';
      dInput.type = 'text';
      dInput.placeholder = window.transDefinition || 'Definition';
      dInput.value = def;
      dInput.setAttribute('aria-label', window.transDefinition || 'Definition');
      const rem = document.createElement('button');
      rem.className = 'uk-icon-button uk-button-danger uk-button-small';
      rem.setAttribute('uk-icon', 'trash');
      rem.setAttribute('aria-label', window.transRemove || 'Remove');
      rem.type = 'button';
      rem.onclick = () => { row.remove(); saveQuestions(); };
      const tDiv = document.createElement('div');
      tDiv.appendChild(tInput);
      const dDiv = document.createElement('div');
      dDiv.appendChild(dInput);
      const bDiv = document.createElement('div');
      bDiv.className = 'uk-width-auto';
      bDiv.appendChild(rem);
      row.appendChild(tDiv);
      row.appendChild(dDiv);
      row.appendChild(bDiv);
      return row;
    }

    function addOption(text = '', checked = false) {
      const row = document.createElement('div');
      row.className = 'uk-flex uk-flex-middle uk-margin-small-bottom option-row';
      const cbId = 'cb-' + Math.random().toString(36).slice(2, 8);
      const cbLabel = document.createElement('label');
      cbLabel.className = 'uk-flex uk-flex-middle uk-margin-small-right';
      cbLabel.setAttribute('for', cbId);
      cbLabel.style.gap = '4px';
      cbLabel.style.whiteSpace = 'nowrap';
      const radio = document.createElement('input');
      radio.type = 'checkbox';
      radio.className = 'uk-checkbox answer';
      radio.name = 'ans' + cardIndex;
      radio.checked = checked;
      radio.id = cbId;
      const cbText = document.createElement('span');
      cbText.className = 'uk-text-meta';
      cbText.style.fontSize = '0.75rem';
      cbText.textContent = window.transCorrect || 'Correct';
      cbLabel.appendChild(radio);
      cbLabel.appendChild(cbText);
      const input = document.createElement('input');
      input.className = 'uk-input option uk-margin-small-left';
      input.type = 'text';
      input.value = text;
      input.setAttribute('aria-label', window.transAnswerText || 'Answer text');
      const rem = document.createElement('button');
      rem.className = 'uk-icon-button uk-button-danger uk-button-small uk-margin-left';
      rem.setAttribute('uk-icon', 'trash');
      rem.setAttribute('aria-label', window.transRemove || 'Remove');
      rem.type = 'button';
      rem.onclick = () => { row.remove(); saveQuestions(); };
      row.appendChild(cbLabel);
      row.appendChild(input);
      row.appendChild(rem);
      return row;
    }

    function addCard(text = '', correct = false) {
      const row = document.createElement('div');
      row.className = 'swipe-card-row card-row';
      const input = document.createElement('input');
      input.className = 'uk-input card-text';
      input.type = 'text';
      input.value = text;
      input.placeholder = window.transCardText || 'Card text';
      input.setAttribute('aria-label', window.transCardText || 'Card text');
      const checkId = 'cc-' + Math.random().toString(36).slice(2, 8);
      const checkLabel = document.createElement('label');
      checkLabel.className = 'swipe-card-row__label';
      checkLabel.setAttribute('for', checkId);
      checkLabel.setAttribute('title', window.transSwipeRightCorrect || '\u2192 Swipe right = correct answer');
      const check = document.createElement('input');
      check.type = 'checkbox';
      check.className = 'uk-checkbox card-correct';
      check.checked = correct;
      check.id = checkId;
      const checkSpan = document.createElement('span');
      checkSpan.textContent = '\u2192';
      checkLabel.appendChild(checkSpan);
      checkLabel.appendChild(check);
      const rem = document.createElement('button');
      rem.className = 'uk-icon-button uk-button-danger uk-button-small';
      rem.setAttribute('uk-icon', 'trash');
      rem.setAttribute('aria-label', window.transRemove || 'Remove');
      rem.type = 'button';
      rem.onclick = () => { row.remove(); saveQuestions(); };
      row.appendChild(input);
      row.appendChild(checkLabel);
      row.appendChild(rem);
      return row;
    }

    // ── renderFields ──────────────────────────────────────────────────────
    function renderFields() {
      fields.innerHTML = '';
      if (typeSelect.value === 'sort') {
        const list = document.createElement('div');
        (q.items || ['', '']).forEach(it => list.appendChild(addItem(it)));
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute('uk-icon', 'plus');
        add.setAttribute('aria-label', window.transAddItem || 'Add item');
        add.type = 'button';
        add.onclick = e => { e.preventDefault(); list.appendChild(addItem('')); };
        const hint = document.createElement('p');
        hint.className = 'uk-text-meta uk-margin-small-top';
        hint.textContent = window.transSortHint || 'Enter items in the correct order \u2013 they will be shuffled for the player.';
        fields.appendChild(list);
        fields.appendChild(add);
        fields.appendChild(hint);
      } else if (typeSelect.value === 'assign') {
        const header = document.createElement('div');
        header.className = 'assign-column-header';
        const hBegriff = document.createElement('span'); hBegriff.textContent = window.transTerm || 'Term';
        const hDef = document.createElement('span'); hDef.textContent = window.transDefinition || 'Definition';
        const hDel = document.createElement('span');
        header.appendChild(hBegriff); header.appendChild(hDef); header.appendChild(hDel);
        const list = document.createElement('div');
        (q.terms || [{ term: '', definition: '' }]).forEach(p => list.appendChild(addPair(p.term, p.definition)));
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute('uk-icon', 'plus');
        add.setAttribute('aria-label', window.transAddTerm || 'Add term');
        add.type = 'button';
        add.onclick = e => { e.preventDefault(); list.appendChild(addPair('', '')); };
        fields.appendChild(header);
        fields.appendChild(list);
        fields.appendChild(add);
      } else if (typeSelect.value === 'swipe') {
        const right = document.createElement('input');
        right.className = 'uk-input uk-margin-small-bottom right-label';
        right.type = 'text';
        right.placeholder = window.transSwipeRightPlaceholder || 'Label right (\u27A1, e.g. Yes)';
        right.style.borderColor = 'green';
        right.value = q.rightLabel || '';
        right.setAttribute('aria-label', window.transSwipeRightLabel || 'Label for swipe right');
        right.setAttribute('uk-tooltip', 'title: ' + (window.transSwipeRightTooltip || 'Text shown when swiping right.') + '; pos: right');
        const left = document.createElement('input');
        left.className = 'uk-input uk-margin-small-bottom left-label';
        left.type = 'text';
        left.placeholder = window.transSwipeLeftPlaceholder || 'Label left (\u2B05, e.g. No)';
        left.style.borderColor = 'red';
        left.value = q.leftLabel || '';
        left.setAttribute('aria-label', window.transSwipeLeftLabel || 'Label for swipe left');
        left.setAttribute('uk-tooltip', 'title: ' + (window.transSwipeLeftTooltip || 'Text shown when swiping left.') + '; pos: right');
        fields.appendChild(right);
        fields.appendChild(left);
        const header = document.createElement('div');
        header.className = 'swipe-card-header';
        const hText = document.createElement('span'); hText.textContent = window.transCardText || 'Card text';
        const hCorrect = document.createElement('span');
        hCorrect.textContent = window.transSwipeCorrect || '\u2192 Correct';
        hCorrect.setAttribute('title', window.transSwipeRightCorrect || '\u2192 Swipe right = correct answer');
        const hDel = document.createElement('span');
        header.appendChild(hText); header.appendChild(hCorrect); header.appendChild(hDel);
        const list = document.createElement('div');
        (q.cards || [{ text: '', correct: false }]).forEach(c => list.appendChild(addCard(c.text, c.correct)));
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute('uk-icon', 'plus');
        add.setAttribute('aria-label', window.transAddCard || 'Add card');
        add.type = 'button';
        add.onclick = e => { e.preventDefault(); list.appendChild(addCard('', false)); };
        fields.appendChild(header);
        fields.appendChild(list);
        fields.appendChild(add);
      } else if (typeSelect.value === 'flip') {
        const ans = document.createElement('textarea');
        ans.className = 'uk-textarea uk-margin-small-bottom flip-answer';
        ans.placeholder = window.transAnswer || 'Answer';
        ans.value = q.answer || '';
        ans.setAttribute('aria-label', window.transAnswer || 'Answer');
        fields.appendChild(ans);
      } else if (typeSelect.value === 'photoText') {
        const consent = document.createElement('label');
        consent.className = 'uk-margin-small-bottom';
        consent.innerHTML = '<input type="checkbox" class="uk-checkbox consent-box"> ' + (window.transShowPrivacyCheckbox || 'Show privacy checkbox');
        const chk = consent.querySelector('input');
        if (q.consent) chk.checked = true;
        fields.appendChild(consent);
      } else {
        // mc
        const list = document.createElement('div');
        (q.options || ['', '']).forEach((opt, i) => list.appendChild(addOption(opt, (q.answers || []).includes(i))));
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute('uk-icon', 'plus');
        add.setAttribute('aria-label', window.transAddOption || 'Add option');
        add.type = 'button';
        add.onclick = e => { e.preventDefault(); list.appendChild(addOption('')); };
        fields.appendChild(list);
        fields.appendChild(add);
      }
    }

    renderFields();
    updatePointsState();

    function updatePreview() {
      preview.innerHTML = '';
      const countdownValue = parseCountdownValue(countdownInput.value);
      const effectiveCountdown = countdownEnabled
        ? (countdownValue !== null ? countdownValue : defaultCountdown)
        : null;
      if (countdownEnabled) {
        if (effectiveCountdown !== null && effectiveCountdown > 0) {
          const timer = document.createElement('div');
          timer.className = 'question-timer uk-margin-small-bottom';
          const timerLabel = document.createElement('span');
          timerLabel.className = 'question-timer__label';
          timerLabel.textContent = (window.transTimeLimit || 'Time limit') + ':';
          const timerValue = document.createElement('span');
          timerValue.className = 'question-timer__value';
          timerValue.textContent = `${effectiveCountdown}s`;
          timer.appendChild(timerLabel);
          timer.appendChild(timerValue);
          preview.appendChild(timer);
        } else if (countdownValue === 0) {
          const noTimer = document.createElement('div');
          noTimer.className = 'uk-text-meta uk-margin-small-bottom';
          noTimer.textContent = window.transNoTimerForQuestion || 'No timer for this question.';
          preview.appendChild(noTimer);
        }
      }
      const scorable = typeSelect.value !== 'flip';
      const pointsValue = getPointsValue(card, typeSelect.value);
      const pointsInfo = document.createElement('div');
      pointsInfo.className = 'uk-text-meta uk-margin-small-bottom';
      if (scorable) {
        pointsInfo.textContent = pointsValue === 1 ? (window.transOnePoint || '1 point') : `${pointsValue} ${window.transPoints || 'points'}`;
      } else {
        pointsInfo.textContent = window.transNoScoring || 'No scoring';
      }
      preview.appendChild(pointsInfo);
      const h = document.createElement('h4');
      h.textContent = insertSoftHyphens(prompt.value || (window.transPreview || 'Preview'));
      preview.appendChild(h);
      if (typeSelect.value === 'sort') {
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.item')).forEach(i => {
          const li = document.createElement('li');
          li.textContent = insertSoftHyphens(i.value);
          ul.appendChild(li);
        });
        preview.appendChild(ul);
      } else if (typeSelect.value === 'assign') {
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.term-row')).forEach(r => {
          const term = r.querySelector('.term').value;
          const def = r.querySelector('.definition').value;
          const li = document.createElement('li');
          li.textContent = insertSoftHyphens(term) + ' – ' + insertSoftHyphens(def);
          ul.appendChild(li);
        });
        preview.appendChild(ul);
      } else if (typeSelect.value === 'swipe') {
        const container = document.createElement('div');
        container.style.position = 'relative';
        container.style.height = '200px';
        container.style.userSelect = 'none';
        container.style.touchAction = 'none';

        const leftLabel = fields.querySelector('.left-label')?.value || 'Nein';
        const rightLabel = fields.querySelector('.right-label')?.value || 'Ja';

        const leftStatic = document.createElement('div');
        leftStatic.textContent = '⬅ ' + insertSoftHyphens(leftLabel);
        leftStatic.style.position = 'absolute';
        leftStatic.style.left = '0';
        leftStatic.style.top = '50%';
        leftStatic.style.transform = 'translate(-50%, -50%) rotate(180deg)';
        leftStatic.style.writingMode = 'vertical-rl';
        leftStatic.style.pointerEvents = 'none';
        leftStatic.style.color = 'red';
        leftStatic.style.zIndex = '10';
        container.appendChild(leftStatic);

        const rightStatic = document.createElement('div');
        rightStatic.textContent = insertSoftHyphens(rightLabel) + ' ➡';
        rightStatic.style.position = 'absolute';
        rightStatic.style.right = '0';
        rightStatic.style.top = '50%';
        rightStatic.style.transform = 'translate(50%, -50%)';
        rightStatic.style.writingMode = 'vertical-rl';
        rightStatic.style.pointerEvents = 'none';
        rightStatic.style.color = 'green';
        rightStatic.style.zIndex = '10';
        container.appendChild(rightStatic);

        const label = document.createElement('div');
        label.style.position = 'absolute';
        label.style.top = '8px';
        label.style.left = '8px';
        label.style.fontWeight = 'bold';
        label.style.pointerEvents = 'none';
        container.appendChild(label);

        let cards = Array.from(fields.querySelectorAll('.card-row')).map(r => ({
          text: r.querySelector('.card-text').value
        }));

        let startX = 0, startY = 0, offsetX = 0, offsetY = 0, dragging = false;

        function render() {
          container.querySelectorAll('.swipe-card').forEach(el => el.remove());
          cards.forEach((c, i) => {
            const card = document.createElement('div');
            card.className = 'swipe-card';
            card.style.position = 'absolute';
            card.style.left = '2rem';
            card.style.right = '2rem';
            card.style.top = '0';
            card.style.bottom = '0';
            card.style.background = 'white';
            card.style.borderRadius = '8px';
            card.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
            card.style.display = 'flex';
            card.style.alignItems = 'center';
            card.style.justifyContent = 'center';
            card.style.padding = '1rem';
            card.style.transition = 'transform 0.3s';
            const off = (cards.length - i - 1) * 4;
            card.style.transform = `translate(0,-${off}px)`;
            card.style.zIndex = i;
            card.textContent = insertSoftHyphens(c.text);
            if (i === cards.length - 1) {
              card.addEventListener('pointerdown', start);
              card.addEventListener('pointermove', move);
              card.addEventListener('pointerup', end);
              card.addEventListener('pointercancel', end);
            }
            container.appendChild(card);
          });
        }

        function point(e) { return { x: e.clientX, y: e.clientY }; }

        function start(e) {
          if (!cards.length) return;
          const p = point(e);
          startX = p.x; startY = p.y;
          dragging = true;
          offsetX = 0; offsetY = 0;
        }

        function move(e) {
          if (!dragging) return;
          const p = point(e);
          offsetX = p.x - startX;
          offsetY = p.y - startY;
          const card = container.querySelector('.swipe-card:last-child');
          if (card) {
            const rot = offsetX / 10;
            card.style.transform = `translate(${offsetX}px,${offsetY}px) rotate(${rot}deg)`;
          }
          label.textContent = offsetX >= 0
            ? '➡ ' + insertSoftHyphens(rightLabel)
            : '⬅ ' + insertSoftHyphens(leftLabel);
          label.style.color = offsetX >= 0 ? 'green' : 'red';
          e.preventDefault();
        }

        function end() {
          if (!dragging) return;
          dragging = false;
          const cardEl = container.querySelector('.swipe-card:last-child');
          const threshold = 80;
          if (Math.abs(offsetX) > threshold) {
            if (cardEl) {
              cardEl.style.transform = `translate(${offsetX > 0 ? 1000 : -1000}px,${offsetY}px)`;
            }
            setTimeout(() => {
              cards.pop();
              offsetX = offsetY = 0;
              label.textContent = '';
              render();
            }, 300);
          } else {
            if (cardEl) {
              cardEl.style.transform = 'translate(0,0)';
            }
            offsetX = offsetY = 0;
            label.textContent = '';
          }
        }

        render();
        preview.appendChild(container);
      } else if (typeSelect.value === 'flip') {
        const flipContainer = document.createElement('div');
        flipContainer.style.perspective = '600px';
        flipContainer.style.height = '120px';
        const flipCard = document.createElement('div');
        flipCard.style.width = '100%';
        flipCard.style.height = '100%';
        flipCard.style.cursor = 'pointer';
        flipCard.style.position = 'relative';
        const flipFront = document.createElement('div');
        flipFront.style.cssText = 'display:flex;align-items:center;justify-content:center;padding:1rem;height:100%;background:#f8f9fa;border-radius:8px;box-sizing:border-box;';
        flipFront.textContent = insertSoftHyphens(prompt.value || (window.transPreviewQuestion || 'Question'));
        const flipBack = document.createElement('div');
        flipBack.style.cssText = 'display:none;align-items:center;justify-content:center;padding:1rem;height:100%;background:var(--brand-primary,#1e87f0);color:#fff;border-radius:8px;box-sizing:border-box;';
        const ans = fields.querySelector('.flip-answer');
        flipBack.textContent = insertSoftHyphens(ans ? ans.value : (window.transPreviewAnswer || 'Answer'));
        flipCard.appendChild(flipFront);
        flipCard.appendChild(flipBack);
        flipContainer.appendChild(flipCard);
        let flipped = false;
        flipCard.addEventListener('click', () => {
          flipped = !flipped;
          flipFront.style.display = flipped ? 'none' : 'flex';
          flipBack.style.display = flipped ? 'flex' : 'none';
        });
        const flipHintPrev = document.createElement('p');
        flipHintPrev.className = 'uk-text-meta';
        flipHintPrev.style.fontSize = '0.8rem';
        flipHintPrev.textContent = window.transPreviewClickToReveal || 'Click to reveal';
        preview.appendChild(flipContainer);
        preview.appendChild(flipHintPrev);
      } else if (typeSelect.value === 'photoText') {
        const photoMock = document.createElement('div');
        photoMock.style.display = 'flex';
        photoMock.style.flexDirection = 'column';
        photoMock.style.gap = '0.5rem';
        const photoBtn = document.createElement('button');
        photoBtn.type = 'button';
        photoBtn.className = 'uk-button uk-button-default';
        photoBtn.disabled = true;
        photoBtn.textContent = window.transPreviewTakePhoto || '\uD83D\uDCF7 Take photo';
        const textInput = document.createElement('input');
        textInput.type = 'text';
        textInput.className = 'uk-input';
        textInput.disabled = true;
        textInput.placeholder = window.transAnswerInputPlaceholder || 'Enter answer \u2026';
        photoMock.appendChild(photoBtn);
        photoMock.appendChild(textInput);
        preview.appendChild(photoMock);
      } else {
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.option-row')).forEach(r => {
          const input = r.querySelector('.option');
          const check = r.querySelector('.answer').checked;
          const li = document.createElement('li');
          li.textContent = insertSoftHyphens(input.value) + (check ? ' ✓' : '');
          if (check) li.classList.add('uk-text-success');
          ul.appendChild(li);
        });
        preview.appendChild(ul);
      }
    }

    fields.addEventListener('input', () => { updatePreview(); updateSummary(); });
    countdownInput.addEventListener('input', updatePreview);
    countdownInput.addEventListener('change', updatePreview);
    updatePreview();

    cardIndex++;
    return card;
  }

  // Sammelt alle Eingaben aus den Karten in ein Array von Fragen
  function getCountdownValue(card) {
    const input = card.querySelector('.countdown-input');
    if (!input) return null;
    return parseCountdownValue(input.value);
  }

  function clampQuestionPoints(value) {
    if (!Number.isFinite(value)) {
      return 0;
    }
    if (value < 0) {
      return 0;
    }
    if (value > 10000) {
      return 10000;
    }
    return Math.round(value);
  }

  function parseQuestionPoints(raw) {
    if (typeof raw === 'number' && Number.isFinite(raw)) {
      return clampQuestionPoints(raw);
    }
    if (typeof raw === 'string') {
      const trimmed = raw.trim();
      if (trimmed === '') {
        return null;
      }
      const parsed = Number.parseInt(trimmed, 10);
      if (Number.isNaN(parsed)) {
        return null;
      }
      return clampQuestionPoints(parsed);
    }
    return null;
  }

  function normalizeQuestionPoints(raw, scorable) {
    if (!scorable) {
      return 0;
    }
    const parsed = parseQuestionPoints(raw);
    if (parsed === null) {
      return 1;
    }
    return parsed;
  }

  function getPointsValue(card, type) {
    const scorable = type !== 'flip';
    const input = card.querySelector('.points-input');
    if (!input) {
      return normalizeQuestionPoints(null, scorable);
    }
    return normalizeQuestionPoints(input.value, scorable);
  }

  function collectSingle(card) {
    const type = card.querySelector('.type-select').value;
    const prompt = card.querySelector('.prompt').value.trim();
    const obj = { type, prompt };
    const countdown = getCountdownValue(card);
    if (countdown !== null) obj.countdown = countdown;
    obj.points = getPointsValue(card, type);
    if (type === 'sort') {
      obj.items = Array.from(card.querySelectorAll('.item-row .item')).map(i => i.value.trim()).filter(Boolean);
    } else if (type === 'assign') {
      obj.terms = Array.from(card.querySelectorAll('.term-row')).map(r => ({
        term: r.querySelector('.term').value.trim(),
        definition: r.querySelector('.definition').value.trim()
      })).filter(t => t.term || t.definition);
    } else if (type === 'swipe') {
      obj.cards = Array.from(card.querySelectorAll('.card-row')).map(r => ({
        text: r.querySelector('.card-text').value.trim(),
        correct: r.querySelector('.card-correct').checked
      })).filter(c => c.text);
      const rl = card.querySelector('.right-label');
      const ll = card.querySelector('.left-label');
      if (rl && rl.value.trim()) obj.rightLabel = rl.value.trim();
      if (ll && ll.value.trim()) obj.leftLabel = ll.value.trim();
    } else if (type === 'flip') {
      const ans = card.querySelector('.flip-answer');
      obj.answer = ans ? ans.value.trim() : '';
    } else if (type === 'photoText') {
      const chk = card.querySelector('.consent-box');
      obj.consent = chk ? chk.checked : false;
    } else {
      obj.options = Array.from(card.querySelectorAll('.option-row .option')).map(i => i.value.trim()).filter(Boolean);
      const checks = Array.from(card.querySelectorAll('.option-row .answer'));
      obj.answers = checks.map((c, i) => (c.checked ? i : -1)).filter(i => i >= 0);
    }
    return obj;
  }

  function collect() {
    return Array.from(container.querySelectorAll('.question-card')).map(card => {
      const type = card.querySelector('.type-select').value;
      const prompt = card.querySelector('.prompt').value.trim();
      if (type === 'sort') {
        const items = Array.from(card.querySelectorAll('.item-row .item'))
          .map(i => i.value.trim())
          .filter(Boolean);
        const obj = { type, prompt, items };
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      } else if (type === 'assign') {
        const terms = Array.from(card.querySelectorAll('.term-row')).map(r => ({
          term: r.querySelector('.term').value.trim(),
          definition: r.querySelector('.definition').value.trim()
        })).filter(t => t.term || t.definition);
        const obj = { type, prompt, terms };
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      } else if (type === 'swipe') {
        const cards = Array.from(card.querySelectorAll('.card-row')).map(r => ({
          text: r.querySelector('.card-text').value.trim(),
          correct: r.querySelector('.card-correct').checked
        })).filter(c => c.text);
        const rightLabel = card.querySelector('.right-label').value.trim();
        const leftLabel = card.querySelector('.left-label').value.trim();
        const obj = { type, prompt, cards };
        if (rightLabel) obj.rightLabel = rightLabel;
        if (leftLabel) obj.leftLabel = leftLabel;
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      } else if (type === 'flip') {
        const answer = card.querySelector('.flip-answer').value.trim();
        const obj = { type, prompt, answer };
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      } else if (type === 'photoText') {
        const consent = card.querySelector('.consent-box').checked;
        const obj = { type, prompt, consent };
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      } else {
        const options = Array.from(card.querySelectorAll('.option-row .option'))
          .map(i => i.value.trim())
          .filter(Boolean);
        const checks = Array.from(card.querySelectorAll('.option-row .answer'));
        const answers = checks
          .map((c, i) => (c.checked ? i : -1))
          .filter(i => i >= 0);
        const obj = { type, prompt, options, answers };
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      }
    });
  }

  // Speichert die Fragen automatisch auf dem Server
  let saveTimer;
  function saveQuestions(list, skipHistory = false) {
    if (!catalogFile) return;
    const data = list || collect();
    if (!skipHistory) {
      undoStack.push(JSON.parse(JSON.stringify(initial)));
      if (undoStack.length > 50) undoStack.shift();
    }
    initial = data;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      apiFetch('/kataloge/' + catalogFile, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
        .then(r => {
          if (!r.ok && r.status !== 400) {
            throw new Error(r.statusText);
          }
        })
        .catch(err => {
          console.error(err);
          notify(window.transErrorSaveFailed || 'Save failed', 'danger');
        });
    }, 300);
  }

  function undo() {
    const prev = undoStack.pop();
    if (prev) {
      renderAll(prev);
      saveQuestions(prev, true);
    }
  }

  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
      e.preventDefault();
      undo();
    }
  });

  // Fügt eine neue leere Frage hinzu
  if (addBtn && container) {
    addBtn.addEventListener('click', function (e) {
      e.preventDefault();
      container.appendChild(
        createCard({ type: 'mc', prompt: '', points: 1, options: ['', ''], answers: [0] }, -1)
      );
    });
  }

  if (newCatBtn) {
    newCatBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (!catalogManager) return;
      const id = crypto.randomUUID();
      const item = { id, slug: '', file: '', name: '', description: '', raetsel_buchstabe: '', comment: '', new: true };
      const list = catalogManager.getData();
      list.push(item);
      catalogManager.render(list);
      saveCatalogs(list, true);
      const cell = document.querySelector(`[data-id="${id}"][data-key="name"]`);
      if (cell) {
        catalogEditError.hidden = true;
        catalogEditor.open(cell);
      }
    });
  }


  const resultsResetBtn = document.getElementById('resultsResetBtn');
  const resultsDownloadBtn = document.getElementById('resultsDownloadBtn');
  const resultsPdfBtn = document.getElementById('resultsPdfBtn');

  resultsResetBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    resultsResetModal?.show();
  });

  resultsResetConfirm?.addEventListener('click', function () {
    apiFetch('/results', { method: 'DELETE' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify(window.transResultsCleared || 'Results cleared', 'success');
        resultsResetModal?.hide();
        window.location.reload();
      })
      .catch(err => {
        console.error(err);
        notify(window.transErrorDeleteFailed || 'Delete failed', 'danger');
      });
  });

  resultsDownloadBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    apiFetch('/results/download')
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        return r.blob();
      })
      .then(blob => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const name = (window.quizConfig && window.quizConfig.header) ? window.quizConfig.header : 'results';
        a.download = name + '.csv';
        a.click();
        URL.revokeObjectURL(url);
      })
      .catch(err => {
        console.error(err);
      notify(window.transErrorDownloadFailed || 'Download failed', 'danger');
    });
  });

  resultsPdfBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    window.open(withBase('/results.pdf'), '_blank');
  });

  // ── Public API ─────────────────────────────────────────────────────────

  return {
    loadCatalogs,
    applyCatalogList,
    saveCatalogs,
    get catalogs() { return catalogs; },
    get catalogManager() { return catalogManager; },
    catSelect,
  };
}
