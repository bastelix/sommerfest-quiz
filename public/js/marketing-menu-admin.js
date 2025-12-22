/* global UIkit */

const manager = document.querySelector('[data-marketing-menu-manager]');

if (manager) {
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
  const pageLabel = manager.querySelector('[data-menu-page-label]');
  const addButton = manager.querySelector('[data-menu-add]');
  const itemsBody = manager.querySelector('[data-menu-items]');
  const feedback = manager.querySelector('[data-menu-feedback]');
  const loadingRow = manager.querySelector('[data-menu-loading-row]');

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
  const allowedSchemes = ['http', 'https', 'mailto', 'tel'];

  if (!itemsBody || !addButton) {
    console.warn('Marketing menu manager requirements missing.');
    return;
  }

  const state = {
    pageId: null,
    pageSlug: '',
    items: [],
    order: []
  };

  const findPageBySlug = slug => pagesData.find(page => page.slug === slug) || null;
  const resolveNamespace = () => {
    const select = document.getElementById('pageNamespaceSelect');
    const candidate = select?.value || manager.dataset.namespace || window.pageNamespace || '';
    return String(candidate || '').trim();
  };
  const withNamespace = path => {
    const namespace = resolveNamespace();
    if (!namespace) {
      return path;
    }
    const separator = path.includes('?') ? '&' : '?';
    return `${path}${separator}namespace=${encodeURIComponent(namespace)}`;
  };

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

  const buildPath = path => {
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
      pageLabel.textContent = 'Keine Marketing-Seite ausgewählt.';
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
    fallback.innerHTML = '<td colspan="8">Lädt…</td>';
    itemsBody.appendChild(fallback);
  };

  const showEmpty = message => {
    itemsBody.innerHTML = '';
    const row = document.createElement('tr');
    row.dataset.menuEmpty = 'true';
    const cell = document.createElement('td');
    cell.colSpan = 8;
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
    const localeInput = row.querySelector('[data-menu-locale]');
    const externalInput = row.querySelector('[data-menu-external]');
    const activeInput = row.querySelector('[data-menu-active]');
    return {
      id: row.dataset.id ? Number(row.dataset.id) : null,
      label: labelInput?.value.trim() || '',
      href: hrefInput?.value.trim() || '',
      icon: iconSelect?.value.trim() || '',
      locale: localeInput?.value.trim() || '',
      isExternal: !!externalInput?.checked,
      isActive: !!activeInput?.checked
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

  const createRow = item => {
    const row = document.createElement('tr');
    row.dataset.menuRow = 'true';
    if (item?.id) {
      row.dataset.id = String(item.id);
    }

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
    const labelInput = document.createElement('input');
    labelInput.className = 'uk-input uk-form-small';
    labelInput.type = 'text';
    labelInput.placeholder = 'Menütitel';
    labelInput.value = item?.label || '';
    labelInput.dataset.menuLabel = 'true';
    labelCell.appendChild(labelInput);

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
    localeCell.appendChild(localeInput);

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
      iconCell,
      localeCell,
      externalCell,
      activeCell,
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
    localeInput.addEventListener('input', markDirty);
    externalInput.addEventListener('change', markDirty);
    activeInput.addEventListener('change', markDirty);

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
    itemsBody.innerHTML = '';
    if (!items.length) {
      showEmpty('Keine Menüeinträge vorhanden.');
      return;
    }
    items.forEach(item => {
      itemsBody.appendChild(createRow(item));
    });
  };

  const loadMenuItems = () => {
    hideFeedback();
    if (!state.pageId) {
      showEmpty('Bitte eine Marketing-Seite auswählen.');
      return;
    }
    setLoading();
    apiFetch(withNamespace(buildPath(`/admin/pages/${state.pageId}/menu`)))
      .then(response => {
        if (!response.ok) {
          throw new Error('menu-load-failed');
        }
        return response.json();
      })
      .then(data => {
        const items = Array.isArray(data?.items) ? data.items.slice() : [];
        items.sort((a, b) => {
          const posA = Number.isFinite(Number(a.position)) ? Number(a.position) : 0;
          const posB = Number.isFinite(Number(b.position)) ? Number(b.position) : 0;
          return posA - posB;
        });
        state.items = items;
        state.order = items.map(item => item.id);
        renderItems(items);
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

    const payload = {
      label: data.label,
      href: data.href,
      icon: data.icon || null,
      position: Array.from(itemsBody.querySelectorAll('tr[data-menu-row]')).indexOf(row) + 1,
      isExternal: data.isExternal,
      locale: data.locale || null,
      isActive: data.isActive
    };
    if (data.id) {
      payload.id = data.id;
    }

    apiFetch(withNamespace(buildPath(`/admin/pages/${state.pageId}/menu`)), {
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
        row.querySelector('[data-menu-icon]').value = item.icon || '';
        row.querySelector('[data-menu-locale]').value = item.locale || '';
        row.querySelector('[data-menu-external]').checked = item.isExternal === true;
        row.querySelector('[data-menu-active]').checked = item.isActive !== false;
        const iconPreview = row.querySelector('[data-menu-icon-preview]');
        updateIconPreview(iconPreview, item.icon || '');
        row.querySelector('[data-menu-save]').textContent = 'Speichern';
        setRowDirty(row, false);
        hideFeedback();
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
    apiFetch(withNamespace(buildPath(`/admin/pages/${state.pageId}/menu`)), {
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

  const saveOrder = () => {
    if (!state.pageId) {
      return;
    }
    const ids = Array.from(itemsBody.querySelectorAll('tr[data-menu-row]'))
      .map(row => row.dataset.id)
      .filter(Boolean)
      .map(id => Number(id));
    if (ids.length < 2) {
      state.order = ids;
      return;
    }
    const hasChanged = ids.length !== state.order.length
      || ids.some((id, index) => id !== state.order[index]);
    if (!hasChanged) {
      return;
    }
    apiFetch(withNamespace(buildPath(`/admin/pages/${state.pageId}/menu/sort`)), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orderedIds: ids })
    })
      .then(response => {
        if (!response.ok && response.status !== 204) {
          throw new Error('menu-sort-failed');
        }
        state.order = ids;
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
      locale: '',
      isExternal: false,
      isActive: true
    });
    itemsBody.appendChild(row);
    row.querySelector('[data-menu-label]').focus();
  };

  const attachDragHandlers = () => {
    let draggingRow = null;

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
      row.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', row.dataset.id);
    });

    itemsBody.addEventListener('dragover', event => {
      if (!draggingRow) {
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
    loadMenuItems();
  };

  addButton.addEventListener('click', addRow);

  pageSelect?.addEventListener('change', () => {
    updateSelectedPage();
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
}
