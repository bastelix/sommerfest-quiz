/* global UIkit */

const overridesDetail = document.querySelector('[data-menu-overrides-detail]');

if (overridesDetail) {
  (() => {
    const feedback = overridesDetail.querySelector('[data-menu-overrides-feedback]');
    const localeSelect = overridesDetail.querySelector('[data-override-locale-select]');
    const headerMenuSelect = overridesDetail.querySelector('[data-header-menu-select]');
    const footerOverrideToggle = overridesDetail.querySelector('[data-footer-override-toggle]');
    const footerSelects = Array.from(overridesDetail.querySelectorAll('[data-footer-override-select]'));

    const footerSlots = ['footer_1', 'footer_2', 'footer_3'];
    const basePath = overridesDetail.dataset.basePath || window.basePath || '';
    const pageId = Number(overridesDetail.dataset.pageId || 0);

    const resolveNamespace = () => {
      const select = document.getElementById('pageNamespaceSelect');
      const candidate = select?.value || overridesDetail.dataset.namespace || '';
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
      footerAssignments: new Map()
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
        const slot = select.dataset.footerOverrideSelect || '';
        const assignment = state.footerAssignments.get(slot);
        select.value = assignment?.menuId ? String(assignment.menuId) : '';
        select.dataset.assignmentId = assignment?.id ? String(assignment.id) : '';
      });
    };

    const updateFooterToggle = () => {
      if (!footerOverrideToggle) {
        return;
      }
      const hasOverride = footerSlots.some(slot => state.footerAssignments.has(slot));
      footerOverrideToggle.checked = hasOverride;
      footerSelects.forEach(select => {
        const isLocked = select.dataset.lockedDisabled === 'true';
        select.disabled = !hasOverride || isLocked;
      });
      if (!hasOverride) {
        footerSelects.forEach(select => {
          select.value = '';
          select.dataset.assignmentId = '';
        });
      }
    };

    const loadAssignments = async () => {
      if (!pageId || !localeSelect) {
        return;
      }
      const namespace = resolveNamespace();
      const locale = String(localeSelect.value || '').trim();
      if (!namespace || !locale) {
        state.headerAssignment = null;
        state.footerAssignments.clear();
        updateHeaderSelect();
        updateFooterSelects();
        updateFooterToggle();
        return;
      }
      try {
        const assignments = await requestAssignments({
          namespace,
          pageId,
          locale,
          includeInactive: 1
        });
        state.headerAssignment = assignments.find(
          assignment => assignment && assignment.isActive && assignment.slot === 'main'
        ) || null;
        state.footerAssignments = new Map(
          assignments
            .filter(assignment => assignment && assignment.isActive)
            .filter(assignment => footerSlots.includes(assignment.slot))
            .map(assignment => [assignment.slot, assignment])
        );
        updateHeaderSelect();
        updateFooterSelects();
        updateFooterToggle();
      } catch (error) {
        setFeedback('Zuweisungen konnten nicht geladen werden.', 'warning');
      }
    };

    const saveAssignment = async ({ assignmentId, menuId, slot, locale }) => {
      const namespace = resolveNamespace();
      if (!namespace || !menuId || !slot || !locale) {
        throw new Error('menu-assignment-invalid');
      }
      const payload = {
        menuId,
        pageId,
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
      if (!headerMenuSelect || !localeSelect) {
        return;
      }
      hideFeedback();
      const locale = String(localeSelect.value || '').trim();
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
        setFeedback('Header-Override gespeichert.', 'success');
      } catch (error) {
        setFeedback('Header-Override konnte nicht gespeichert werden.', 'danger');
      }
    };

    const handleFooterOverrideToggle = async () => {
      if (!footerOverrideToggle) {
        return;
      }
      hideFeedback();
      if (footerOverrideToggle.checked) {
        footerSelects.forEach(select => {
          if (select.dataset.lockedDisabled !== 'true') {
            select.disabled = false;
          }
        });
        return;
      }
      const assignmentsToDelete = footerSlots
        .map(slot => state.footerAssignments.get(slot))
        .filter(Boolean);
      try {
        for (const assignment of assignmentsToDelete) {
          await deleteAssignment(assignment.id);
          state.footerAssignments.delete(assignment.slot);
        }
        updateFooterToggle();
        setFeedback('Footer-Overrides entfernt.', 'success');
      } catch (error) {
        setFeedback('Footer-Overrides konnten nicht entfernt werden.', 'danger');
      }
    };

    const handleFooterChange = async (event) => {
      const select = event.target;
      if (!select || !footerOverrideToggle?.checked || !localeSelect) {
        return;
      }
      hideFeedback();
      const slot = select.dataset.footerOverrideSelect || '';
      const locale = String(localeSelect.value || '').trim();
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
        updateFooterToggle();
        setFeedback('Footer-Override gespeichert.', 'success');
      } catch (error) {
        setFeedback('Footer-Override konnte nicht gespeichert werden.', 'danger');
      }
    };

    const refreshLocaleFilters = () => {
      filterMenuOptions(headerMenuSelect, localeSelect?.value);
      footerSelects.forEach(select => filterMenuOptions(select, localeSelect?.value));
    };

    localeSelect?.addEventListener('change', () => {
      refreshLocaleFilters();
      loadAssignments();
    });
    headerMenuSelect?.addEventListener('change', handleHeaderChange);
    footerOverrideToggle?.addEventListener('change', handleFooterOverrideToggle);
    footerSelects.forEach(select => {
      select.addEventListener('change', handleFooterChange);
    });

    const namespaceSelect = document.getElementById('pageNamespaceSelect');
    if (namespaceSelect) {
      namespaceSelect.addEventListener('change', loadAssignments);
    }

    refreshLocaleFilters();
    loadAssignments();
  })();
}
