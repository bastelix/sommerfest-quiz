/* global UIkit */

const manager = document.querySelector('[data-marketing-menu-manager]');

if (manager) {
  (() => {
    manager.dataset.menuInitialized = 'true';
    const pagesData = (() => {
      try {
        const parsed = JSON.parse(manager.dataset.pages || '[]');
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        console.warn('Failed to parse marketing menu pages dataset', error);
        return [];
      }
    })();

    const pageSelect = document.getElementById('pageContentSelect');
    const localeSelect = document.getElementById('menuLocaleSelect');
    const pageLabel = manager.querySelector('[data-menu-page-label]');
    const addButton = manager.querySelector('[data-menu-add]');
    const exportButton = manager.querySelector('[data-menu-export]');
    const importButton = manager.querySelector('[data-menu-import]');
    const importInput = manager.querySelector('[data-menu-import-input]');
    const itemsBody = manager.querySelector('[data-menu-items]');
    const feedback = manager.querySelector('[data-menu-feedback]');
    const loadingRow = manager.querySelector('[data-menu-loading-row]');
    if (loadingRow) {
      loadingRow.innerHTML = '<td colspan="12">Lädt…</td>';
    }

    const basePath = manager.dataset.basePath || window.basePath || '';
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
    const NAMESPACE_MISMATCH_ERROR = 'Namespace des Exports stimmt nicht mit der Seite überein.';

    if (!itemsBody || !addButton) {
      console.warn('Marketing menu manager requirements missing.');
      return;
    }

    const state = {
      pageId: null,
      pageSlug: '',
      items: [],
      tree: [],
      flatItems: [],
      descendants: new Map(),
      order: [],
      orderSignature: ''
    };

    const findPageBySlug = slug => pagesData.find(page => page.slug === slug) || null;
    const resolveNamespace = () => {
      const select = document.getElementById('pageNamespaceSelect');
      const candidate = select?.value || manager.dataset.namespace || window.pageNamespace || '';
      return String(candidate || '').trim();
    };
    const resolveLocale = () => {
      const candidate = localeSelect?.value ?? manager.dataset.locale ?? '';
      return String(candidate || '').trim();
    };
    const normalizeLocaleValue = value => String(value || '').trim().toLowerCase();
    const appendQueryParam = (path, key, value) => {
      if (!value) {
        return path;
      }
      const separator = path.includes('?') ? '&' : '?';
      return `${path}${separator}${encodeURIComponent(key)}=${encodeURIComponent(value)}`;
    };
    const withNamespace = path => appendQueryParam(path, 'namespace', resolveNamespace());

    const resolveCsrfToken = () =>
      document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.csrfToken || '';

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

    const buildPath = (pageId, suffix = '') => {
      const path = `/admin/pages/${pageId}${suffix}`;
      if (typeof window.apiFetch === 'function') {
        return path;
      }
      return `${basePath}${path}`;
    };

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

    const downloadMenu = () => {
      if (!state.pageId) {
        setFeedback('Bitte zuerst eine Marketing-Seite auswählen.', 'warning');
        return;
      }

      const path = appendQueryParam(withNamespace(buildPath(state.pageId, '/menu/export')), 'locale', resolveLocale());
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
          const namespace = resolveNamespace() || 'default';
          const locale = resolveLocale() || 'all';
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

    const submitImportPayload = (payload, options = {}) => {
      const { allowNamespaceMismatch = false } = options;

      if (!state.pageId) {
        setFeedback('Bitte zuerst eine Marketing-Seite auswählen.', 'warning');
        return;
      }

      const body = allowNamespaceMismatch ? { ...payload, allowNamespaceMismatch: true } : payload;

      apiFetch(withNamespace(buildPath(state.pageId, '/menu/import')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      })
        .then(async response => {
          if (response.ok || response.status === 204) {
            return null;
          }
          const responseBody = await response.json().catch(() => ({}));
          const message = responseBody?.error || 'Import fehlgeschlagen.';
          const error = new Error(message);
          error.responseStatus = response.status;
          error.responseBody = responseBody;
          throw error;
        })
        .then(() => {
          setFeedback('Menü importiert.', 'success');
          loadMenuItems(resolveLocale());
        })
        .catch(error => {
          if (
            !allowNamespaceMismatch
            && (error?.message === NAMESPACE_MISMATCH_ERROR
              || error?.responseBody?.error === NAMESPACE_MISMATCH_ERROR)
          ) {
            const confirmImport = window.confirm(
              'Namespace des Exports stimmt nicht mit der Seite überein. Trotzdem importieren?'
            );
            if (confirmImport) {
              submitImportPayload(payload, { allowNamespaceMismatch: true });
              return;
            }
          }
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

  const setLoading = () => {
    itemsBody.innerHTML = '';
    const row = loadingRow ? loadingRow.cloneNode(true) : null;
    if (row) {
      itemsBody.appendChild(row);
      return;
    }
    const fallback = document.createElement('tr');
    fallback.innerHTML = '<td colspan="12">Lädt…</td>';
    itemsBody.appendChild(fallback);
  };

  const enforceSingleStartpage = row => {
    const currentLocale = normalizeLocaleValue(row.querySelector('[data-menu-locale]')?.value);
    itemsBody.querySelectorAll('tr[data-menu-row]').forEach(entry => {
      if (entry === row) {
        return;
      }
      const entryLocale = normalizeLocaleValue(entry.querySelector('[data-menu-locale]')?.value);
      if (entryLocale !== currentLocale) {
        return;
      }
      const input = entry.querySelector('[data-menu-startpage]');
      if (input?.checked) {
        input.checked = false;
      }
    });
  };

  const showEmpty = message => {
    itemsBody.innerHTML = '';
    const row = document.createElement('tr');
    row.dataset.menuEmpty = 'true';
    const cell = document.createElement('td');
    cell.colSpan = 12;
    cell.textContent = message;
    row.appendChild(cell);
    itemsBody.appendChild(row);
  };

  const updateIconPreview = (preview, icon) => {
    if (!preview) {
      return;
    }
    if (!icon) {
      preview.hidden = true;
      preview.removeAttribute('uk-icon');
      preview.textContent = '';
      return;
    }
    preview.hidden = false;
    preview.setAttribute('uk-icon', `icon: ${icon}`);
    if (window.UIkit && typeof UIkit.icon === 'function') {
      UIkit.icon(preview, { icon });
    }
  };

  const validateHref = href => {
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
    if (!basePath) {
      return null;
    }
    if (href === basePath || href === `${basePath}/`) {
      return null;
    }
    if (!href.startsWith(`${basePath}/`)) {
      return 'Link muss mit dem BasePath beginnen.';
    }
    return null;
  };

  const resolveRowData = row => {
    const labelInput = row.querySelector('[data-menu-label]');
    const hrefInput = row.querySelector('[data-menu-href]');
    const iconSelect = row.querySelector('[data-menu-icon]');
    const parentSelect = row.querySelector('[data-menu-parent]');
    const layoutSelect = row.querySelector('[data-menu-layout]');
    const localeInput = row.querySelector('[data-menu-locale]');
    const startpageInput = row.querySelector('[data-menu-startpage]');
    const externalInput = row.querySelector('[data-menu-external]');
    const activeInput = row.querySelector('[data-menu-active]');
    const detailTitleInput = row.querySelector('[data-menu-detail-title]');
    const detailTextInput = row.querySelector('[data-menu-detail-text]');
    const detailSublineInput = row.querySelector('[data-menu-detail-subline]');
    const parentValue = parentSelect?.value ? Number(parentSelect.value) : null;
    return {
      id: row.dataset.id ? Number(row.dataset.id) : null,
      label: labelInput?.value.trim() || '',
      href: hrefInput?.value.trim() || '',
      icon: iconSelect?.value.trim() || '',
      parentId: Number.isFinite(parentValue) ? parentValue : null,
      layout: layoutSelect?.value.trim() || 'link',
      locale: localeInput?.value.trim() || '',
      isStartpage: !!startpageInput?.checked,
      isExternal: !!externalInput?.checked,
      isActive: !!activeInput?.checked,
      detailTitle: detailTitleInput?.value.trim() || '',
      detailText: detailTextInput?.value.trim() || '',
      detailSubline: detailSublineInput?.value.trim() || ''
    };
  };

  const setRowBusy = (row, busy) => {
    row.querySelectorAll('input, select, button').forEach(element => {
      element.disabled = busy;
    });
  };

  const setRowDirty = (row, dirty) => {
    row.dataset.dirty = dirty ? '1' : '0';
    const saveButton = row.querySelector('[data-menu-save]');
    if (saveButton) {
      saveButton.disabled = !dirty;
    }
  };

  const setValidationState = (input, errorElement, message) => {
    if (input) {
      input.classList.toggle('uk-form-danger', !!message);
    }
    if (errorElement) {
      if (message) {
        errorElement.textContent = message;
        errorElement.hidden = false;
      } else {
        errorElement.textContent = '';
        errorElement.hidden = true;
      }
    }
  };

  const refreshRowValidation = row => {
    const hrefInput = row.querySelector('[data-menu-href]');
    const hrefError = row.querySelector('[data-menu-link-error]');
    const hrefValue = hrefInput?.value.trim() || '';
    const error = hrefValue ? validateHref(hrefValue) : 'Link ist erforderlich.';
    setValidationState(hrefInput, hrefError, error);
    return !error;
  };

  const buildTree = items => {
    const byId = new Map();
    items.forEach(item => {
      if (!item?.id) {
        return;
      }
      byId.set(item.id, { ...item, children: [] });
    });

    const roots = [];
    byId.forEach(node => {
      if (node.parentId && byId.has(node.parentId)) {
        byId.get(node.parentId).children.push(node);
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
      nodes.forEach(node => sortNodes(node.children));
    };
    sortNodes(roots);

    return { roots, byId };
  };

  const flattenTree = (nodes, depth = 0, list = []) => {
    nodes.forEach(node => {
      list.push({ ...node, depth });
      if (node.children && node.children.length) {
        flattenTree(node.children, depth + 1, list);
      }
    });
    return list;
  };

  const buildDescendantsMap = nodes => {
    const map = new Map();
    const visit = node => {
      const children = node.children || [];
      const descendants = new Set();
      children.forEach(child => {
        descendants.add(child.id);
        const childDescendants = visit(child);
        childDescendants.forEach(id => descendants.add(id));
      });
      map.set(node.id, descendants);
      return descendants;
    };
    nodes.forEach(node => visit(node));
    return map;
  };

  const createIconSelect = selected => {
    const select = document.createElement('select');
    select.className = 'uk-select uk-form-small';
    select.dataset.menuIcon = 'true';
    iconOptions.forEach(icon => {
      const option = document.createElement('option');
      option.value = icon;
      option.textContent = icon === '' ? 'Keins' : icon;
      if (icon === selected) {
        option.selected = true;
      }
      select.appendChild(option);
    });
    return select;
  };

  const createLayoutSelect = selected => {
    const select = document.createElement('select');
    select.className = 'uk-select uk-form-small';
    select.dataset.menuLayout = 'true';
    layoutOptions.forEach(option => {
      const opt = document.createElement('option');
      opt.value = option.value;
      opt.textContent = option.label;
      if (option.value === selected) {
        opt.selected = true;
      }
      select.appendChild(opt);
    });
    return select;
  };

  const createParentSelect = item => {
    const select = document.createElement('select');
    select.className = 'uk-select uk-form-small';
    select.dataset.menuParent = 'true';

    const emptyOption = document.createElement('option');
    emptyOption.value = '';
    emptyOption.textContent = '—';
    select.appendChild(emptyOption);

    const descendants = state.descendants?.get(item?.id) || new Set();
    state.flatItems
      .filter(entry => entry.id && entry.id !== item?.id && !descendants.has(entry.id))
      .forEach(entry => {
        const option = document.createElement('option');
        option.value = String(entry.id);
        const indent = '—'.repeat(entry.depth || 0);
        option.textContent = indent ? `${indent} ${entry.label}` : entry.label;
        if (entry.id === item?.parentId) {
          option.selected = true;
        }
        select.appendChild(option);
      });

    return select;
  };

  const createRow = item => {
    const row = document.createElement('tr');
    row.dataset.menuRow = 'true';
    if (item?.id) {
      row.dataset.id = String(item.id);
    }
    row.dataset.parentId = item?.parentId ? String(item.parentId) : '';

    const handleCell = document.createElement('td');
    handleCell.className = 'uk-table-shrink';
    const handleButton = document.createElement('button');
    handleButton.type = 'button';
    handleButton.className = 'uk-icon-button uk-button-default uk-button-small';
    handleButton.setAttribute('uk-icon', 'icon: menu');
    handleButton.setAttribute('aria-label', 'Sortieren');
    handleButton.dataset.menuDragHandle = 'true';
    handleButton.disabled = !item?.id;
    handleCell.appendChild(handleButton);

    const labelCell = document.createElement('td');
    const labelWrapper = document.createElement('div');
    const labelInput = document.createElement('input');
    labelInput.className = 'uk-input uk-form-small';
    labelInput.type = 'text';
    labelInput.placeholder = 'Menütitel';
    labelInput.value = item?.label || '';
    labelInput.dataset.menuLabel = 'true';
    labelWrapper.style.paddingLeft = `${Math.max(0, Number(item?.depth || 0)) * 16}px`;
    labelWrapper.appendChild(labelInput);
    labelCell.appendChild(labelWrapper);

    const hrefCell = document.createElement('td');
    const hrefInput = document.createElement('input');
    hrefInput.className = 'uk-input uk-form-small';
    hrefInput.type = 'text';
    hrefInput.placeholder = '/landing oder https://';
    hrefInput.value = item?.href || '';
    hrefInput.dataset.menuHref = 'true';
    const hrefError = document.createElement('div');
    hrefError.className = 'uk-text-danger uk-text-small';
    hrefError.dataset.menuLinkError = 'true';
    hrefError.hidden = true;
    hrefCell.appendChild(hrefInput);
    hrefCell.appendChild(hrefError);

    const parentCell = document.createElement('td');
    parentCell.className = 'uk-table-shrink';
    const parentSelect = createParentSelect(item);
    parentCell.appendChild(parentSelect);

    const layoutCell = document.createElement('td');
    layoutCell.className = 'uk-table-shrink';
    const layoutSelect = createLayoutSelect(item?.layout || 'link');
    layoutCell.appendChild(layoutSelect);

    const iconCell = document.createElement('td');
    const iconWrapper = document.createElement('div');
    iconWrapper.className = 'uk-flex uk-flex-middle';
    iconWrapper.style.gap = '6px';
    const iconPreview = document.createElement('span');
    iconPreview.dataset.menuIconPreview = 'true';
    const iconSelect = createIconSelect(item?.icon || '');
    iconWrapper.appendChild(iconPreview);
    iconWrapper.appendChild(iconSelect);
    iconCell.appendChild(iconWrapper);
    updateIconPreview(iconPreview, item?.icon || '');

    const localeCell = document.createElement('td');
    const localeInput = document.createElement('input');
    localeInput.className = 'uk-input uk-form-small';
    localeInput.type = 'text';
    localeInput.placeholder = 'de';
    localeInput.value = item?.locale || '';
    localeInput.dataset.menuLocale = 'true';
    const activeLocaleFilter = resolveLocale();
    if (activeLocaleFilter && !item?.id) {
      localeInput.readOnly = true;
      localeInput.title = 'Locale wird durch den Filter vorgegeben.';
    }
    localeCell.appendChild(localeInput);

    const startpageCell = document.createElement('td');
    startpageCell.className = 'uk-table-shrink uk-text-center';
    const startpageInput = document.createElement('input');
    startpageInput.type = 'checkbox';
    startpageInput.className = 'uk-checkbox';
    startpageInput.checked = item?.isStartpage === true;
    startpageInput.dataset.menuStartpage = 'true';
    startpageCell.appendChild(startpageInput);

    const externalCell = document.createElement('td');
    externalCell.className = 'uk-table-shrink uk-text-center';
    const externalInput = document.createElement('input');
    externalInput.type = 'checkbox';
    externalInput.className = 'uk-checkbox';
    externalInput.checked = item?.isExternal === true;
    externalInput.dataset.menuExternal = 'true';
    externalCell.appendChild(externalInput);

    const activeCell = document.createElement('td');
    activeCell.className = 'uk-table-shrink uk-text-center';
    const activeInput = document.createElement('input');
    activeInput.type = 'checkbox';
    activeInput.className = 'uk-checkbox';
    activeInput.checked = item?.isActive !== false;
    activeInput.dataset.menuActive = 'true';
    activeCell.appendChild(activeInput);

    const detailCell = document.createElement('td');
    const detailWrapper = document.createElement('div');
    detailWrapper.className = 'uk-flex uk-flex-column';
    detailWrapper.style.gap = '6px';
    const detailTitleInput = document.createElement('input');
    detailTitleInput.className = 'uk-input uk-form-small';
    detailTitleInput.type = 'text';
    detailTitleInput.placeholder = 'Titel';
    detailTitleInput.value = item?.detailTitle || '';
    detailTitleInput.dataset.menuDetailTitle = 'true';
    const detailTextInput = document.createElement('textarea');
    detailTextInput.className = 'uk-textarea uk-form-small';
    detailTextInput.rows = 2;
    detailTextInput.placeholder = 'Beschreibung';
    detailTextInput.value = item?.detailText || '';
    detailTextInput.dataset.menuDetailText = 'true';
    const detailSublineInput = document.createElement('input');
    detailSublineInput.className = 'uk-input uk-form-small';
    detailSublineInput.type = 'text';
    detailSublineInput.placeholder = 'Subline';
    detailSublineInput.value = item?.detailSubline || '';
    detailSublineInput.dataset.menuDetailSubline = 'true';
    detailWrapper.append(detailTitleInput, detailTextInput, detailSublineInput);
    detailCell.appendChild(detailWrapper);

    const actionCell = document.createElement('td');
    actionCell.className = 'uk-table-shrink uk-text-right';
    const saveButton = document.createElement('button');
    saveButton.type = 'button';
    saveButton.className = 'uk-button uk-button-primary uk-button-small';
    saveButton.textContent = item?.id ? 'Speichern' : 'Erstellen';
    saveButton.dataset.menuSave = 'true';
    const deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'uk-icon-button uk-button-danger uk-button-small uk-margin-small-left';
    deleteButton.setAttribute('uk-icon', 'trash');
    deleteButton.setAttribute('aria-label', 'Entfernen');
    deleteButton.dataset.menuDelete = 'true';
    actionCell.appendChild(saveButton);
    actionCell.appendChild(deleteButton);

    row.append(
      handleCell,
      labelCell,
      hrefCell,
      parentCell,
      layoutCell,
      iconCell,
      localeCell,
      startpageCell,
      externalCell,
      activeCell,
      detailCell,
      actionCell
    );

    const markDirty = () => setRowDirty(row, true);
    labelInput.addEventListener('input', markDirty);
    hrefInput.addEventListener('input', () => {
      markDirty();
      refreshRowValidation(row);
    });
    iconSelect.addEventListener('change', event => {
      markDirty();
      updateIconPreview(iconPreview, event.target.value || '');
    });
    parentSelect.addEventListener('change', event => {
      markDirty();
      row.dataset.parentId = event.target.value || '';
    });
    layoutSelect.addEventListener('change', markDirty);
    localeInput.addEventListener('input', markDirty);
    startpageInput.addEventListener('change', () => {
      if (startpageInput.checked) {
        enforceSingleStartpage(row);
      }
      markDirty();
    });
    externalInput.addEventListener('change', markDirty);
    activeInput.addEventListener('change', markDirty);
    detailTitleInput.addEventListener('input', markDirty);
    detailTextInput.addEventListener('input', markDirty);
    detailSublineInput.addEventListener('input', markDirty);

    saveButton.addEventListener('click', () => saveRow(row));
    deleteButton.addEventListener('click', () => deleteRow(row));

    setRowDirty(row, !item?.id);
    refreshRowValidation(row);

    if (item?.id) {
      row.draggable = true;
    }

    return row;
  };

  const renderItems = items => {
    const tree = buildTree(items);
    state.tree = tree.roots;
    state.flatItems = flattenTree(tree.roots);
    state.descendants = buildDescendantsMap(tree.roots);

    itemsBody.innerHTML = '';
    if (!state.flatItems.length) {
      showEmpty('Keine Menüeinträge vorhanden.');
      return;
    }
    state.flatItems.forEach(item => {
      itemsBody.appendChild(createRow(item));
    });
  };

  const loadMenuItems = locale => {
    hideFeedback();
    if (!state.pageId) {
      showEmpty('Erstelle zuerst eine Marketing-Seite, um das Hauptmenü zu bearbeiten.');
      return;
    }
    setLoading();
    const path = appendQueryParam(withNamespace(buildPath(state.pageId, '/menu')), 'locale', locale);
    apiFetch(path)
      .then(response => {
        if (!response.ok) {
          throw new Error('menu-load-failed');
        }
        return response.json();
      })
      .then(data => {
        const items = Array.isArray(data?.items) ? data.items.slice() : [];
        state.items = items;
        renderItems(items);
        state.orderSignature = JSON.stringify(buildOrderPayload());
      })
      .catch(error => {
        console.error('Failed to load menu items', error);
        showEmpty('Menüeinträge konnten nicht geladen werden.');
        setFeedback('Menüeinträge konnten nicht geladen werden.', 'danger');
      });
  };

  const saveRow = row => {
    if (!state.pageId) {
      return;
    }
    const data = resolveRowData(row);
    const labelInput = row.querySelector('[data-menu-label]');
    const hrefInput = row.querySelector('[data-menu-href]');
    const hrefError = row.querySelector('[data-menu-link-error]');

    const labelError = data.label ? '' : 'Label ist erforderlich.';
    labelInput.classList.toggle('uk-form-danger', !!labelError);
    const hrefValid = refreshRowValidation(row);

    if (labelError || !hrefValid) {
      if (labelError) {
        setFeedback(labelError, 'danger');
      } else if (hrefError?.textContent) {
        setFeedback(hrefError.textContent, 'danger');
      }
      return;
    }

    setRowBusy(row, true);

    const parentIdValue = data.parentId ? String(data.parentId) : '';
    const siblingRows = Array.from(itemsBody.querySelectorAll('tr[data-menu-row]'))
      .filter(entry => (entry.dataset.parentId || '') === parentIdValue);
    const position = siblingRows.indexOf(row);

    const payload = {
      label: data.label,
      href: data.href,
      icon: data.icon || null,
      parentId: data.parentId || null,
      layout: data.layout || 'link',
      detailTitle: data.detailTitle || null,
      detailText: data.detailText || null,
      detailSubline: data.detailSubline || null,
      position: position >= 0 ? position : 0,
      isExternal: data.isExternal,
      locale: data.locale || null,
      isActive: data.isActive,
      isStartpage: data.isStartpage
    };
    if (data.id) {
      payload.id = data.id;
    }

    apiFetch(withNamespace(buildPath(state.pageId, '/menu')), {
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
        const item = data?.item || {};
        if (item.id) {
          row.dataset.id = String(item.id);
          row.draggable = true;
          const dragHandle = row.querySelector('[data-menu-drag-handle]');
          if (dragHandle) {
            dragHandle.disabled = false;
          }
        }
        row.querySelector('[data-menu-label]').value = item.label || payload.label;
        row.querySelector('[data-menu-href]').value = item.href || payload.href;
        row.querySelector('[data-menu-parent]').value = item.parentId ? String(item.parentId) : '';
        row.querySelector('[data-menu-layout]').value = item.layout || payload.layout || 'link';
        row.querySelector('[data-menu-icon]').value = item.icon || '';
        row.querySelector('[data-menu-locale]').value = item.locale || '';
        row.querySelector('[data-menu-startpage]').checked = item.isStartpage === true;
        row.querySelector('[data-menu-external]').checked = item.isExternal === true;
        row.querySelector('[data-menu-active]').checked = item.isActive !== false;
        row.querySelector('[data-menu-detail-title]').value = item.detailTitle || '';
        row.querySelector('[data-menu-detail-text]').value = item.detailText || '';
        row.querySelector('[data-menu-detail-subline]').value = item.detailSubline || '';
        row.dataset.parentId = item.parentId ? String(item.parentId) : '';
        const iconPreview = row.querySelector('[data-menu-icon-preview]');
        updateIconPreview(iconPreview, item.icon || '');
        row.querySelector('[data-menu-save]').textContent = 'Speichern';
        setRowDirty(row, false);
        hideFeedback();
        const hasDirtyRows = Array.from(itemsBody.querySelectorAll('tr[data-menu-row]'))
          .some(entry => entry !== row && entry.dataset.dirty === '1');
        if (!hasDirtyRows) {
          loadMenuItems(resolveLocale());
        }
      })
      .catch(error => {
        console.error('Failed to save menu item', error);
        setFeedback('Speichern fehlgeschlagen.', 'danger');
      })
      .finally(() => {
        setRowBusy(row, false);
      });
  };

  const deleteRow = row => {
    if (!confirm('Eintrag entfernen?')) {
      return;
    }
    const id = row.dataset.id ? Number(row.dataset.id) : null;
    if (!id) {
      row.remove();
      if (!itemsBody.querySelector('tr[data-menu-row]')) {
        showEmpty('Keine Menüeinträge vorhanden.');
      }
      return;
    }
    setRowBusy(row, true);
    apiFetch(withNamespace(buildPath(state.pageId, '/menu')), {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    })
      .then(response => {
        if (!response.ok && response.status !== 204) {
          throw new Error('menu-delete-failed');
        }
        row.remove();
        hideFeedback();
        if (!itemsBody.querySelector('tr[data-menu-row]')) {
          showEmpty('Keine Menüeinträge vorhanden.');
        }
        saveOrder();
      })
      .catch(error => {
        console.error('Failed to delete menu item', error);
        setFeedback('Löschen fehlgeschlagen.', 'danger');
        setRowBusy(row, false);
      });
  };

  const buildOrderPayload = () => {
    const rows = Array.from(itemsBody.querySelectorAll('tr[data-menu-row]'));
    const parentPositions = new Map();
    const orderedItems = [];

    rows.forEach(row => {
      const id = row.dataset.id ? Number(row.dataset.id) : null;
      if (!id) {
        return;
      }
      const parentKey = row.dataset.parentId || 'root';
      const current = parentPositions.get(parentKey) ?? 0;
      orderedItems.push({ id, position: current });
      parentPositions.set(parentKey, current + 1);
    });

    return orderedItems;
  };

  const saveOrder = () => {
    if (!state.pageId) {
      return;
    }
    const orderedItems = buildOrderPayload();
    if (orderedItems.length < 2) {
      state.orderSignature = JSON.stringify(orderedItems);
      return;
    }
    const signature = JSON.stringify(orderedItems);
    const hasChanged = signature !== state.orderSignature;
    if (!hasChanged) {
      return;
    }
    apiFetch(withNamespace(buildPath(state.pageId, '/menu/sort')), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orderedItems })
    })
      .then(response => {
        if (!response.ok && response.status !== 204) {
          throw new Error('menu-sort-failed');
        }
        state.orderSignature = signature;
        hideFeedback();
      })
      .catch(error => {
        console.error('Failed to sort menu items', error);
        setFeedback('Sortieren fehlgeschlagen.', 'danger');
      });
  };

  const addRow = () => {
    if (!state.pageId) {
      setFeedback('Bitte zuerst eine Marketing-Seite auswählen.', 'warning');
      return;
    }
    const emptyRow = itemsBody.querySelector('[data-menu-empty]');
    if (emptyRow) {
      emptyRow.remove();
    }
    const row = createRow({
      label: '',
      href: '',
      icon: '',
      layout: 'link',
      parentId: null,
      detailTitle: '',
      detailText: '',
      detailSubline: '',
      isStartpage: false,
      locale: resolveLocale() || '',
      isExternal: false,
      isActive: true
    });
    itemsBody.appendChild(row);
    row.querySelector('[data-menu-label]').focus();
  };

  const attachDragHandlers = () => {
    let draggingRow = null;
    let draggingParentId = '';

    const getDragAfterElement = (container, y) => {
      const rows = [...container.querySelectorAll('tr[data-menu-row]:not(.is-dragging)')];
      return rows.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
          return { offset, element: child };
        }
        return closest;
      }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    };

    itemsBody.addEventListener('dragstart', event => {
      const row = event.target.closest('tr[data-menu-row]');
      if (!row || !row.dataset.id) {
        event.preventDefault();
        return;
      }
      const isHandle = event.target.closest('[data-menu-drag-handle]');
      if (!isHandle) {
        event.preventDefault();
        return;
      }
      draggingRow = row;
      draggingParentId = row.dataset.parentId || '';
      row.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', row.dataset.id);
    });

    itemsBody.addEventListener('dragover', event => {
      if (!draggingRow) {
        return;
      }
      const targetRow = event.target.closest('tr[data-menu-row]');
      if (targetRow && (targetRow.dataset.parentId || '') !== draggingParentId) {
        return;
      }
      event.preventDefault();
      const afterElement = getDragAfterElement(itemsBody, event.clientY);
      if (afterElement === null) {
        itemsBody.appendChild(draggingRow);
      } else if (afterElement !== draggingRow) {
        itemsBody.insertBefore(draggingRow, afterElement);
      }
    });

    itemsBody.addEventListener('dragend', () => {
      if (draggingRow) {
        draggingRow.classList.remove('is-dragging');
        draggingRow = null;
        draggingParentId = '';
        saveOrder();
      }
    });
  };

  const updateSelectedPage = () => {
    const slug = pageSelect?.value || manager.dataset.selectedSlug || '';
    const page = slug ? findPageBySlug(slug) : null;
    state.pageSlug = slug;
    state.pageId = page ? Number(page.id) : null;
    setPageLabel(page);
    addButton.disabled = !state.pageId;
    if (exportButton) {
      exportButton.disabled = !state.pageId;
    }
    if (importButton) {
      importButton.disabled = !state.pageId;
    }
    loadMenuItems(resolveLocale());
  };

  addButton.addEventListener('click', addRow);

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
    if (!file) {
      return;
    }
    handleImportFile(file);
  });

  pageSelect?.addEventListener('change', () => {
    updateSelectedPage();
  });

  localeSelect?.addEventListener('change', () => {
    manager.dataset.locale = localeSelect.value || '';
    loadMenuItems(resolveLocale());
  });

  document.addEventListener('marketing-page:created', event => {
    const page = event.detail || {};
    if (page?.slug) {
      const existing = pagesData.findIndex(entry => entry.slug === page.slug);
      if (existing >= 0) {
        pagesData[existing] = { ...pagesData[existing], ...page };
      } else {
        pagesData.push(page);
      }
    }
    updateSelectedPage();
  });

  document.addEventListener('marketing-page:deleted', event => {
    const slug = event.detail?.slug || '';
    if (slug) {
      const index = pagesData.findIndex(page => page.slug === slug);
      if (index >= 0) {
        pagesData.splice(index, 1);
      }
    }
    updateSelectedPage();
  });

  attachDragHandlers();
  updateSelectedPage();
  })();
}
