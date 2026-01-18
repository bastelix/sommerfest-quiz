/* global UIkit */

const assignmentsContainer = document.querySelector('[data-menu-assignments]');

if (assignmentsContainer) {
  (() => {
    const feedback = assignmentsContainer.querySelector('[data-menu-assignments-feedback]');
    const headerPageSelect = assignmentsContainer.querySelector('[data-header-page-select]');
    const headerLocaleSelect = assignmentsContainer.querySelector('[data-header-locale-select]');
    const headerMenuSelect = assignmentsContainer.querySelector('[data-header-menu-select]');
    const footerGlobalLocaleSelect = assignmentsContainer.querySelector('[data-footer-global-locale-select]');
    const footerGlobalSelects = Array.from(assignmentsContainer.querySelectorAll('[data-footer-global-select]'));
    const footerPageSelect = assignmentsContainer.querySelector('[data-footer-page-select]');
    const footerLocaleSelect = assignmentsContainer.querySelector('[data-footer-locale-select]');
    const footerOverrideToggle = assignmentsContainer.querySelector('[data-footer-override-toggle]');
    const footerOverrideSelects = Array.from(assignmentsContainer.querySelectorAll('[data-footer-override-select]'));

    const footerSlots = ['footer_1', 'footer_2', 'footer_3'];
    const basePath = assignmentsContainer.dataset.basePath || window.basePath || '';

    const resolveNamespace = () => {
      const select = document.getElementById('pageNamespaceSelect');
      const candidate = select?.value || assignmentsContainer.dataset.namespace || '';
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
      footerPageAssignments: new Map(),
      globalFooterAssignments: new Map()
    };

    const updateHeaderSelect = () => {
      if (!headerMenuSelect) {
        return;
      }
      const assignment = state.headerAssignment;
      headerMenuSelect.value = assignment?.menuId ? String(assignment.menuId) : '';
      headerMenuSelect.dataset.assignmentId = assignment?.id ? String(assignment.id) : '';
    };

    const updateFooterOverrideToggle = () => {
      if (!footerOverrideToggle) {
        return;
      }
      const hasOverride = footerSlots.some(slot => state.footerPageAssignments.has(slot));
      footerOverrideToggle.checked = hasOverride;
      footerOverrideSelects.forEach(select => {
        const isLocked = select.dataset.lockedDisabled === 'true';
        select.disabled = !hasOverride || isLocked;
      });
      if (!hasOverride) {
        footerOverrideSelects.forEach(select => {
          select.value = '';
          select.dataset.assignmentId = '';
        });
      }
    };

    const updateFooterOverrideSelects = () => {
      footerOverrideSelects.forEach(select => {
        const slot = select.dataset.footerOverrideSelect || '';
        const assignment = state.footerPageAssignments.get(slot);
        select.value = assignment?.menuId ? String(assignment.menuId) : '';
        select.dataset.assignmentId = assignment?.id ? String(assignment.id) : '';
      });
    };

    const updateFooterGlobalSelects = () => {
      footerGlobalSelects.forEach(select => {
        const slot = select.dataset.footerGlobalSelect || '';
        const assignment = state.globalFooterAssignments.get(slot);
        select.value = assignment?.menuId ? String(assignment.menuId) : '';
        select.dataset.assignmentId = assignment?.id ? String(assignment.id) : '';
      });
    };

    const loadHeaderAssignments = async () => {
      if (!headerPageSelect || !headerLocaleSelect) {
        return;
      }
      const namespace = resolveNamespace();
      const pageId = Number(headerPageSelect.value || 0);
      const locale = String(headerLocaleSelect.value || '').trim();
      if (!namespace || !pageId || !locale) {
        state.headerAssignment = null;
        updateHeaderSelect();
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
        updateHeaderSelect();
      } catch (error) {
        setFeedback('Zuweisungen konnten nicht geladen werden.', 'warning');
      }
    };

    const loadFooterOverrideAssignments = async () => {
      if (!footerPageSelect || !footerLocaleSelect) {
        return;
      }
      const namespace = resolveNamespace();
      const pageId = Number(footerPageSelect.value || 0);
      const locale = String(footerLocaleSelect.value || '').trim();
      if (!namespace || !pageId || !locale) {
        state.footerPageAssignments.clear();
        updateFooterOverrideToggle();
        updateFooterOverrideSelects();
        return;
      }
      try {
        const assignments = await requestAssignments({
          namespace,
          pageId,
          locale,
          includeInactive: 1
        });
        state.footerPageAssignments = new Map(
          assignments
            .filter(assignment => assignment && assignment.isActive)
            .filter(assignment => footerSlots.includes(assignment.slot))
            .map(assignment => [assignment.slot, assignment])
        );
        updateFooterOverrideToggle();
        updateFooterOverrideSelects();
      } catch (error) {
        setFeedback('Footer-Zuweisungen konnten nicht geladen werden.', 'warning');
      }
    };

    const loadGlobalFooterAssignments = async () => {
      if (!footerGlobalLocaleSelect) {
        return;
      }
      const namespace = resolveNamespace();
      const locale = String(footerGlobalLocaleSelect.value || '').trim();
      if (!namespace || !locale) {
        state.globalFooterAssignments.clear();
        updateFooterGlobalSelects();
        return;
      }
      try {
        const assignments = await requestAssignments({
          namespace,
          locale,
          includeInactive: 1
        });
        state.globalFooterAssignments = new Map(
          assignments
            .filter(assignment => assignment && assignment.pageId === null && assignment.isActive)
            .filter(assignment => footerSlots.includes(assignment.slot))
            .map(assignment => [assignment.slot, assignment])
        );
        updateFooterGlobalSelects();
      } catch (error) {
        setFeedback('Footer-Zuweisungen konnten nicht geladen werden.', 'warning');
      }
    };

    const saveAssignment = async ({ assignmentId, menuId, pageId, slot, locale }) => {
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

    const handleHeaderMenuChange = async () => {
      if (!headerMenuSelect || !headerPageSelect || !headerLocaleSelect) {
        return;
      }
      hideFeedback();
      const pageId = Number(headerPageSelect.value || 0);
      const locale = String(headerLocaleSelect.value || '').trim();
      if (!pageId || !locale) {
        setFeedback('Bitte zuerst Seite und Locale wählen.', 'warning');
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
            pageId,
            slot: 'main',
            locale
          });
          state.headerAssignment = saved;
        }
        updateHeaderSelect();
        setFeedback('Header-Menü gespeichert.', 'success');
      } catch (error) {
        setFeedback('Header-Menü konnte nicht gespeichert werden.', 'danger');
      }
    };

    const handleGlobalFooterChange = async (event) => {
      const select = event.target;
      if (!select) {
        return;
      }
      hideFeedback();
      const slot = select.dataset.footerGlobalSelect || '';
      const locale = String(footerGlobalLocaleSelect?.value || '').trim();
      if (!slot || !locale) {
        setFeedback('Bitte Locale auswählen.', 'warning');
        return;
      }
      const menuId = Number(select.value || 0);
      const assignment = state.globalFooterAssignments.get(slot);
      try {
        if (!menuId) {
          if (assignment?.id) {
            await deleteAssignment(assignment.id);
          }
          state.globalFooterAssignments.delete(slot);
        } else {
          const saved = await saveAssignment({
            assignmentId: assignment?.id,
            menuId,
            pageId: null,
            slot,
            locale
          });
          if (saved) {
            state.globalFooterAssignments.set(slot, saved);
          }
        }
        updateFooterGlobalSelects();
        setFeedback('Footer-Spalte gespeichert.', 'success');
      } catch (error) {
        setFeedback('Footer-Spalte konnte nicht gespeichert werden.', 'danger');
      }
    };

    const handleFooterOverrideToggle = async () => {
      if (!footerOverrideToggle) {
        return;
      }
      hideFeedback();
      if (footerOverrideToggle.checked) {
        footerOverrideSelects.forEach(select => {
          if (select.dataset.lockedDisabled !== 'true') {
            select.disabled = false;
          }
        });
        return;
      }
      const assignmentsToDelete = footerSlots
        .map(slot => state.footerPageAssignments.get(slot))
        .filter(Boolean);
      try {
        for (const assignment of assignmentsToDelete) {
          await deleteAssignment(assignment.id);
          state.footerPageAssignments.delete(assignment.slot);
        }
        updateFooterOverrideToggle();
        setFeedback('Footer-Overrides entfernt.', 'success');
      } catch (error) {
        setFeedback('Footer-Overrides konnten nicht entfernt werden.', 'danger');
      }
    };

    const handleFooterOverrideChange = async (event) => {
      const select = event.target;
      if (!select || !footerOverrideToggle?.checked) {
        return;
      }
      hideFeedback();
      const slot = select.dataset.footerOverrideSelect || '';
      const pageId = Number(footerPageSelect?.value || 0);
      const locale = String(footerLocaleSelect?.value || '').trim();
      if (!slot || !pageId || !locale) {
        setFeedback('Bitte Seite und Locale wählen.', 'warning');
        return;
      }
      const menuId = Number(select.value || 0);
      const assignment = state.footerPageAssignments.get(slot);
      try {
        if (!menuId) {
          if (assignment?.id) {
            await deleteAssignment(assignment.id);
          }
          state.footerPageAssignments.delete(slot);
        } else {
          const saved = await saveAssignment({
            assignmentId: assignment?.id,
            menuId,
            pageId,
            slot,
            locale
          });
          if (saved) {
            state.footerPageAssignments.set(slot, saved);
          }
        }
        updateFooterOverrideSelects();
        updateFooterOverrideToggle();
        setFeedback('Footer-Override gespeichert.', 'success');
      } catch (error) {
        setFeedback('Footer-Override konnte nicht gespeichert werden.', 'danger');
      }
    };

    const refreshLocaleFilters = () => {
      const headerLocale = headerLocaleSelect?.value;
      filterMenuOptions(headerMenuSelect, headerLocale);
      const globalLocale = footerGlobalLocaleSelect?.value;
      footerGlobalSelects.forEach(select => filterMenuOptions(select, globalLocale));
      const footerLocale = footerLocaleSelect?.value;
      footerOverrideSelects.forEach(select => filterMenuOptions(select, footerLocale));
    };

    if (headerLocaleSelect) {
      headerLocaleSelect.addEventListener('change', () => {
        refreshLocaleFilters();
        loadHeaderAssignments();
      });
    }
    if (headerPageSelect) {
      headerPageSelect.addEventListener('change', () => {
        loadHeaderAssignments();
      });
    }
    if (headerMenuSelect) {
      headerMenuSelect.addEventListener('change', handleHeaderMenuChange);
    }
    if (footerGlobalLocaleSelect) {
      footerGlobalLocaleSelect.addEventListener('change', () => {
        refreshLocaleFilters();
        loadGlobalFooterAssignments();
      });
    }
    footerGlobalSelects.forEach(select => {
      select.addEventListener('change', handleGlobalFooterChange);
    });
    if (footerLocaleSelect) {
      footerLocaleSelect.addEventListener('change', () => {
        refreshLocaleFilters();
        loadFooterOverrideAssignments();
      });
    }
    if (footerPageSelect) {
      footerPageSelect.addEventListener('change', loadFooterOverrideAssignments);
    }
    if (footerOverrideToggle) {
      footerOverrideToggle.addEventListener('change', handleFooterOverrideToggle);
    }
    footerOverrideSelects.forEach(select => {
      select.addEventListener('change', handleFooterOverrideChange);
    });

    const namespaceSelect = document.getElementById('pageNamespaceSelect');
    if (namespaceSelect) {
      namespaceSelect.addEventListener('change', () => {
        loadHeaderAssignments();
        loadFooterOverrideAssignments();
        loadGlobalFooterAssignments();
      });
    }

    refreshLocaleFilters();
    loadHeaderAssignments();
    loadFooterOverrideAssignments();
    loadGlobalFooterAssignments();
  })();
}
