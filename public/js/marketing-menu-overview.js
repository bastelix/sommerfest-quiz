/* global UIkit */

const overview = document.querySelector('[data-marketing-menu-overview]');

if (overview) {
  (() => {
    const tableBody = overview.querySelector('[data-menu-overview-items]');
    const feedback = overview.querySelector('[data-menu-overview-feedback]');
    const createForm = overview.querySelector('[data-menu-create-form]');
    const menuSelect = document.getElementById('menuDefinitionSelect');
    const modalElement = document.getElementById('menuDefinitionModal');
    const modalForm = modalElement?.querySelector('[data-menu-modal-form]');
    const modalTitle = modalElement?.querySelector('[data-menu-modal-title]');
    const modalSubmit = modalElement?.querySelector('[data-menu-modal-submit]');

    if (!tableBody || !createForm || !menuSelect) {
      return;
    }

    const basePath = overview.dataset.basePath || window.basePath || '';

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

    const appendQueryParam = (path, key, value) => {
      if (!value) {
        return path;
      }
      const separator = path.includes('?') ? '&' : '?';
      return `${path}${separator}${encodeURIComponent(key)}=${encodeURIComponent(value)}`;
    };

    const resolveNamespace = () => {
      const select = document.getElementById('pageNamespaceSelect');
      const candidate = select?.value || select?.dataset.pageNamespace || overview.dataset.namespace || '';
      return String(candidate || '').trim();
    };

    const withNamespace = path => appendQueryParam(path, 'namespace', resolveNamespace());

    const buildMenusPath = (menuId = '') => {
      const suffix = menuId ? `/${menuId}` : '';
      const path = `/admin/menus${suffix}`;
      if (typeof window.apiFetch === 'function') {
        return path;
      }
      return `${basePath}${path}`;
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

    const parseInitialMenus = () => {
      try {
        const parsed = JSON.parse(overview.dataset.menus || '[]');
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        console.warn('Failed to parse menu overview dataset', error);
        return [];
      }
    };

    const state = {
      menus: parseInitialMenus(),
      activeId: menuSelect.value || menuSelect.dataset.selected || ''
    };

    const normalizeLabel = label => {
      const trimmed = String(label || '').trim();
      return trimmed || 'Neues Menü';
    };

    const renderMenuSelect = () => {
      const selected = menuSelect.value || state.activeId;
      menuSelect.innerHTML = '';
      if (!state.menus.length) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Keine Menüs vorhanden';
        menuSelect.appendChild(option);
        menuSelect.disabled = true;
        return;
      }
      menuSelect.disabled = false;
      state.menus.forEach(menu => {
        const option = document.createElement('option');
        option.value = String(menu.id);
        option.textContent = `${normalizeLabel(menu.label)} (${menu.locale || 'de'})`;
        if (String(menu.id) === String(selected)) {
          option.selected = true;
        }
        menuSelect.appendChild(option);
      });
      state.activeId = menuSelect.value || selected;
    };

    const dispatchMenuUpdate = () => {
      window.dispatchEvent(new CustomEvent('marketing-menu:list-updated', { detail: { menus: state.menus } }));
    };

    const renderTable = () => {
      tableBody.innerHTML = '';
      if (!state.menus.length) {
        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="6">Keine Menüs vorhanden.</td>';
        tableBody.appendChild(row);
        return;
      }
      state.menus.forEach(menu => {
        const row = document.createElement('tr');

        const labelCell = document.createElement('td');
        labelCell.textContent = normalizeLabel(menu.label);

        const localeCell = document.createElement('td');
        localeCell.textContent = menu.locale || 'de';

        const statusCell = document.createElement('td');
        statusCell.innerHTML = menu.isActive ? '<span class="uk-label uk-label-success">Aktiv</span>' : '<span class="uk-label">Inaktiv</span>';

        const usageCell = document.createElement('td');
        usageCell.className = 'uk-text-center';
        usageCell.textContent = String(menu.assignmentCount || 0);

        const updatedCell = document.createElement('td');
        updatedCell.textContent = menu.updatedAt ? new Date(menu.updatedAt).toLocaleString() : '—';

        const actionsCell = document.createElement('td');
        actionsCell.className = 'uk-text-right';

        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'uk-button uk-button-primary uk-button-small';
        editButton.textContent = 'Bearbeiten';
        editButton.addEventListener('click', () => {
          menuSelect.value = String(menu.id);
          menuSelect.dispatchEvent(new Event('change'));
          window.scrollTo({ top: overview.offsetTop, behavior: 'smooth' });
        });

        const renameButton = document.createElement('button');
        renameButton.type = 'button';
        renameButton.className = 'uk-button uk-button-default uk-button-small';
        renameButton.textContent = 'Umbenennen';
        renameButton.addEventListener('click', () => openModal('rename', menu));

        const duplicateButton = document.createElement('button');
        duplicateButton.type = 'button';
        duplicateButton.className = 'uk-button uk-button-default uk-button-small';
        duplicateButton.textContent = 'Duplizieren';
        duplicateButton.addEventListener('click', () => openModal('duplicate', menu));

        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'uk-button uk-button-danger uk-button-small';
        deleteButton.textContent = 'Löschen';
        deleteButton.addEventListener('click', () => deleteMenu(menu));

        const buttonGroup = document.createElement('div');
        buttonGroup.className = 'uk-button-group';
        buttonGroup.append(editButton, renameButton, duplicateButton, deleteButton);

        actionsCell.appendChild(buttonGroup);

        row.append(labelCell, localeCell, statusCell, usageCell, updatedCell, actionsCell);
        tableBody.appendChild(row);
      });
    };

    const updateMenuState = menus => {
      const previousSelection = menuSelect.value || '';
      state.menus = menus;
      renderMenuSelect();
      renderTable();
      if (menuSelect.value !== previousSelection) {
        menuSelect.dispatchEvent(new Event('change'));
      }
      dispatchMenuUpdate();
    };

    const openModal = (mode, menu) => {
      if (!modalForm || !modalElement) {
        return;
      }
      modalForm.reset();
      const menuIdInput = modalForm.querySelector('input[name="menuId"]');
      const modeInput = modalForm.querySelector('input[name="mode"]');
      const labelInput = modalForm.querySelector('input[name="label"]');
      const localeInput = modalForm.querySelector('input[name="locale"]');
      const activeInput = modalForm.querySelector('input[name="isActive"]');

      if (menuIdInput) {
        menuIdInput.value = menu ? String(menu.id) : '';
      }
      if (modeInput) {
        modeInput.value = mode;
      }
      if (labelInput) {
        labelInput.value = mode === 'duplicate'
          ? `${normalizeLabel(menu?.label)} Kopie`
          : normalizeLabel(menu?.label);
      }
      if (localeInput) {
        localeInput.value = menu?.locale || 'de';
      }
      if (activeInput) {
        activeInput.checked = menu?.isActive !== false;
      }
      if (modalTitle) {
        modalTitle.textContent = mode === 'duplicate' ? 'Menü duplizieren' : 'Menü umbenennen';
      }
      if (modalSubmit) {
        modalSubmit.textContent = mode === 'duplicate' ? 'Duplizieren' : 'Speichern';
      }
      if (typeof UIkit !== 'undefined' && UIkit.modal) {
        UIkit.modal(modalElement).show();
      }
    };

    const fetchMenuItems = async menuId => {
      const response = await apiFetch(withNamespace(buildItemsPath(menuId)), {
        headers: { Accept: 'application/json' }
      });
      if (!response.ok) {
        throw new Error('Menüeinträge konnten nicht geladen werden.');
      }
      const data = await response.json();
      return Array.isArray(data?.items) ? data.items : [];
    };

    const buildImportTree = items => {
      const byId = new Map();
      const roots = [];

      items.forEach(item => {
        if (item?.id === null || item?.id === undefined) {
          return;
        }
        byId.set(item.id, { ...item, children: [] });
      });

      items.forEach(item => {
        if (item?.id === null || item?.id === undefined) {
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
        throw new Error(errorBody?.error || 'Duplizieren fehlgeschlagen.');
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

    const duplicateMenuItems = async (sourceMenuId, targetMenuId) => {
      const items = await fetchMenuItems(sourceMenuId);
      if (!items.length) {
        return;
      }
      const tree = buildImportTree(items);
      await createMenuItemsFromTree(targetMenuId, tree);
    };

    const deleteMenu = async menu => {
      const assignmentCount = Number(menu.assignmentCount || 0);
      const confirmMessage = assignmentCount > 0
        ? `Dieses Menü ist ${assignmentCount}× zugewiesen. Trotzdem löschen?`
        : 'Menü wirklich löschen?';
      if (!window.confirm(confirmMessage)) {
        return;
      }
      try {
        const response = await apiFetch(withNamespace(buildMenusPath(menu.id)), { method: 'DELETE' });
        if (!response.ok && response.status !== 204) {
          throw new Error('Löschen fehlgeschlagen.');
        }
        updateMenuState(state.menus.filter(entry => entry.id !== menu.id));
        setFeedback('Menü gelöscht.', 'success');
      } catch (error) {
        console.error('Failed to delete menu', error);
        setFeedback(error?.message || 'Löschen fehlgeschlagen.', 'danger');
      }
    };

    createForm.addEventListener('submit', async event => {
      event.preventDefault();
      const formData = new FormData(createForm);
      const label = normalizeLabel(formData.get('label'));
      const locale = String(formData.get('locale') || 'de').trim().toLowerCase() || 'de';
      const isActive = formData.get('isActive') === 'on';

      try {
        const response = await apiFetch(withNamespace(buildMenusPath()), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ label, locale, isActive })
        });
        if (!response.ok) {
          const body = await response.json().catch(() => ({}));
          throw new Error(body?.error || 'Anlegen fehlgeschlagen.');
        }
        const data = await response.json();
        const menu = data?.menu;
        if (menu) {
          menu.assignmentCount = 0;
          updateMenuState([...state.menus, menu]);
          menuSelect.value = String(menu.id);
          menuSelect.dispatchEvent(new Event('change'));
          createForm.reset();
          createForm.querySelector('input[name="isActive"]').checked = true;
          setFeedback('Menü angelegt.', 'success');
        }
      } catch (error) {
        console.error('Failed to create menu', error);
        setFeedback(error?.message || 'Anlegen fehlgeschlagen.', 'danger');
      }
    });

    modalForm?.addEventListener('submit', async event => {
      event.preventDefault();
      const formData = new FormData(modalForm);
      const menuId = Number(formData.get('menuId'));
      const mode = String(formData.get('mode') || 'rename');
      const label = normalizeLabel(formData.get('label'));
      const locale = String(formData.get('locale') || 'de').trim().toLowerCase() || 'de';
      const isActive = formData.get('isActive') === 'on';

      if (!menuId || !Number.isFinite(menuId)) {
        return;
      }

      try {
        if (mode === 'duplicate') {
          const response = await apiFetch(withNamespace(buildMenusPath()), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ label, locale, isActive })
          });
          if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body?.error || 'Duplizieren fehlgeschlagen.');
          }
          const data = await response.json();
          const newMenu = data?.menu;
          if (newMenu) {
            newMenu.assignmentCount = 0;
            try {
              await duplicateMenuItems(menuId, newMenu.id);
              setFeedback('Menü dupliziert.', 'success');
            } catch (copyError) {
              console.error('Failed to duplicate menu items', copyError);
              setFeedback('Menü wurde angelegt, Inhalte konnten nicht kopiert werden.', 'warning');
            }
            updateMenuState([...state.menus, newMenu]);
            menuSelect.value = String(newMenu.id);
            menuSelect.dispatchEvent(new Event('change'));
          }
        } else {
          const response = await apiFetch(withNamespace(buildMenusPath(menuId)), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ label, locale, isActive })
          });
          if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body?.error || 'Aktualisieren fehlgeschlagen.');
          }
          const data = await response.json();
          const updated = data?.menu;
          if (updated) {
            const next = state.menus.map(entry => (entry.id === updated.id
              ? { ...entry, ...updated }
              : entry));
            updateMenuState(next);
            setFeedback('Menü aktualisiert.', 'success');
          }
        }
        if (typeof UIkit !== 'undefined' && UIkit.modal) {
          UIkit.modal(modalElement).hide();
        }
      } catch (error) {
        console.error('Failed to update menu', error);
        setFeedback(error?.message || 'Speichern fehlgeschlagen.', 'danger');
      }
    });

    menuSelect.addEventListener('change', () => {
      state.activeId = menuSelect.value || '';
    });

    renderMenuSelect();
    renderTable();
    dispatchMenuUpdate();
  })();
}
