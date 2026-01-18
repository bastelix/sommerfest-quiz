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
    const menuSelect = document.getElementById('menuDefinitionSelect');
    const localeSelect = document.getElementById('menuLocaleSelect');
    const previewTree = document.querySelector('[data-menu-preview-tree]');
    const previewEmpty = document.querySelector('[data-menu-preview-empty]');
    const previewSummary = document.querySelector('[data-menu-preview-summary]');
    const variantSelect = document.querySelector('[data-menu-variant-select]');
    const generateButton = container.querySelector('[data-menu-generate-ai]');

    const normalizeBasePath = (candidate = '') => {
      const trimmed = String(candidate || '').trim();
      if (trimmed === '') {
        return '';
      }

      try {
        const parsed = new URL(trimmed, window.location.origin);
        if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
          return parsed.pathname.replace(/\/$/, '');
        }
      } catch (error) {
        // Fall back to raw value on parse errors
      }

      return trimmed.replace(/\/$/, '');
    };

    const parseVariantOptions = () => {
      const raw = container.dataset.navigationVariantOptions || '[]';
      try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        console.warn('Failed to parse navigation variant options', error);
        return [];
      }
    };

    const variantOptions = parseVariantOptions();
    const internalLinks = (() => {
      try {
        const parsed = JSON.parse(container.dataset.internalLinks || '[]');
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        console.warn('Failed to parse internal link options', error);
        return [];
      }
    })();

    const resolveSelectedVariant = () => {
      const requested = container.dataset.selectedNavigationVariant || variantSelect?.dataset.selected || '';
      if (!requested && variantOptions.length) {
        return variantOptions[0].value || '';
      }
      const found = variantOptions.find(option => option.value === requested);
      return found ? found.value : requested;
    };

    const state = {
      menuId: null,
      items: [],
      byId: new Map(),
      originalItems: [],
      menus: [],
      dirty: new Set(),
      dragging: null,
      basePath: normalizeBasePath(container.dataset.basePath || window.basePath || ''),
      namespace: container.dataset.namespace || '',
      locale: container.dataset.locale || '',
      variant: resolveSelectedVariant(),
      variantOptions,
    };

    const renderHrefOptions = options => {
      if (!options.length) {
        return null;
      }
      const list = document.createElement('datalist');
      list.id = `menu-tree-href-options-${Math.random().toString(36).slice(2, 9)}`;
      options.forEach(option => {
        const value = typeof option?.value === 'string' ? option.value.trim() : '';
        if (!value) {
          return;
        }
        const label = typeof option?.label === 'string' ? option.label.trim() : value;
        const group = typeof option?.group === 'string' ? option.group.trim() : '';
        const displayLabel = group && label && group !== label ? `${group}: ${label}` : label;
        const entry = document.createElement('option');
        entry.value = value;
        if (displayLabel && displayLabel !== value) {
          entry.label = displayLabel;
        }
        list.appendChild(entry);
      });
      return list;
    };

    const hrefOptionsList = renderHrefOptions(internalLinks);
    const hrefOptionsListId = hrefOptionsList?.id || '';
    if (hrefOptionsList) {
      container.appendChild(hrefOptionsList);
    }

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

    const parseMenus = () => {
      try {
        const parsed = JSON.parse(container.dataset.menus || '[]');
        state.menus = Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        console.warn('Failed to parse menus', error);
        state.menus = [];
      }
    };

    const findMenuById = id => state.menus.find(menu => Number(menu.id) === Number(id)) || null;

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

    const buildItemsPath = (menuId, suffix = '') => {
      const path = `/admin/menus/${menuId}/items${suffix}`;
      if (typeof window.apiFetch === 'function') {
        return path;
      }
      return `${state.basePath}${path}`;
    };

    const formatMenuLabel = menu => {
      const label = (menu?.label || '').trim();
      if (label) {
        return label;
      }
      return 'Neues Menü';
    };

    const setMenuLabel = menu => {
      if (!pageLabel) {
        return;
      }
      if (!menu) {
        pageLabel.textContent = 'Kein Menü ausgewählt.';
        return;
      }
      const locale = menu.locale ? ` (${menu.locale})` : '';
      pageLabel.textContent = `Menü: ${formatMenuLabel(menu)}${locale}`;
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
        if (!state.menuId) {
          previewSummary.textContent = 'Bitte Menü auswählen.';
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

      const renderHamburgerPreview = nodes => {
        const wrapper = document.createElement('div');
        wrapper.className = 'menu-preview__hamburger';

        const header = document.createElement('div');
        header.className = 'uk-flex uk-flex-middle uk-margin-small-bottom';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'uk-button uk-button-default uk-button-small';
        toggle.setAttribute('aria-label', 'Menü öffnen');
        toggle.textContent = '☰';

        header.appendChild(toggle);
        wrapper.appendChild(header);

        const panel = document.createElement('div');
        panel.className = 'uk-border-rounded uk-padding-small uk-background-muted';
        panel.appendChild(renderBranch(nodes, null));
        wrapper.appendChild(panel);

        return wrapper;
      };

      if (!roots.length) {
        if (previewEmpty) {
          previewEmpty.hidden = false;
        }
        previewTree.appendChild(renderHamburgerPreview([]));
        updateSummary();
        return;
      }

      if (previewEmpty) {
        previewEmpty.hidden = true;
      }

      previewTree.appendChild(renderHamburgerPreview(roots));
      updateSummary();
    };

    const updateButtons = () => {
      const hasDirty = state.dirty.size > 0;
      if (saveButton) {
        saveButton.disabled = !hasDirty || !state.menuId;
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

      const detailSectionLabel = document.createElement('div');
      detailSectionLabel.className = 'uk-text-meta uk-margin-small-top';
      detailSectionLabel.textContent = 'Mega-Menü Inhalte';

      const detailTitleLabel = document.createElement('label');
      detailTitleLabel.className = 'uk-form-label uk-text-small menu-tree__field-label';
      detailTitleLabel.textContent = 'Detail Titel';
      const detailTitle = document.createElement('input');
      detailTitle.className = 'uk-input uk-form-small';
      detailTitle.type = 'text';
      detailTitle.placeholder = 'SEO Titel';
      detailTitle.dataset.field = 'detailTitle';
      detailTitle.value = node.detailTitle || '';

      const detailTextLabel = document.createElement('label');
      detailTextLabel.className = 'uk-form-label uk-text-small menu-tree__field-label';
      detailTextLabel.textContent = 'Detail Beschreibung';
      const detailText = document.createElement('textarea');
      detailText.className = 'uk-textarea uk-form-small';
      detailText.rows = 2;
      detailText.placeholder = 'SEO Beschreibung';
      detailText.dataset.field = 'detailText';
      detailText.value = node.detailText || '';

      const detailSublineLabel = document.createElement('label');
      detailSublineLabel.className = 'uk-form-label uk-text-small menu-tree__field-label';
      detailSublineLabel.textContent = 'Detail Subline';
      const detailSubline = document.createElement('input');
      detailSubline.className = 'uk-input uk-form-small';
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
        detailSectionLabel,
        detailTitleLabel,
        detailTitle,
        detailTextLabel,
        detailText,
        detailSublineLabel,
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
      hrefInput.placeholder = '/pfad, #anker oder Link';
      hrefInput.dataset.field = 'href';
      hrefInput.setAttribute('aria-label', 'Link bearbeiten');
      hrefInput.id = hrefFieldId;
      if (hrefOptionsListId) {
        hrefInput.setAttribute('list', hrefOptionsListId);
      }

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

      const deleteBtn = document.createElement('button');
      deleteBtn.type = 'button';
      deleteBtn.className = 'uk-icon-button uk-button-danger';
      deleteBtn.setAttribute('uk-icon', 'trash');
      deleteBtn.title = 'Eintrag entfernen';
      deleteBtn.setAttribute('aria-label', 'Eintrag entfernen');
      actions.appendChild(deleteBtn);

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
        const current = state.byId.get(normalizeId(node.id))?.isActive !== false;
        const next = !current;
        applyInputChange(node, 'isActive', next);
        visibilityBtn.setAttribute('uk-icon', next ? 'eye' : 'ban');
        visibilityBtn.setAttribute('aria-pressed', next ? 'true' : 'false');
        const activeCheckbox = advanced.querySelector('input[data-field="isActive"]');
        if (activeCheckbox) {
          activeCheckbox.checked = next;
        }
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

      deleteBtn.addEventListener('click', () => deleteNode(node, deleteBtn));

      return item;
    };

    const collectDescendantIds = rootId => {
      const target = normalizeId(rootId);
      const ids = new Set([target]);
      let changed = true;

      while (changed) {
        changed = false;
        state.items.forEach(item => {
          const itemId = normalizeId(item.id);
          if (ids.has(itemId)) {
            return;
          }
          const parentValue = item.parentId === null || item.parentId === undefined
            ? null
            : normalizeId(item.parentId);
          if (parentValue !== null && ids.has(parentValue)) {
            ids.add(itemId);
            changed = true;
          }
        });
      }

      return Array.from(ids);
    };

    const removeNodesFromState = ids => {
      const removeSet = new Set(ids.map(normalizeId));
      state.items = state.items.filter(item => !removeSet.has(normalizeId(item.id)));
      removeSet.forEach(id => {
        state.byId.delete(id);
        state.dirty.delete(id);
      });
    };

    const deleteNode = (node, button) => {
      if (!window.confirm('Eintrag entfernen?')) {
        return;
      }
      if (!state.menuId) {
        setFeedback('Bitte zuerst ein Menü auswählen.', 'warning');
        return;
      }

      const idsToRemove = collectDescendantIds(node.id);
      const numericId = Number(node.id);
      const isPersisted = Number.isFinite(numericId);

      if (!isPersisted) {
        removeNodesFromState(idsToRemove);
        renderTree();
        return;
      }

      if (button) {
        button.disabled = true;
      }

      apiFetch(withNamespace(buildItemsPath(state.menuId, `/${numericId}`)), {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' }
      })
        .then(response => {
          if (!response.ok && response.status !== 204) {
            throw new Error('menu-delete-failed');
          }
          removeNodesFromState(idsToRemove);
          state.originalItems = JSON.parse(JSON.stringify(state.items));
          renderTree();
          setFeedback('Eintrag entfernt.', 'success');
        })
        .catch(error => {
          console.error('Failed to delete menu item', error);
          setFeedback('Löschen fehlgeschlagen.', 'danger');
          if (button) {
            button.disabled = false;
          }
        });
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
      if (!state.menuId) {
        const message = document.createElement('div');
        message.className = 'uk-text-meta';
        message.textContent = 'Bitte zuerst ein Menü auswählen.';
        treeRoot.appendChild(message);
        return;
      }
      const tree = buildTree(state.items);
      renderBranch(tree.roots, 0, treeRoot);
      attachDragHandlers();
      updateButtons();
      renderPreview(state.items);
    };

    const updateSelectedMenu = () => {
      const selectedId = menuSelect?.value || container.dataset.selectedMenuId || '';
      const menu = selectedId ? findMenuById(selectedId) : null;
      state.menuId = menu ? Number(menu.id) : null;
      setMenuLabel(menu);
      addButton.disabled = !state.menuId;
      exportButton.disabled = !state.menuId;
      importButton.disabled = !state.menuId;
      if (generateButton) {
        generateButton.disabled = !state.menuId;
      }
      state.dirty.clear();
      updateButtons();
      loadMenuItems(state.locale);
    };

    const loadMenuItems = locale => {
      if (!state.menuId) {
        renderTree();
        return;
      }
      hideFeedback();
      treeRoot.textContent = 'Lädt…';
      const path = appendQueryParam(withNamespace(buildItemsPath(state.menuId)), 'locale', locale);
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
          console.error('Failed to load menu items', error);
          setFeedback('Menüeinträge konnten nicht geladen werden.', 'danger');
          treeRoot.textContent = 'Menüeinträge konnten nicht geladen werden.';
        });
    };

    const triggerAutoGeneration = overwrite => {
      if (!state.menuId) {
        return;
      }

      setFeedback('KI-Generierung ist für Menü-Definitionen aktuell nicht verfügbar.', 'warning');
    };

    const applyVariantSelection = () => {
      if (!variantSelect) {
        return;
      }
      if (state.variant) {
        variantSelect.value = state.variant;
      }
      variantSelect.addEventListener('change', event => {
        state.variant = event.target?.value || '';
        loadMenuItems(state.locale);
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
      const resolvedId = Number.isFinite(Number(item.id)) ? Number(item.id) : null;
      const path = resolvedId
        ? withNamespace(buildItemsPath(state.menuId, `/${resolvedId}`))
        : withNamespace(buildItemsPath(state.menuId));

      return apiFetch(path, {
        method: resolvedId ? 'PATCH' : 'POST',
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
          console.error('Failed to save menu', error);
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
      if (!state.menuId) {
        setFeedback('Bitte zuerst ein Menü auswählen.', 'warning');
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
      if (!state.menuId) {
        setFeedback('Bitte zuerst ein Menü auswählen.', 'warning');
        return;
      }

      const menu = findMenuById(state.menuId);
      const exportPayload = {
        menuId: state.menuId,
        menu: menu
          ? {
            id: menu.id,
            label: menu.label,
            locale: menu.locale,
            isActive: menu.isActive
          }
          : null,
        namespace: state.namespace || 'default',
        items: state.items
      };

      const blob = new Blob([JSON.stringify(exportPayload, null, 2)], { type: 'application/json' });
      const label = menu?.label || 'menu';
      const normalizedLabel = label.toLowerCase().replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'menu';
      const namespace = state.namespace || 'default';
      const locale = state.locale || 'all';
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
      const filename = `marketing-menu-${normalizedLabel}-${namespace}-${locale}-${timestamp}.json`;

      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      setFeedback('Menü exportiert.', 'success');
    };

    const normalizeImportItems = items => {
      if (!Array.isArray(items)) {
        return [];
      }
      return items
        .filter(item => item && typeof item === 'object')
        .map(item => ({
          ...item,
          id: item.id ?? null,
          parentId: item.parentId ?? null
        }));
    };

    const buildImportTree = items => {
      const byId = new Map();
      const roots = [];

      items.forEach(item => {
        if (item.id === null || item.id === undefined) {
          return;
        }
        byId.set(item.id, { ...item, children: [] });
      });

      items.forEach(item => {
        if (item.id === null || item.id === undefined) {
          return;
        }
        const node = byId.get(item.id);
        const parentId = item.parentId;
        if (parentId !== null && parentId !== undefined && byId.has(parentId)) {
          byId.get(parentId).children.push(node);
        } else {
          roots.push(node);
        }
      });

      const sortNodes = nodes => {
        nodes.sort((a, b) => {
          const posA = Number.isFinite(Number(a.position)) ? Number(a.position) : 0;
          const posB = Number.isFinite(Number(b.position)) ? Number(b.position) : 0;
          if (posA === posB) {
            return Number(a.id || 0) - Number(b.id || 0);
          }
          return posA - posB;
        });
        nodes.forEach(node => sortNodes(node.children || []));
      };

      sortNodes(roots);
      return roots;
    };

    const deleteExistingMenuItems = async () => {
      const roots = state.items.filter(item => item.parentId === null || item.parentId === undefined);
      for (const root of roots) {
        const rootId = Number(root.id);
        if (!Number.isFinite(rootId)) {
          continue;
        }
        await apiFetch(withNamespace(buildItemsPath(state.menuId, `/${rootId}`)), {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' }
        });
      }
    };

    const createMenuItemFromPayload = async (menuId, payload, parentId, index) => {
      const body = {
        label: payload.label || '',
        href: payload.href || '',
        icon: payload.icon || null,
        parentId: parentId ?? null,
        layout: payload.layout || 'link',
        detailTitle: payload.detailTitle || null,
        detailText: payload.detailText || null,
        detailSubline: payload.detailSubline || null,
        position: Number.isFinite(payload.position) ? payload.position : index,
        isExternal: payload.isExternal === true,
        locale: payload.locale || null,
        isActive: payload.isActive !== false,
        isStartpage: payload.isStartpage === true
      };

      const response = await apiFetch(withNamespace(buildItemsPath(menuId)), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });

      if (!response.ok) {
        const errorBody = await response.json().catch(() => ({}));
        throw new Error(errorBody?.error || 'Import fehlgeschlagen.');
      }

      const data = await response.json();
      return data?.item?.id ?? null;
    };

    const createMenuItemsFromTree = async (menuId, nodes, parentId = null) => {
      for (let index = 0; index < nodes.length; index += 1) {
        const node = nodes[index];
        const newId = await createMenuItemFromPayload(menuId, node, parentId, index);
        if (node.children?.length) {
          await createMenuItemsFromTree(menuId, node.children, newId);
        }
      }
    };

    const submitImportPayload = async payload => {
      if (!state.menuId) {
        setFeedback('Bitte zuerst ein Menü auswählen.', 'warning');
        return;
      }

      const payloadMenuId = payload?.menuId ?? payload?.menu?.id ?? null;
      if (payloadMenuId && Number(payloadMenuId) !== Number(state.menuId)) {
        const confirmImport = window.confirm(
          'Menu-ID des Exports stimmt nicht mit dem Zielmenü überein. Trotzdem importieren?'
        );
        if (!confirmImport) {
          return;
        }
      }

      const normalizedItems = normalizeImportItems(payload?.items || []);
      if (!normalizedItems.length) {
        setFeedback('Import fehlgeschlagen: Keine Einträge gefunden.', 'danger');
        return;
      }

      setFeedback('Menü wird importiert…', 'primary');

      try {
        await deleteExistingMenuItems();
        const tree = buildImportTree(normalizedItems);
        await createMenuItemsFromTree(state.menuId, tree);
        setFeedback('Menü importiert.', 'success');
        loadMenuItems(state.locale);
      } catch (error) {
        console.error('Failed to import menu items', error);
        setFeedback(error?.message || 'Import fehlgeschlagen.', 'danger');
      }
    };

    const handleImportFile = file => {
      const reader = new FileReader();
      reader.onload = event => {
        try {
          const content = event?.target?.result || '';
          const payload = JSON.parse(content);
          submitImportPayload(payload);
        } catch (error) {
          console.error('Invalid menu import file', error);
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
        const handle = item.querySelector('.menu-tree__drag');

        item.addEventListener('dragstart', event => {
          if (event.target !== item && !event.target.closest('.menu-tree__drag')) {
            event.preventDefault();
            return;
          }
          draggingElement = item;
          item.classList.add('is-dragging');
          handle?.setAttribute('aria-grabbed', 'true');
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', item.dataset.id);
        });

        item.addEventListener('dragend', () => {
          if (draggingElement) {
            draggingElement.classList.remove('is-dragging');
            draggingElement = null;
            collectTreeOrder();
          }
          handle?.setAttribute('aria-grabbed', 'false');
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
      if (!state.menuId) {
        setFeedback('Bitte zuerst ein Menü auswählen.', 'warning');
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

    menuSelect?.addEventListener('change', updateSelectedMenu);
    window.addEventListener('marketing-menu:list-updated', event => {
      const updated = Array.isArray(event.detail?.menus) ? event.detail.menus : null;
      if (updated) {
        state.menus = updated;
        updateSelectedMenu();
      }
    });
    localeSelect?.addEventListener('change', () => {
      state.locale = localeSelect?.value || '';
      loadMenuItems(state.locale);
    });
    generateButton?.addEventListener('click', () => {
      triggerAutoGeneration(false);
    });

    applyVariantSelection();
    parseMenus();
    updateSelectedMenu();
  })();
}
