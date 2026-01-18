/* global UIkit */

const standardsContainer = document.querySelector('[data-menu-standards]');

if (standardsContainer) {
  (() => {
    const feedback = standardsContainer.querySelector('[data-menu-standards-feedback]');
    const headerLocaleSelect = standardsContainer.querySelector('[data-header-locale-select]');
    const headerMenuSelect = standardsContainer.querySelector('[data-header-menu-select]');
    const footerLocaleSelect = standardsContainer.querySelector('[data-footer-locale-select]');
    const footerSelects = Array.from(standardsContainer.querySelectorAll('[data-footer-select]'));

    const footerSlots = ['footer_1', 'footer_2', 'footer_3'];
    const basePath = standardsContainer.dataset.basePath || window.basePath || '';

    const resolveNamespace = () => {
      const select = document.getElementById('pageNamespaceSelect');
      const candidate = select?.value || standardsContainer.dataset.namespace || '';
      return String(candidate || '').trim();
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

    const buildAssignmentsPath = (params = {}) => {
      const searchParams = new URLSearchParams();
      Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
          return;
        }
        searchParams.set(key, String(value));
      });
      const query = searchParams.toString();
      const path = `/admin/menu-assignments${query ? `?${query}` : ''}`;
      if (typeof window.apiFetch === 'function') {
        return path;
      }
      return `${basePath}${path}`;
    };

    const withNamespace = (path) => {
      const namespace = resolveNamespace();
      if (!namespace) {
        return path;
      }
      const separator = path.includes('?') ? '&' : '?';
      return `${path}${separator}namespace=${encodeURIComponent(namespace)}`;
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

    const filterMenuOptions = (select, locale) => {
      if (!select) {
        return;
      }
      const normalizedLocale = String(locale || '').trim().toLowerCase();
      Array.from(select.options).forEach(option => {
        if (!option) {
          return;
        }
        if (option.value === '') {
          option.hidden = false;
          return;
        }
        const optionLocale = String(option.dataset.menuLocale || '').trim().toLowerCase();
        option.hidden = normalizedLocale !== '' && optionLocale !== '' && optionLocale !== normalizedLocale;
      });
      const selectedOption = select.options[select.selectedIndex];
      if (selectedOption && selectedOption.hidden) {
        select.value = '';
      }
    };

    const requestAssignments = async (params) => {
      const response = await apiFetch(buildAssignmentsPath(params));
      if (!response.ok) {
        throw new Error('menu-assignments-request-failed');
      }
      const payload = await response.json();
      return Array.isArray(payload?.assignments) ? payload.assignments : [];
    };

    const state = {
      headerAssignment: null,
      footerAssignments: new Map(),
    };

    const updateHeaderSelect = () => {
      if (!headerMenuSelect) {
        return;
      }
      const assignment = state.headerAssignment;
      headerMenuSelect.value = assignment?.menuId ? String(assignment.menuId) : '';
      headerMenuSelect.dataset.assignmentId = assignment?.id ? String(assignment.id) : '';
    };

    const updateFooterSelects = () => {
      footerSelects.forEach(select => {
        const slot = select.dataset.footerSelect || '';
        const assignment = state.footerAssignments.get(slot);
        select.value = assignment?.menuId ? String(assignment.menuId) : '';
        select.dataset.assignmentId = assignment?.id ? String(assignment.id) : '';
      });
    };

    const loadHeaderStandard = async () => {
      if (!headerLocaleSelect) {
        return;
      }
      const namespace = resolveNamespace();
      const locale = String(headerLocaleSelect.value || '').trim();
      if (!namespace || !locale) {
        state.headerAssignment = null;
        updateHeaderSelect();
        return;
      }
      try {
        const assignments = await requestAssignments({
          namespace,
          locale,
          includeInactive: 1
        });
        state.headerAssignment = assignments.find(
          assignment => assignment && assignment.pageId === null && assignment.isActive && assignment.slot === 'main'
        ) || null;
        updateHeaderSelect();
      } catch (error) {
        setFeedback('Header-Standard konnte nicht geladen werden.', 'warning');
      }
    };

    const loadFooterStandards = async () => {
      if (!footerLocaleSelect) {
        return;
      }
      const namespace = resolveNamespace();
      const locale = String(footerLocaleSelect.value || '').trim();
      if (!namespace || !locale) {
        state.footerAssignments.clear();
        updateFooterSelects();
        return;
      }
      try {
        const assignments = await requestAssignments({
          namespace,
          locale,
          includeInactive: 1
        });
        state.footerAssignments = new Map(
          assignments
            .filter(assignment => assignment && assignment.pageId === null && assignment.isActive)
            .filter(assignment => footerSlots.includes(assignment.slot))
            .map(assignment => [assignment.slot, assignment])
        );
        updateFooterSelects();
      } catch (error) {
        setFeedback('Footer-Standards konnten nicht geladen werden.', 'warning');
      }
    };

    const saveAssignment = async ({ assignmentId, menuId, slot, locale }) => {
      const namespace = resolveNamespace();
      if (!namespace || !menuId || !slot || !locale) {
        throw new Error('menu-assignment-invalid');
      }
      const payload = {
        menuId,
        pageId: null,
        slot,
        locale,
        isActive: true
      };
      const isUpdate = Boolean(assignmentId);
      const endpoint = withNamespace(
        isUpdate ? `/admin/menu-assignments/${assignmentId}` : '/admin/menu-assignments'
      );
      const path = typeof window.apiFetch === 'function' ? endpoint : `${basePath}${endpoint}`;
      const response = await apiFetch(path, {
        method: isUpdate ? 'PATCH' : 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      if (!response.ok) {
        throw new Error('menu-assignment-save-failed');
      }
      const result = await response.json();
      return result?.assignment || null;
    };

    const deleteAssignment = async (assignmentId) => {
      if (!assignmentId) {
        return;
      }
      const endpoint = withNamespace(`/admin/menu-assignments/${assignmentId}`);
      const path = typeof window.apiFetch === 'function' ? endpoint : `${basePath}${endpoint}`;
      const response = await apiFetch(path, { method: 'DELETE' });
      if (!response.ok && response.status !== 404) {
        throw new Error('menu-assignment-delete-failed');
      }
    };

    const handleHeaderChange = async () => {
      if (!headerMenuSelect || !headerLocaleSelect) {
        return;
      }
      hideFeedback();
      const locale = String(headerLocaleSelect.value || '').trim();
      if (!locale) {
        setFeedback('Bitte Locale auswählen.', 'warning');
        return;
      }
      const menuId = Number(headerMenuSelect.value || 0);
      const assignment = state.headerAssignment;
      try {
        if (!menuId) {
          if (assignment?.id) {
            await deleteAssignment(assignment.id);
          }
          state.headerAssignment = null;
        } else {
          const saved = await saveAssignment({
            assignmentId: assignment?.id,
            menuId,
            slot: 'main',
            locale
          });
          state.headerAssignment = saved;
        }
        updateHeaderSelect();
        setFeedback('Header-Standard gespeichert.', 'success');
      } catch (error) {
        setFeedback('Header-Standard konnte nicht gespeichert werden.', 'danger');
      }
    };

    const handleFooterChange = async (event) => {
      const select = event.target;
      if (!select || !footerLocaleSelect) {
        return;
      }
      hideFeedback();
      const slot = select.dataset.footerSelect || '';
      const locale = String(footerLocaleSelect.value || '').trim();
      if (!slot || !locale) {
        setFeedback('Bitte Locale auswählen.', 'warning');
        return;
      }
      const menuId = Number(select.value || 0);
      const assignment = state.footerAssignments.get(slot);
      try {
        if (!menuId) {
          if (assignment?.id) {
            await deleteAssignment(assignment.id);
          }
          state.footerAssignments.delete(slot);
        } else {
          const saved = await saveAssignment({
            assignmentId: assignment?.id,
            menuId,
            slot,
            locale
          });
          if (saved) {
            state.footerAssignments.set(slot, saved);
          }
        }
        updateFooterSelects();
        setFeedback('Footer-Standard gespeichert.', 'success');
      } catch (error) {
        setFeedback('Footer-Standard konnte nicht gespeichert werden.', 'danger');
      }
    };

    const refreshLocaleFilters = () => {
      filterMenuOptions(headerMenuSelect, headerLocaleSelect?.value);
      const footerLocale = footerLocaleSelect?.value;
      footerSelects.forEach(select => filterMenuOptions(select, footerLocale));
    };

    if (headerLocaleSelect) {
      headerLocaleSelect.addEventListener('change', () => {
        refreshLocaleFilters();
        loadHeaderStandard();
      });
    }
    if (headerMenuSelect) {
      headerMenuSelect.addEventListener('change', handleHeaderChange);
    }
    if (footerLocaleSelect) {
      footerLocaleSelect.addEventListener('change', () => {
        refreshLocaleFilters();
        loadFooterStandards();
      });
    }
    footerSelects.forEach(select => {
      select.addEventListener('change', handleFooterChange);
    });

    const namespaceSelect = document.getElementById('pageNamespaceSelect');
    if (namespaceSelect) {
      namespaceSelect.addEventListener('change', () => {
        loadHeaderStandard();
        loadFooterStandards();
      });
    }

    refreshLocaleFilters();
    loadHeaderStandard();
    loadFooterStandards();
  })();
}
