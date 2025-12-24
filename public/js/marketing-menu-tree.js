/* global UIkit */

const container = document.querySelector('[data-marketing-menu-tree]');

if (container) {
  (() => {
    const treeRoot = container.querySelector('[data-menu-tree]');
    const feedback = container.querySelector('[data-menu-feedback]');
    const saveButton = container.querySelector('[data-menu-save-all]');
    const cancelButton = container.querySelector('[data-menu-cancel]');
    const addButton = container.querySelector('[data-menu-add]');
    const exportButton = container.querySelector('[data-menu-export]');
    const importButton = container.querySelector('[data-menu-import]');
    const importInput = container.querySelector('[data-menu-import-input]');
    const pageLabel = container.querySelector('[data-menu-page-label]');
    const pageSelect = document.getElementById('pageContentSelect');
    const localeSelect = document.getElementById('menuLocaleSelect');
    const previewTree = document.querySelector('[data-menu-preview-tree]');
    const previewEmpty = document.querySelector('[data-menu-preview-empty]');
    const previewSummary = document.querySelector('[data-menu-preview-summary]');

    const state = {
      pageId: null,
      pageSlug: '',
      items: [],
      byId: new Map(),
      originalItems: [],
      pages: [],
      dirty: new Set(),
      dragging: null,
      basePath: container.dataset.basePath || window.basePath || '',
      namespace: container.dataset.namespace || '',
      locale: container.dataset.locale || '',
    };

    const iconOptions = [
      '',
      'home',
      'info',
      'question',
      'calendar',
      'star',
      'bookmark',
      'file-text',
      'bolt',
      'heart',
      'mail',
      'location',
      'phone',
      'link',
      'world',
      'user',
      'users'
    ];

    const layoutOptions = [
      { value: 'link', label: 'Link' },
      { value: 'dropdown', label: 'Dropdown' },
      { value: 'mega', label: 'Mega' },
      { value: 'column', label: 'Spalte' }
    ];

    const allowedSchemes = ['http', 'https', 'mailto', 'tel'];

    const setFeedback = (message, status = 'primary') => {
      if (!feedback) {
        return;
      }
      feedback.textContent = message;
      feedback.classList.remove('uk-alert-primary', 'uk-alert-success', 'uk-alert-danger', 'uk-alert-warning');
      const cls = status === 'danger'
        ? 'uk-alert-danger'
        : status === 'success'
          ? 'uk-alert-success'
          : status === 'warning'
            ? 'uk-alert-warning'
            : 'uk-alert-primary';
      feedback.classList.add(cls);
      feedback.hidden = false;
    };

    const hideFeedback = () => {
      if (feedback) {
        feedback.hidden = true;
      }
    };

    const parsePages = () => {
      try {
        const parsed = JSON.parse(container.dataset.pages || '[]');
        state.pages = Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        console.warn('Failed to parse marketing pages', error);
        state.pages = [];
      }
    };

    const findPageBySlug = slug => state.pages.find(page => page.slug === slug) || null;

    const resolveCsrfToken = () =>
      document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.csrfToken || '';

    const normalizeId = value => {
      const numeric = Number(value);
      if (Number.isFinite(numeric)) {
        return numeric;
      }
      return String(value);
    };

    const apiFetch = (path, options = {}) => {
      if (typeof window.apiFetch === 'function') {
        return window.apiFetch(path, options);
      }
      const token = resolveCsrfToken();
      const headers = {
        ...(token ? { 'X-CSRF-Token': token } : {}),
        'X-Requested-With': 'fetch',
        ...(options.headers || {})
      };
      return fetch(path, {
        credentials: 'same-origin',
        cache: 'no-store',
        ...options,
        headers
      });
    };

    const appendQueryParam = (path, key, value) => {
      if (!value) {
        return path;
      }
      const separator = path.includes('?') ? '&' : '?';
      return `${path}${separator}${encodeURIComponent(key)}=${encodeURIComponent(value)}`;
    };

    const withNamespace = path => appendQueryParam(path, 'namespace', state.namespace);

    const buildPath = (pageId, suffix = '') => {
      const path = `/admin/pages/${pageId}${suffix}`;
      if (typeof window.apiFetch === 'function') {
        return path;
      }
      return `${state.basePath}${path}`;
    };

    const formatPageLabel = page => {
      const title = (page?.title || '').trim();
      if (title) {
        return title;
      }
      const slug = (page?.slug || '').trim();
      if (!slug) {
        return 'Neue Seite';
      }
      return slug
        .split('-')
        .filter(Boolean)
        .map(part => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
    };

    const setPageLabel = page => {
      if (!pageLabel) {
        return;
      }
      if (!page) {
        pageLabel.textContent = 'Keine Marketing-Seite vorhanden.';
        return;
      }
      pageLabel.textContent = `Seite: ${formatPageLabel(page)} (${page.slug})`;
    };

    const buildTree = items => {
      const byId = new Map();
      items.forEach(item => {
        if (item?.id === undefined || item?.id === null) {
          return;
        }
        const key = normalizeId(item.id);
        byId.set(key, { ...item, id: key, children: [] });
      });
      const roots = [];
      byId.forEach(node => {
        const parentKey = node.parentId === null || node.parentId === undefined
          ? null
          : normalizeId(node.parentId);
        if (parentKey !== null && byId.has(parentKey)) {
          byId.get(parentKey).children.push(node);
        } else {
          roots.push(node);
        }
      });
      const sortNodes = nodes => {
        nodes.sort((a, b) => {
          const posA = Number.isFinite(Number(a.position)) ? Number(a.position) : 0;
          const posB = Number.isFinite(Number(b.position)) ? Number(b.position) : 0;
          return posA - posB;
        });
        nodes.forEach(child => sortNodes(child.children));
      };
      sortNodes(roots);
      return { roots, byId };
    };

    const rebuildMaps = () => {
      state.byId = new Map();
      state.items.forEach(item => {
        if (item?.id !== undefined && item?.id !== null) {
          state.byId.set(normalizeId(item.id), item);
        }
      });
    };

    const formatHref = href => {
      if (!href) {
        return 'ohne Link';
      }
      if (href.length > 42) {
        return `${href.slice(0, 38)}…`;
      }
      return href;
    };

    const renderPreview = items => {
      if (!previewTree) {
        return;
      }

      const { roots } = buildTree(items);
      previewTree.innerHTML = '';

      const updateSummary = () => {
        if (!previewSummary) {
          return;
        }
        if (!state.pageId) {
          previewSummary.textContent = 'Bitte Marketing-Seite auswählen.';
          return;
        }
        const label = pageLabel?.textContent?.trim() || 'Aktuelle Navigation';
        previewSummary.textContent = label;
      };

      const createBadge = (text, className = 'uk-badge') => {
        const badge = document.createElement('span');
        badge.className = className;
        badge.textContent = text;
        return badge;
      };

      const renderBranch = (nodes, parent) => {
        const list = document.createElement('ul');
        list.className = parent ? 'uk-nav-sub uk-margin-remove-top' : 'uk-nav uk-nav-default';

        nodes.forEach(node => {
          const item = document.createElement('li');

          const line = document.createElement('div');
          line.className = 'uk-flex uk-flex-middle uk-flex-between';

          const left = document.createElement('div');
          left.className = 'uk-flex uk-flex-middle';
          left.style.gap = '8px';

          const label = document.createElement('span');
          label.className = 'uk-text-bold';
          label.textContent = node.label || 'Ohne Label';
          left.appendChild(label);

          if (node.isStartpage) {
            left.appendChild(createBadge('Startseite', 'uk-label uk-label-success'));
          }

          if (node.layout) {
            left.appendChild(createBadge(node.layout));
          }

          if (node.isExternal) {
            left.appendChild(createBadge('Extern', 'uk-label uk-label-warning'));
          }

          const href = document.createElement('span');
          href.className = 'uk-text-meta';
          href.textContent = formatHref(node.href || '');

          line.append(left, href);
          item.appendChild(line);

          if (node.children?.length) {
            item.appendChild(renderBranch(node.children, item));
          }

          list.appendChild(item);
        });

        return list;
      };

      if (!roots.length) {
        if (previewEmpty) {
          previewEmpty.hidden = false;
        }
        previewTree.appendChild(renderBranch([], null));
        updateSummary();
        return;
      }

      if (previewEmpty) {
        previewEmpty.hidden = true;
      }

      const tree = renderBranch(roots, null);
      previewTree.appendChild(tree);
      updateSummary();
    };

    const updateButtons = () => {
      const hasDirty = state.dirty.size > 0;
      if (saveButton) {
        saveButton.disabled = !hasDirty || !state.pageId;
      }
      if (cancelButton) {
        cancelButton.disabled = !hasDirty;
      }
    };

    const validateHref = href => {
      if (!href) {
        return 'Link ist erforderlich.';
      }
      if (href.startsWith('//')) {
        return 'Protocol-relative URLs sind nicht erlaubt.';
      }
      const schemeMatch = href.match(/^([a-z][a-z0-9+.-]*):/i);
      if (schemeMatch) {
        const scheme = schemeMatch[1].toLowerCase();
        if (!allowedSchemes.includes(scheme)) {
          return 'URL-Schema ist nicht erlaubt.';
        }
        return null;
      }
      const firstChar = href[0] || '';
      if (firstChar === '#' || firstChar === '?') {
        return null;
      }
      if (firstChar !== '/') {
        return 'Link muss relativ zur Basis beginnen.';
      }
      if (!state.basePath) {
        return null;
      }
      if (href === state.basePath || href === `${state.basePath}/`) {
        return null;
      }
      if (!href.startsWith(`${state.basePath}/`)) {
        return 'Link muss mit dem BasePath beginnen.';
      }
      return null;
    };

    const markDirty = id => {
      if (id === undefined || id === null) {
        return;
      }
      state.dirty.add(normalizeId(id));
      updateButtons();
    };

    const renderAdvancedFields = (node, containerEl) => {
      const advanced = document.createElement('div');
      advanced.className = 'menu-tree__advanced uk-margin-small-top';
      advanced.hidden = true;

      const layoutLabel = document.createElement('label');
      layoutLabel.className = 'uk-form-label';
      layoutLabel.textContent = 'Layout';
      const layoutSelect = document.createElement('select');
      layoutSelect.className = 'uk-select uk-form-small';
      layoutSelect.dataset.field = 'layout';
      layoutOptions.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.value;
        opt.textContent = option.label;
        if (option.value === (node.layout || 'link')) {
          opt.selected = true;
        }
        layoutSelect.appendChild(opt);
      });

      const iconLabel = document.createElement('label');
      iconLabel.className = 'uk-form-label';
      iconLabel.textContent = 'Icon';
      const iconSelect = document.createElement('select');
      iconSelect.className = 'uk-select uk-form-small';
      iconSelect.dataset.field = 'icon';
      iconOptions.forEach(icon => {
        const opt = document.createElement('option');
        opt.value = icon;
        opt.textContent = icon === '' ? 'Keins' : icon;
        if (icon === (node.icon || '')) {
          opt.selected = true;
        }
        iconSelect.appendChild(opt);
      });

      const localeLabel = document.createElement('label');
      localeLabel.className = 'uk-form-label';
      localeLabel.textContent = 'Locale';
      const localeInput = document.createElement('input');
      localeInput.className = 'uk-input uk-form-small';
      localeInput.type = 'text';
      localeInput.maxLength = 5;
      localeInput.dataset.field = 'locale';
      localeInput.value = node.locale || '';

      const togglesRow = document.createElement('div');
      togglesRow.className = 'uk-grid-small uk-child-width-auto uk-flex-middle uk-grid';
      const buildToggle = (labelText, field, checked) => {
        const wrapper = document.createElement('label');
        wrapper.className = 'uk-flex uk-flex-middle menu-tree__toggle';
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.className = 'uk-checkbox';
        input.dataset.field = field;
        input.checked = !!checked;
        const span = document.createElement('span');
        span.className = 'uk-margin-small-left';
        span.textContent = labelText;
        wrapper.append(input, span);
        return wrapper;
      };
      togglesRow.append(
        buildToggle('Startpage', 'isStartpage', node.isStartpage),
        buildToggle('Extern', 'isExternal', node.isExternal),
        buildToggle('Aktiv', 'isActive', node.isActive !== false)
      );

      const detailTitle = document.createElement('input');
      detailTitle.className = 'uk-input uk-form-small uk-margin-small-top';
      detailTitle.type = 'text';
      detailTitle.placeholder = 'SEO Titel';
      detailTitle.dataset.field = 'detailTitle';
      detailTitle.value = node.detailTitle || '';

      const detailText = document.createElement('textarea');
      detailText.className = 'uk-textarea uk-form-small uk-margin-small-top';
      detailText.rows = 2;
      detailText.placeholder = 'SEO Beschreibung';
      detailText.dataset.field = 'detailText';
      detailText.value = node.detailText || '';

      const detailSubline = document.createElement('input');
      detailSubline.className = 'uk-input uk-form-small uk-margin-small-top';
      detailSubline.type = 'text';
      detailSubline.placeholder = 'Subline';
      detailSubline.dataset.field = 'detailSubline';
      detailSubline.value = node.detailSubline || '';

      advanced.append(
        layoutLabel,
        layoutSelect,
        iconLabel,
        iconSelect,
        localeLabel,
        localeInput,
        togglesRow,
        detailTitle,
        detailText,
        detailSubline
      );
      containerEl.appendChild(advanced);
      return advanced;
    };

    const applyInputChange = (node, field, value) => {
      const key = normalizeId(node.id);
      const current = state.byId.get(key) || node;
      current[field] = value;
      state.byId.set(key, current);
      const itemIndex = state.items.findIndex(entry => normalizeId(entry.id) === key);
      if (itemIndex >= 0) {
        state.items[itemIndex] = { ...state.items[itemIndex], [field]: value };
      }
      markDirty(key);
    };

    const renderNode = (node, depth = 0) => {
      const safeId = String(normalizeId(node.id)).replace(/[^a-zA-Z0-9_-]/g, '-');
      const buildFieldId = suffix => `menu-node-${safeId}-${suffix}`;
      const item = document.createElement('div');
      item.className = 'menu-tree__item';
      item.dataset.id = node.id;
      item.dataset.parentId = node.parentId ?? '';
      item.style.setProperty('--menu-depth', String(depth));
      item.setAttribute('role', 'treeitem');
      item.setAttribute('aria-level', String(depth + 1));
      item.setAttribute('tabindex', '0');
      if (node.children?.length) {
        item.setAttribute('aria-expanded', 'true');
      }
      item.draggable = !!node.id;

      const row = document.createElement('div');
      row.className = 'menu-tree__row';

      const dragHandle = document.createElement('button');
      dragHandle.type = 'button';
      dragHandle.className = 'uk-icon-button menu-tree__drag';
      dragHandle.setAttribute('uk-icon', 'table');
      dragHandle.setAttribute('aria-label', 'Verschieben');
      dragHandle.setAttribute('aria-grabbed', 'false');
      dragHandle.tabIndex = 0;

      const labelFieldId = buildFieldId('label');
      const labelLabel = document.createElement('label');
      labelLabel.className = 'uk-form-label uk-text-small menu-tree__field-label';
      labelLabel.htmlFor = labelFieldId;
      labelLabel.textContent = 'Label';

      const labelInput = document.createElement('input');
      labelInput.type = 'text';
      labelInput.className = 'uk-input uk-form-small menu-tree__label-input';
      labelInput.value = node.label || '';
      labelInput.placeholder = 'Label';
      labelInput.dataset.field = 'label';
      labelInput.setAttribute('aria-label', 'Label bearbeiten');
      labelInput.id = labelFieldId;

      const hrefFieldId = buildFieldId('href');
      const hrefLabel = document.createElement('label');
      hrefLabel.className = 'uk-form-label uk-text-small menu-tree__field-label';
      hrefLabel.htmlFor = hrefFieldId;
      hrefLabel.textContent = 'Ziel / Link';
      const hrefInput = document.createElement('input');
      hrefInput.type = 'text';
      hrefInput.className = 'uk-input uk-form-small menu-tree__href-input';
      hrefInput.value = node.href || '';
      hrefInput.placeholder = '/pfad oder Link';
      hrefInput.dataset.field = 'href';
      hrefInput.setAttribute('aria-label', 'Link bearbeiten');
      hrefInput.id = hrefFieldId;

      const errorHint = document.createElement('div');
      errorHint.className = 'uk-text-small uk-text-danger';
      errorHint.hidden = true;

      const actions = document.createElement('div');
      actions.className = 'menu-tree__actions';

      const visibilityBtn = document.createElement('button');
      visibilityBtn.type = 'button';
      visibilityBtn.className = 'uk-icon-button';
      visibilityBtn.setAttribute('uk-icon', node.isActive === false ? 'ban' : 'eye');
      visibilityBtn.title = 'Sichtbarkeit umschalten';
      visibilityBtn.setAttribute('aria-label', 'Sichtbarkeit umschalten');
      visibilityBtn.setAttribute('aria-pressed', node.isActive !== false ? 'true' : 'false');
      actions.appendChild(visibilityBtn);

      const settingsBtn = document.createElement('button');
      settingsBtn.type = 'button';
      settingsBtn.className = 'uk-icon-button';
      settingsBtn.setAttribute('uk-icon', 'cog');
      settingsBtn.title = 'Erweitert anzeigen';
      settingsBtn.setAttribute('aria-label', 'Erweitert anzeigen');
      const advancedId = buildFieldId('advanced');
      settingsBtn.setAttribute('aria-expanded', 'false');
      settingsBtn.setAttribute('aria-controls', advancedId);
      actions.appendChild(settingsBtn);

      const baseWrapper = document.createElement('div');
      baseWrapper.className = 'menu-tree__base';
      baseWrapper.append(labelLabel, labelInput, hrefLabel, hrefInput, errorHint);

      row.append(dragHandle, baseWrapper, actions);
      item.appendChild(row);

      const advanced = renderAdvancedFields(node, item);
      advanced.id = advancedId;

      const childrenContainer = document.createElement('div');
      childrenContainer.className = 'menu-tree__branch';
      childrenContainer.dataset.parentId = node.id ?? '';
      childrenContainer.setAttribute('role', 'group');
      item.appendChild(childrenContainer);

      labelInput.addEventListener('input', event => {
        applyInputChange(node, 'label', event.target.value.trim());
      });

      hrefInput.addEventListener('input', event => {
        const value = event.target.value.trim();
        const error = validateHref(value);
        errorHint.hidden = !error;
        errorHint.textContent = error || '';
        applyInputChange(node, 'href', value);
      });

      settingsBtn.addEventListener('click', () => {
        advanced.hidden = !advanced.hidden;
        settingsBtn.setAttribute('aria-expanded', advanced.hidden ? 'false' : 'true');
      });

      visibilityBtn.addEventListener('click', () => {
        const next = !(state.byId.get(node.id)?.isActive === true);
        applyInputChange(node, 'isActive', next);
        visibilityBtn.setAttribute('uk-icon', next ? 'eye' : 'ban');
        visibilityBtn.setAttribute('aria-pressed', next ? 'true' : 'false');
        if (window.UIkit && typeof UIkit.icon === 'function') {
          UIkit.icon(visibilityBtn, { icon: next ? 'eye' : 'ban' });
        }
      });

      advanced.querySelectorAll('[data-field]').forEach(input => {
        input.addEventListener('change', event => {
          const field = event.target.dataset.field;
          let value = event.target.value;
          if (event.target.type === 'checkbox') {
            value = event.target.checked;
          }
          applyInputChange(node, field, value);
        });
      });

      return item;
    };

    const renderBranch = (nodes, depth = 0, branch) => {
      nodes.forEach(node => {
        const item = renderNode(node, depth);
        branch.appendChild(item);
        if (node.children?.length) {
          renderBranch(node.children, depth + 1, item.querySelector('.menu-tree__branch'));
        }
      });
    };

    const renderTree = () => {
      if (!treeRoot) {
        return;
      }
      treeRoot.innerHTML = '';
      if (!state.pageId) {
        const message = document.createElement('div');
        message.className = 'uk-text-meta';
        message.textContent = 'Bitte zuerst eine Marketing-Seite auswählen.';
        treeRoot.appendChild(message);
        return;
      }
      const tree = buildTree(state.items);
      renderBranch(tree.roots, 0, treeRoot);
      attachDragHandlers();
      updateButtons();
      renderPreview(state.items);
    };

    const updateSelectedPage = () => {
      const slug = pageSelect?.value || container.dataset.selectedSlug || '';
      const page = slug ? findPageBySlug(slug) : null;
      state.pageSlug = slug;
      state.pageId = page ? Number(page.id) : null;
      setPageLabel(page);
      addButton.disabled = !state.pageId;
      exportButton.disabled = !state.pageId;
      importButton.disabled = !state.pageId;
      state.dirty.clear();
      updateButtons();
      loadMenuItems(state.locale);
    };

    const loadMenuItems = locale => {
      if (!state.pageId) {
        renderTree();
        return;
      }
      hideFeedback();
      treeRoot.textContent = 'Lädt…';
      const path = appendQueryParam(withNamespace(buildPath(state.pageId, '/menu')), 'locale', locale);
      apiFetch(path)
        .then(response => {
          if (!response.ok) {
            throw new Error('menu-load-failed');
          }
          return response.json();
        })
        .then(data => {
          state.items = Array.isArray(data?.items) ? data.items.slice() : [];
          state.originalItems = JSON.parse(JSON.stringify(state.items));
          rebuildMaps();
          renderTree();
        })
        .catch(error => {
          console.error('Failed to load marketing menu', error);
          setFeedback('Menüeinträge konnten nicht geladen werden.', 'danger');
          treeRoot.textContent = 'Menüeinträge konnten nicht geladen werden.';
        });
    };

    const collectTreeOrder = () => {
      treeRoot.querySelectorAll('.menu-tree__branch').forEach(branch => {
        const parentValue = branch.dataset.parentId ?? '';
        const parentId = parentValue === '' ? null : normalizeId(parentValue);
        Array.from(branch.children).forEach((child, index) => {
          const key = normalizeId(child.dataset.id);
          const item = state.byId.get(key);
          if (item) {
            item.parentId = parentId;
            item.position = index;
            markDirty(key);
          }
        });
      });
    };

    const saveItem = item => {
      const payload = {
        id: Number.isFinite(Number(item.id)) ? Number(item.id) : null,
        label: item.label || '',
        href: item.href || '',
        icon: item.icon || null,
        parentId: item.parentId || null,
        layout: item.layout || 'link',
        detailTitle: item.detailTitle || null,
        detailText: item.detailText || null,
        detailSubline: item.detailSubline || null,
        position: Number.isFinite(item.position) ? item.position : 0,
        isExternal: item.isExternal === true,
        locale: item.locale || null,
        isActive: item.isActive !== false,
        isStartpage: item.isStartpage === true
      };

      return apiFetch(withNamespace(buildPath(state.pageId, '/menu')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(response => {
          if (!response.ok) {
            return response.json().catch(() => ({})).then(errData => {
              throw new Error(errData?.error || 'menu-save-failed');
            });
          }
          return response.json();
        })
        .then(data => {
          const saved = data?.item || payload;
          const newId = saved.id || item.id;
          state.byId.delete(normalizeId(item.id));
          state.byId.set(normalizeId(newId), { ...item, ...saved, id: normalizeId(newId) });
          state.dirty.delete(normalizeId(item.id));
          state.dirty.delete(normalizeId(newId));
        });
    };

    const saveAll = () => {
      collectTreeOrder();
      const dirtyItems = Array.from(state.dirty)
        .map(id => state.byId.get(id))
        .filter(Boolean);
      if (!dirtyItems.length) {
        hideFeedback();
        return;
      }
      setFeedback('Speichern…', 'primary');
      dirtyItems.reduce((promise, item) => promise.then(() => saveItem(item)), Promise.resolve())
        .then(() => {
          state.items = Array.from(state.byId.values());
          state.originalItems = JSON.parse(JSON.stringify(state.items));
          state.dirty.clear();
          renderTree();
          setFeedback('Änderungen gespeichert.', 'success');
        })
        .catch(error => {
          console.error('Failed to save marketing menu', error);
          setFeedback('Speichern fehlgeschlagen.', 'danger');
        });
    };

    const cancelChanges = () => {
      state.items = JSON.parse(JSON.stringify(state.originalItems));
      rebuildMaps();
      state.dirty.clear();
      renderTree();
      hideFeedback();
    };

    const addNode = () => {
      if (!state.pageId) {
        setFeedback('Bitte zuerst eine Marketing-Seite auswählen.', 'warning');
        return;
      }
      const tempId = `new-${Date.now()}`;
      const newItem = {
        id: tempId,
        label: 'Neuer Eintrag',
        href: `${state.basePath || ''}/`,
        parentId: null,
        position: state.items.length,
        layout: 'link',
        icon: '',
        isActive: true
      };
      state.items.push(newItem);
      state.byId.set(tempId, newItem);
      markDirty(tempId);
      renderTree();
    };

    const downloadMenu = () => {
      if (!state.pageId) {
        setFeedback('Bitte zuerst eine Marketing-Seite auswählen.', 'warning');
        return;
      }

      const path = appendQueryParam(withNamespace(buildPath(state.pageId, '/menu/export')), 'locale', state.locale);
      apiFetch(path, { headers: { Accept: 'application/json' } })
        .then(async response => {
          if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            const message = body?.error || 'Export fehlgeschlagen.';
            throw new Error(message);
          }
          const data = await response.json();
          const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
          const slug = state.pageSlug || 'menu';
          const namespace = state.namespace || 'default';
          const locale = state.locale || 'all';
          const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
          const filename = `marketing-menu-${slug}-${namespace}-${locale}-${timestamp}.json`;

          const url = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = filename;
          document.body.appendChild(link);
          link.click();
          link.remove();
          URL.revokeObjectURL(url);
          setFeedback('Menü exportiert.', 'success');
        })
        .catch(error => {
          console.error('Failed to export marketing menu', error);
          setFeedback(error.message || 'Export fehlgeschlagen.', 'danger');
        });
    };

    const submitImportPayload = payload => {
      if (!state.pageId) {
        setFeedback('Bitte zuerst eine Marketing-Seite auswählen.', 'warning');
        return;
      }
      apiFetch(withNamespace(buildPath(state.pageId, '/menu/import')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(async response => {
          if (!response.ok && response.status !== 204) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body?.error || 'Import fehlgeschlagen.');
          }
          return null;
        })
        .then(() => {
          setFeedback('Menü importiert.', 'success');
          loadMenuItems(state.locale);
        })
        .catch(error => {
          console.error('Failed to import marketing menu', error);
          setFeedback(error.message || 'Import fehlgeschlagen.', 'danger');
        });
    };

    const handleImportFile = file => {
      const reader = new FileReader();
      reader.onload = event => {
        try {
          const content = event?.target?.result || '';
          const payload = JSON.parse(content);
          submitImportPayload(payload);
        } catch (error) {
          console.error('Invalid marketing menu import file', error);
          setFeedback('Die Datei konnte nicht gelesen werden. Bitte gültiges JSON hochladen.', 'danger');
        } finally {
          if (importInput) {
            importInput.value = '';
          }
        }
      };
      reader.onerror = () => {
        setFeedback('Datei konnte nicht geladen werden.', 'danger');
        if (importInput) {
          importInput.value = '';
        }
      };
      reader.readAsText(file);
    };

    const attachDragHandlers = () => {
      let draggingElement = null;
      treeRoot.querySelectorAll('.menu-tree__item').forEach(item => {
        item.addEventListener('dragstart', event => {
          if (event.target !== item && !event.target.closest('.menu-tree__drag')) {
            event.preventDefault();
            return;
          }
          draggingElement = item;
          item.classList.add('is-dragging');
          dragHandle?.setAttribute('aria-grabbed', 'true');
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', item.dataset.id);
        });

        item.addEventListener('dragend', () => {
          if (draggingElement) {
            draggingElement.classList.remove('is-dragging');
            draggingElement = null;
            dragHandle?.setAttribute('aria-grabbed', 'false');
            collectTreeOrder();
          }
        });
      });

      treeRoot.querySelectorAll('.menu-tree__branch').forEach(branch => {
        branch.addEventListener('dragover', event => {
          if (!draggingElement) {
            return;
          }
          const draggingParent = draggingElement.dataset.parentId || '';
          if (draggingParent !== (branch.dataset.parentId || '')) {
            return;
          }
          event.preventDefault();
          const afterElement = Array.from(branch.children).find(child => {
            const box = child.getBoundingClientRect();
            return event.clientY < box.top + box.height / 2;
          });
          if (!afterElement) {
            branch.appendChild(draggingElement);
          } else if (afterElement !== draggingElement) {
            branch.insertBefore(draggingElement, afterElement);
          }
        });

        branch.addEventListener('drop', event => {
          event.preventDefault();
          collectTreeOrder();
        });
      });
    };

    saveButton?.addEventListener('click', saveAll);
    cancelButton?.addEventListener('click', cancelChanges);
    addButton?.addEventListener('click', addNode);

    exportButton?.addEventListener('click', downloadMenu);
    importButton?.addEventListener('click', () => {
      if (!state.pageId) {
        setFeedback('Bitte zuerst eine Marketing-Seite auswählen.', 'warning');
        return;
      }
      importInput?.click();
    });
    importInput?.addEventListener('change', event => {
      const file = event.target?.files?.[0];
      if (file) {
        handleImportFile(file);
      }
    });

    pageSelect?.addEventListener('change', updateSelectedPage);
    localeSelect?.addEventListener('change', () => {
      state.locale = localeSelect?.value || '';
      loadMenuItems(state.locale);
    });

    parsePages();
    updateSelectedPage();
  })();
}
