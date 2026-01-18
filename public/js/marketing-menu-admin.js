/* global UIkit */

const manager = document.querySelector('[data-marketing-menu-manager]');

if (manager) {
  (() => {
    manager.dataset.menuInitialized = 'true';
    let menusData = (() => {
      try {
        const parsed = JSON.parse(manager.dataset.menus || '[]');
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        console.warn('Failed to parse marketing menus dataset', error);
        return [];
      }
    })();
    const internalLinks = (() => {
      try {
        const parsed = JSON.parse(manager.dataset.internalLinks || '[]');
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        console.warn('Failed to parse internal link options', error);
        return [];
      }
    })();

    const menuSelect = document.getElementById('menuDefinitionSelect');
    const localeSelect = document.getElementById('menuLocaleSelect');
    const pageLabel = manager.querySelector('[data-menu-page-label]');
    const addButton = manager.querySelector('[data-menu-add]');
    const exportButton = manager.querySelector('[data-menu-export]');
    const importButton = manager.querySelector('[data-menu-import]');
    const importInput = manager.querySelector('[data-menu-import-input]');
    const itemsBody = manager.querySelector('[data-menu-items]');
    const feedback = manager.querySelector('[data-menu-feedback]');
    const loadingRow = manager.querySelector('[data-menu-loading-row]');
    const previewTree = document.querySelector('[data-menu-preview-tree]');
    const previewEmpty = document.querySelector('[data-menu-preview-empty]');
    const previewSummary = document.querySelector('[data-menu-preview-summary]');
    const generateButton = manager.querySelector('[data-menu-generate-ai]');
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
    const namespaceMismatchMessage = 'Namespace/Locale von Eltern- und Kindelement müssen übereinstimmen.';

    if (!itemsBody || !addButton) {
      console.warn('Marketing menu manager requirements missing.');
      return;
    }

    const state = {
      menuId: null,
      items: [],
      tree: [],
      flatItems: [],
      descendants: new Map(),
      order: [],
      orderSignature: ''
    };

    const renderHrefOptions = options => {
      if (!options.length) {
        return null;
      }
      const list = document.createElement('datalist');
      list.id = `menu-href-options-${Math.random().toString(36).slice(2, 9)}`;
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
      manager.appendChild(hrefOptionsList);
    }

    const findMenuById = id => menusData.find(menu => Number(menu.id) === Number(id)) || null;
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

    const buildItemsPath = (menuId, suffix = '') => {
      const path = `/admin/menus/${menuId}/items${suffix}`;
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
        namespace: resolveNamespace() || 'default',
        items: state.items
      };
      const blob = new Blob([JSON.stringify(exportPayload, null, 2)], { type: 'application/json' });
      const label = menu?.label || 'menu';
      const normalizedLabel = label.toLowerCase().replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'menu';
      const namespace = resolveNamespace() || 'default';
      const locale = resolveLocale() || 'all';
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
        loadMenuItems(resolveLocale());
      } catch (error) {
        console.error('Failed to import marketing menu', error);
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
      try {
        // eslint-disable-next-line no-new
        new URL(href);
      } catch (error) {
        return 'Ungültige URL.';
      }
      if (scheme === 'mailto' && !href.includes('@')) {
        return 'Mailto-Links benötigen eine Empfänger-Adresse.';
      }
      if (scheme === 'tel' && !href.replace(/[^0-9+]/g, '').length) {
        return 'Telefon-Links benötigen eine Rufnummer.';
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

  const normalizeSlug = href => {
    const clean = (href || '').split(/[?#]/)[0];
    const withoutBase = basePath && clean.startsWith(`${basePath}/`)
      ? clean.slice(basePath.length)
      : clean;
    return withoutBase.replace(/^\//, '').replace(/\/$/, '').toLowerCase();
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

  const updateStartpageBadge = row => {
    const badge = row.querySelector('[data-menu-startpage-badge]');
    const startpageInput = row.querySelector('[data-menu-startpage]');
    const localeInput = row.querySelector('[data-menu-locale]');
    if (!badge) {
      return;
    }
    const locale = localeInput?.value?.trim();
    badge.hidden = !startpageInput?.checked;
    badge.textContent = locale ? `Startseite (${locale})` : 'Startseite';
  };

  const ensureRowIdentifier = row => {
    if (row.dataset.id) {
      return row.dataset.id;
    }
    if (!row.dataset.tempId) {
      row.dataset.tempId = `tmp-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }
    return row.dataset.tempId;
  };

  const appendMessage = (currentMessage, message) => {
    if (!currentMessage) {
      return message;
    }
    if (currentMessage.includes(message)) {
      return currentMessage;
    }
    return `${currentMessage} ${message}`;
  };

  const validateRows = () => {
    const result = { errors: [], warnings: [] };
    const rows = Array.from(itemsBody.querySelectorAll('tr[data-menu-row]'))
      .map(row => ({ id: ensureRowIdentifier(row), row, data: resolveRowData(row) }));

    const labelCounts = new Map();
    const slugCounts = new Map();
    const rowById = new Map();

    rows.forEach(entry => {
      const labelKey = `${normalizeLocaleValue(entry.data.locale)}|${(entry.data.label || '').trim().toLowerCase()}`;
      const slugValue = normalizeSlug(entry.data.href);
      const slugKey = `${normalizeLocaleValue(entry.data.locale)}|${slugValue}`;
      rowById.set(entry.id, entry);
      labelCounts.set(labelKey, (labelCounts.get(labelKey) || 0) + (entry.data.label ? 1 : 0));
      if (slugValue) {
        slugCounts.set(slugKey, (slugCounts.get(slugKey) || 0) + 1);
      }
    });

    const parentMap = new Map();
    rows.forEach(entry => {
      if (entry.data.parentId) {
        parentMap.set(entry.id, String(entry.data.parentId));
      }
    });

    const visited = new Set();
    const stack = new Set();
    const cycleNodes = new Set();
    const detectCycle = id => {
      if (!id || visited.has(id)) {
        return;
      }
      visited.add(id);
      stack.add(id);
      const parentId = parentMap.get(id);
      if (parentId) {
        if (stack.has(parentId)) {
          cycleNodes.add(id);
          cycleNodes.add(parentId);
        } else {
          detectCycle(parentId);
        }
      }
      stack.delete(id);
    };
    rows.forEach(entry => detectCycle(entry.id));

    const startpageLocales = new Map();

    rows.forEach(entry => {
      const { row, data } = entry;
      const labelInput = row.querySelector('[data-menu-label]');
      const labelError = row.querySelector('[data-menu-label-error]');
      const hrefInput = row.querySelector('[data-menu-href]');
      const hrefError = row.querySelector('[data-menu-link-error]');
      const parentSelect = row.querySelector('[data-menu-parent]');
      const parentError = row.querySelector('[data-menu-parent-error]');
      const startpageInput = row.querySelector('[data-menu-startpage]');
      const localeInput = row.querySelector('[data-menu-locale]');

      const labelMessage = data.label ? '' : 'Label ist erforderlich.';
      setValidationState(labelInput, labelError, labelMessage);

      const hrefMessage = data.href ? validateHref(data.href) : 'Link ist erforderlich.';
      setValidationState(hrefInput, hrefError, hrefMessage);

      setValidationState(parentSelect, parentError, '');

      if (labelMessage) {
        result.errors.push(labelMessage);
      }
      if (hrefMessage) {
        result.errors.push(hrefMessage);
      }

      const labelKey = `${normalizeLocaleValue(data.locale)}|${(data.label || '').trim().toLowerCase()}`;
      if (data.label && labelCounts.get(labelKey) > 1) {
        const message = 'Label ist mehrfach vergeben.';
        setValidationState(labelInput, labelError, appendMessage(labelError?.textContent, message));
        result.errors.push(message);
      }

      const slug = normalizeSlug(data.href);
      if (slug && slugCounts.get(`${normalizeLocaleValue(data.locale)}|${slug}`) > 1) {
        const message = 'Slug/Link ist mehrfach vergeben.';
        setValidationState(hrefInput, hrefError, appendMessage(hrefError?.textContent, message));
        result.errors.push(message);
      }

      if (cycleNodes.has(entry.id)) {
        const message = 'Zyklische Eltern-Relation erkannt.';
        setValidationState(parentSelect, parentError, appendMessage(parentError?.textContent, message));
        result.errors.push(message);
      }

      if (data.parentId) {
        const parentEntry = rowById.get(String(data.parentId));
        if (!parentEntry) {
          const message = 'Ausgewählter Elternknoten existiert nicht.';
          setValidationState(parentSelect, parentError, appendMessage(parentError?.textContent, message));
          result.errors.push(message);
        } else {
          const parentLocale = normalizeLocaleValue(parentEntry.data.locale);
          const locale = normalizeLocaleValue(data.locale);
          if (parentLocale && locale && parentLocale !== locale) {
            setValidationState(
              parentSelect,
              parentError,
              appendMessage(parentError?.textContent, namespaceMismatchMessage)
            );
            result.errors.push(namespaceMismatchMessage);
          }
        }
      }

      if (startpageInput?.checked) {
        const localeKey = normalizeLocaleValue(localeInput?.value || '');
        const entries = startpageLocales.get(localeKey) || [];
        entries.push(row);
        startpageLocales.set(localeKey, entries);
      }

      updateStartpageBadge(row);
    });

    startpageLocales.forEach((list, locale) => {
      if (list.length > 1) {
        const localeLabel = locale || 'alle Locales';
        result.warnings.push(`Mehrere Startseiten für ${localeLabel} gesetzt.`);
      }
    });

    const messageList = result.errors.length
      ? [...new Set(result.errors)]
      : [...new Set(result.warnings)];
    if (messageList.length) {
      setFeedback(messageList.join(' '), result.errors.length ? 'danger' : 'warning');
    } else {
      hideFeedback();
    }

    return result;
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

  const formatPreviewHref = href => {
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
        href.textContent = formatPreviewHref(node.href || '');

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

    const tree = renderHamburgerPreview(roots);
    previewTree.appendChild(tree);
    updateSummary();
  };

  const collectPreviewItems = () => {
    const rows = Array.from(itemsBody?.querySelectorAll('tr[data-menu-row]') || []);
    return rows.map((row, index) => {
      const id = row.dataset.id || `tmp-${index}`;
      const parentSelect = row.querySelector('[data-menu-parent]');
      const parentValue = parentSelect?.value || row.dataset.parentId || '';
      const layoutSelect = row.querySelector('[data-menu-layout]');
      const hrefInput = row.querySelector('[data-menu-href]');
      const labelInput = row.querySelector('[data-menu-label]');
      const localeInput = row.querySelector('[data-menu-locale]');
      const startpageInput = row.querySelector('[data-menu-startpage]');
      const externalInput = row.querySelector('[data-menu-external]');
      const activeInput = row.querySelector('[data-menu-active]');

      return {
        id,
        parentId: parentValue || null,
        position: index,
        label: labelInput?.value || '',
        href: hrefInput?.value || '',
        layout: layoutSelect?.value || 'link',
        locale: localeInput?.value || '',
        isStartpage: startpageInput?.checked || false,
        isExternal: externalInput?.checked || false,
        isActive: activeInput?.checked !== false,
      };
    });
  };

  const updatePreviewFromRows = () => {
    renderPreview(collectPreviewItems());
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
    ensureRowIdentifier(row);
    row.dataset.parentId = item?.parentId ? String(item.parentId) : '';

    const handleCell = document.createElement('td');
    handleCell.className = 'uk-table-shrink';
    const handleButton = document.createElement('button');
    handleButton.type = 'button';
    handleButton.className = 'uk-icon-button uk-button-default uk-button-small';
    handleButton.setAttribute('uk-icon', 'icon: menu');
    handleButton.setAttribute('aria-label', 'Sortieren');
    handleButton.dataset.menuDragHandle = 'true';
    handleCell.appendChild(handleButton);

    const labelCell = document.createElement('td');
    const labelWrapper = document.createElement('div');
    const labelInput = document.createElement('input');
    labelInput.className = 'uk-input uk-form-small';
    labelInput.type = 'text';
    labelInput.placeholder = 'Menütitel';
    labelInput.value = item?.label || '';
    labelInput.dataset.menuLabel = 'true';
    const labelError = document.createElement('div');
    labelError.className = 'uk-text-danger uk-text-small';
    labelError.dataset.menuLabelError = 'true';
    labelError.hidden = true;
    const startpageBadge = document.createElement('span');
    startpageBadge.className = 'uk-label uk-label-success uk-margin-small-right';
    startpageBadge.dataset.menuStartpageBadge = 'true';
    startpageBadge.hidden = item?.isStartpage !== true;
    startpageBadge.textContent = item?.locale
      ? `Startseite (${item.locale})`
      : 'Startseite';
    labelWrapper.style.paddingLeft = `${Math.max(0, Number(item?.depth || 0)) * 16}px`;
    labelWrapper.appendChild(startpageBadge);
    labelWrapper.appendChild(labelInput);
    labelWrapper.appendChild(labelError);
    labelCell.appendChild(labelWrapper);

    const hrefCell = document.createElement('td');
    const hrefInput = document.createElement('input');
    hrefInput.className = 'uk-input uk-form-small';
    hrefInput.type = 'text';
    hrefInput.placeholder = '/landing, #anker oder https://';
    hrefInput.value = item?.href || '';
    hrefInput.dataset.menuHref = 'true';
    if (hrefOptionsListId) {
      hrefInput.setAttribute('list', hrefOptionsListId);
    }
    const hrefError = document.createElement('div');
    hrefError.className = 'uk-text-danger uk-text-small';
    hrefError.dataset.menuLinkError = 'true';
    hrefError.hidden = true;
    hrefCell.appendChild(hrefInput);
    hrefCell.appendChild(hrefError);

    const parentCell = document.createElement('td');
    parentCell.className = 'uk-table-shrink';
    const parentSelect = createParentSelect(item);
    const parentError = document.createElement('div');
    parentError.className = 'uk-text-danger uk-text-small';
    parentError.dataset.menuParentError = 'true';
    parentError.hidden = true;
    parentCell.appendChild(parentSelect);
    parentCell.appendChild(parentError);

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

    const markDirty = () => {
      setRowDirty(row, true);
      updatePreviewFromRows();
    };
    labelInput.addEventListener('input', () => {
      markDirty();
      validateRows();
    });
    hrefInput.addEventListener('input', () => {
      markDirty();
      refreshRowValidation(row);
      validateRows();
    });
    iconSelect.addEventListener('change', event => {
      markDirty();
      updateIconPreview(iconPreview, event.target.value || '');
    });
    parentSelect.addEventListener('change', event => {
      markDirty();
      row.dataset.parentId = event.target.value || '';
      validateRows();
    });
    layoutSelect.addEventListener('change', markDirty);
    localeInput.addEventListener('input', () => {
      markDirty();
      validateRows();
    });
    startpageInput.addEventListener('change', () => {
      if (startpageInput.checked) {
        enforceSingleStartpage(row);
      }
      markDirty();
      updateStartpageBadge(row);
      validateRows();
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
    validateRows();

    row.draggable = true;

    return row;
  };

  const renderItems = items => {
    const tree = buildTree(items);
    state.tree = tree.roots;
    state.flatItems = flattenTree(tree.roots);
    state.descendants = buildDescendantsMap(tree.roots);
    renderPreview(items);

    itemsBody.innerHTML = '';
    if (!state.flatItems.length) {
      showEmpty('Keine Menüeinträge vorhanden.');
      return;
    }
    state.flatItems.forEach(item => {
      itemsBody.appendChild(createRow(item));
    });
    validateRows();
  };

  const loadMenuItems = locale => {
    hideFeedback();
    if (!state.menuId) {
      showEmpty('Erstelle zuerst ein Menü, um die Navigation zu bearbeiten.');
      return;
    }
    setLoading();
    const path = appendQueryParam(withNamespace(buildItemsPath(state.menuId)), 'locale', locale);
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

  const triggerAutoGeneration = overwrite => {
    if (!state.menuId) {
      setFeedback('Bitte zuerst ein Menü auswählen.', 'warning');
      return;
    }
    setFeedback('KI-Generierung ist für Menü-Definitionen aktuell nicht verfügbar.', 'warning');
  };

  const saveRow = row => {
    if (!state.menuId) {
      return;
    }
    const validation = validateRows();
    if (validation.errors.length) {
      setFeedback([...new Set(validation.errors)].join(' '), 'danger');
      return;
    }
    const data = resolveRowData(row);

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

    const path = data.id
      ? withNamespace(buildItemsPath(state.menuId, `/${data.id}`))
      : withNamespace(buildItemsPath(state.menuId));
    const method = data.id ? 'PATCH' : 'POST';

    apiFetch(path, {
      method,
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
          delete row.dataset.tempId;
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
      validateRows();
      if (!itemsBody.querySelector('tr[data-menu-row]')) {
        showEmpty('Keine Menüeinträge vorhanden.');
      }
      updatePreviewFromRows();
      return;
    }
    setRowBusy(row, true);
    apiFetch(withNamespace(buildItemsPath(state.menuId, `/${id}`)), {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' }
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
        validateRows();
        saveOrder();
        updatePreviewFromRows();
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
      const identifier = ensureRowIdentifier(row);
      const id = row.dataset.id ? Number(row.dataset.id) : null;
      const tempId = row.dataset.tempId || null;
      const parentKey = row.dataset.parentId || 'root';
      const current = parentPositions.get(parentKey) ?? 0;
      orderedItems.push({ identifier, id, tempId, position: current, parentId: parentKey });
      parentPositions.set(parentKey, current + 1);
    });

    return orderedItems;
  };

  const saveOrder = () => {
    if (!state.menuId) {
      return;
    }
    const orderedItems = buildOrderPayload();
    const signature = JSON.stringify(orderedItems);
    if (orderedItems.length < 2) {
      state.orderSignature = signature;
      return;
    }
    const hasChanged = signature !== state.orderSignature;
    if (!hasChanged) {
      return;
    }
    const persistedItems = orderedItems
      .filter(item => item.id)
      .map(item => ({ id: item.id, position: item.position }));
    if (persistedItems.length < 2) {
      state.orderSignature = signature;
      return;
    }
    const updates = persistedItems.map(item => {
      const row = itemsBody.querySelector(`tr[data-menu-row][data-id="${item.id}"]`);
      if (!row) {
        return Promise.resolve();
      }
      const data = resolveRowData(row);
      const payload = {
        label: data.label,
        href: data.href,
        icon: data.icon || null,
        parentId: data.parentId || null,
        layout: data.layout || 'link',
        detailTitle: data.detailTitle || null,
        detailText: data.detailText || null,
        detailSubline: data.detailSubline || null,
        position: item.position,
        isExternal: data.isExternal,
        locale: data.locale || null,
        isActive: data.isActive,
        isStartpage: data.isStartpage
      };
      return apiFetch(withNamespace(buildItemsPath(state.menuId, `/${item.id}`)), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(response => {
        if (!response.ok) {
          throw new Error('menu-sort-failed');
        }
        return response;
      });
    });

    Promise.all(updates)
      .then(() => {
        state.orderSignature = signature;
        hideFeedback();
      })
      .catch(error => {
        console.error('Failed to sort menu items', error);
        setFeedback('Sortieren fehlgeschlagen.', 'danger');
      });
  };

  const addRow = () => {
    if (!state.menuId) {
      setFeedback('Bitte zuerst ein Menü auswählen.', 'warning');
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
    updatePreviewFromRows();
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
      if (!row) {
        event.preventDefault();
        return;
      }
      const isHandle = event.target.closest('[data-menu-drag-handle]');
      if (!isHandle) {
        event.preventDefault();
        return;
      }
      const identifier = ensureRowIdentifier(row);
      draggingRow = row;
      draggingParentId = row.dataset.parentId || '';
      row.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', identifier);
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

  const updateSelectedMenu = () => {
    const selectedId = menuSelect?.value || manager.dataset.selectedMenuId || '';
    const menu = selectedId ? findMenuById(selectedId) : null;
    state.menuId = menu ? Number(menu.id) : null;
    setMenuLabel(menu);
    addButton.disabled = !state.menuId;
    if (exportButton) {
      exportButton.disabled = !state.menuId;
    }
    if (importButton) {
      importButton.disabled = !state.menuId;
    }
    if (generateButton) {
      generateButton.disabled = !state.menuId;
    }
    loadMenuItems(resolveLocale());
  };

  addButton.addEventListener('click', addRow);

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
    if (!file) {
      return;
    }
    handleImportFile(file);
  });

  generateButton?.addEventListener('click', () => {
    triggerAutoGeneration(false);
  });

  menuSelect?.addEventListener('change', () => {
    updateSelectedMenu();
  });

  localeSelect?.addEventListener('change', () => {
    manager.dataset.locale = localeSelect.value || '';
    loadMenuItems(resolveLocale());
  });

  window.addEventListener('marketing-menu:list-updated', event => {
    const updated = Array.isArray(event.detail?.menus) ? event.detail.menus : null;
    if (updated) {
      menusData = updated;
      updateSelectedMenu();
    }
  });

  attachDragHandlers();
  updateSelectedMenu();
  })();
}
