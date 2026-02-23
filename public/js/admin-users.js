/* global UIkit */

/**
 * Users / Tenants section – extracted from admin.js.
 *
 * @param {object} ctx - shared dependencies injected from admin.js
 * @param {function} ctx.apiFetch           - API fetch wrapper
 * @param {function} ctx.notify             - notification helper (window.notify)
 * @param {object}   ctx.managementSection  - DOM reference to [data-admin-section="management"]
 * @param {Element}  ctx.adminTabs          - admin tabs container
 * @param {string[]} ctx.adminRoutes        - ordered route names for each tab
 * @param {function} ctx.TableManager       - TableManager class
 * @param {function} ctx.registerCacheReset - from event-switcher
 * @param {function} ctx.getStored          - localStorage getter (global)
 * @param {function} ctx.setStored          - localStorage setter (global)
 * @param {object}   ctx.STORAGE_KEYS       - storage key constants (global)
 */
export function initUsers(ctx) {
  const {
    apiFetch,
    notify,
    managementSection,
    adminTabs,
    adminRoutes,
    TableManager,
    registerCacheReset,
    getStored,
    setStored,
    STORAGE_KEYS
  } = ctx;

  // --------- Benutzer ---------
  const usersSection = document.querySelector('[data-admin-section="users"]');
  const usersRoot = usersSection || document;
  const usersListEl = usersRoot?.querySelector('#usersList') || null;
  const usersCardsEl = usersRoot?.querySelector('#usersCards') || null;
  const userAddBtn = usersRoot?.querySelector('#userAddBtn') || null;
  const userPassModal = usersRoot && window.UIkit ? UIkit.modal('#userPassModal') : null;
  const userPassInput = usersRoot?.querySelector('#userPassInput') || null;
  const userPassRepeat = usersRoot?.querySelector('#userPassRepeat') || null;
  const userPassForm = usersRoot?.querySelector('#userPassForm') || null;
  const usersPaginationEl = usersRoot?.querySelector('#usersPagination') || null;
  const labelUsername = usersListEl?.dataset.labelUsername || (window.transUsername || 'Username');
  const labelRole = usersListEl?.dataset.labelRole || (window.transRole || 'Role');
  const labelNamespaces = usersListEl?.dataset.labelNamespaces || (window.transNamespaces || 'Namespaces');
  const labelActive = usersListEl?.dataset.labelActive || (window.transActive || 'Active');
  const rawAvailableNamespaces = Array.isArray(window.availableNamespaces)
    ? window.availableNamespaces
    : [];
  const normalizeNamespaceEntry = (entry) => {
    if (!entry) {
      return null;
    }
    if (typeof entry === 'string') {
      const namespace = entry.trim().toLowerCase();
      return namespace ? { namespace, label: null, is_active: true } : null;
    }
    if (typeof entry === 'object') {
      const namespace = String(entry.namespace || '').trim().toLowerCase();
      if (!namespace) {
        return null;
      }
      const label = entry.label ? String(entry.label).trim() : null;
      const isActive = entry.is_active !== false;
      return { namespace, label: label || null, is_active: isActive };
    }
    return null;
  };
  const normalizedAvailableEntries = rawAvailableNamespaces
    .map(normalizeNamespaceEntry)
    .filter(Boolean);
  if (normalizedAvailableEntries.length === 0) {
    normalizedAvailableEntries.push({
      namespace: window.defaultNamespace || 'default',
      label: null,
      is_active: true
    });
  }
  const availableNamespaces = normalizedAvailableEntries
    .filter(entry => entry.is_active !== false)
    .map(entry => entry.namespace);
  if (availableNamespaces.length === 0) {
    availableNamespaces.push(window.defaultNamespace || 'default');
  }
  const availableNamespaceLabels = new Map(
    normalizedAvailableEntries.map(entry => [entry.namespace, entry.label])
  );
  const defaultNamespace = window.defaultNamespace || 'default';
  const canEditNamespaces = window.currentUserRole === 'admin';
  const namespaceActiveLabel = window.transNamespaceActiveLabel || 'Aktiv';
  const namespaceDefaultLabel = window.transNamespaceDefaultLabel || 'Standard';
  const namespaceNoneLabel = window.transNamespaceNone || '-';
  const USERS_PER_PAGE = 50;
  const userSectionActive = usersListEl?.closest('li')?.classList.contains('uk-active');
  let currentUserId = null;
  let userManager;
  let usersInitialized = false;
  let usersLoading = false;

  function normalizeUserNamespaces(item) {
    const existing = Array.isArray(item.namespaces) ? item.namespaces : [];
    const normalized = [];
    existing.forEach(entry => {
      const namespace = typeof entry === 'string' ? entry : entry?.namespace;
      if (!namespace) return;
      const value = String(namespace).trim().toLowerCase();
      if (!value || normalized.some(v => v.namespace === value)) return;
      normalized.push({
        namespace: value,
        is_default: Boolean(entry?.is_default)
      });
    });
    if (normalized.length === 0) {
      normalized.push({ namespace: defaultNamespace, is_default: true });
    }
    if (!normalized.some(entry => entry.is_default)) {
      normalized[0].is_default = true;
    }
    item.namespaces = normalized;
    return normalized;
  }

  function getDefaultNamespace(item) {
    const namespaces = normalizeUserNamespaces(item);
    const selected = namespaces.find(entry => entry.is_default);
    return selected?.namespace || namespaces[0]?.namespace || defaultNamespace;
  }

  function renderUsers(list = []) {
    const data = list.map(u => ({
      ...u,
      id: u.id ?? crypto.randomUUID(),
      role: u.role || (window.roles && window.roles[0]) || '',
      password: '',
      namespaces: Array.isArray(u.namespaces) ? u.namespaces : []
    }));
    data.forEach(item => normalizeUserNamespaces(item));
    userManager.render(data);
  }

  const getResponseErrorText = (payload) => {
    if (!payload) {
      return '';
    }
    if (typeof payload === 'string') {
      return payload.trim();
    }
    const candidate =
      payload.error ||
      payload.message ||
      payload.detail ||
      payload.title;
    if (typeof candidate === 'string') {
      return candidate.trim();
    }
    if (Array.isArray(payload.errors) && payload.errors.length) {
      const first = payload.errors.find(entry => typeof entry === 'string');
      if (first) {
        return first.trim();
      }
    }
    if (payload.errors && typeof payload.errors === 'object') {
      const firstKey = Object.keys(payload.errors)[0];
      const entry = payload.errors[firstKey];
      if (Array.isArray(entry) && entry.length && typeof entry[0] === 'string') {
        return entry[0].trim();
      }
      if (typeof entry === 'string') {
        return entry.trim();
      }
    }
    return '';
  };

  const getUserConflictMessage = (payload) => {
    const hint =
      payload?.conflict ||
      payload?.field ||
      payload?.code ||
      payload?.reason ||
      '';
    const normalized = String(hint || getResponseErrorText(payload)).toLowerCase();
    if (normalized.includes('email') || normalized.includes('e-mail') || normalized.includes('mail')) {
      return window.transEmailTaken || 'Email already taken';
    }
    if (normalized.includes('rolle') || normalized.includes('role')) {
      return window.transRoleTaken || 'Role already taken';
    }
    if (normalized.includes('user') || normalized.includes('benutzer') || normalized.includes('name')) {
      return window.transUsernameTaken || 'Username already taken';
    }
    const fallback = getResponseErrorText(payload);
    return fallback || (window.transUsernameTaken || 'Username already taken');
  };

  const readErrorPayload = async (response) => {
    if (!response || response.ok) {
      return null;
    }
    try {
      return await response.clone().json();
    } catch (error) {
      return null;
    }
  };

  function saveUsers(list = userManager?.getData() || []) {
    if (list.some(u => !u.username?.trim())) {
      notify(window.transUsernameRequired || 'Username must not be empty', 'warning');
      return;
    }
    const payload = list
      .map((u, index) => ({
        id: u.id && !isNaN(u.id) ? parseInt(u.id, 10) : undefined,
        username: u.username?.trim(),
        role: u.role,
        active: u.active !== false,
        password: u.password || '',
        namespaces: Array.isArray(u.namespaces) ? u.namespaces : [],
        position: index
      }))
      .filter(u => u.username);
    apiFetch('/users.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(async r => {
        const errorPayload = await readErrorPayload(r);
        if (r.status === 409) {
          notify(getUserConflictMessage(errorPayload), 'danger');
          return Promise.reject();
        }
        if (!r.ok) {
          const message = getResponseErrorText(errorPayload);
          if (r.status === 400 || r.status === 422) {
            notify(message || (window.transUsernameNotAllowed || 'Username not allowed'), 'danger');
            return Promise.reject();
          }
          if (message) {
            notify(message, 'danger');
            return Promise.reject();
          }
          throw new Error(r.statusText);
        }
        return r.json();
      })
      .then(data => {
        renderUsers(data);
        notify(window.transListSaved || 'List saved', 'success');
      })
      .catch(err => {
        if (err) {
          console.error(err);
          notify(window.transErrorSaveFailed || 'Save failed', 'danger');
        }
      });
  }

  function removeUser(id) {
    const list = userManager.getData();
    const idx = list.findIndex(u => u.id === id);
    if (idx !== -1) {
      list.splice(idx, 1);
      userManager.render(list);
      saveUsers(list);
    }
  }

  function openPassModal(id) {
    currentUserId = id;
    if (userPassInput) userPassInput.value = '';
    if (userPassRepeat) userPassRepeat.value = '';
    userPassModal?.show();
  }

  function addUser() {
    const list = userManager.getData();
    const id = crypto.randomUUID();
    list.push({
      id,
      username: '',
      role: (window.roles && window.roles[0]) || '',
      active: true,
      password: '',
      namespaces: [{ namespace: defaultNamespace, is_default: true }]
    });
    userManager.render(list);
    const cell = usersListEl?.querySelector(`[data-id="${id}"][data-key="username"]`);
    if (cell) {
      openUserEditor(cell);
    }
  }

  const userNameModal = usersRoot && window.UIkit ? UIkit.modal('#userNameModal') : null;
  const userNameForm = usersRoot?.querySelector('#userNameForm') || null;
  const userNameInput = usersRoot?.querySelector('#userNameInput') || null;

  function openUserEditor(cell) {
    const id = cell?.dataset.id;
    const key = cell?.dataset.key;
    const item = userManager.getData().find(u => u.id === id);
    if (!item || key !== 'username') return;
    currentUserId = id;
    userNameInput.value = item.username || '';
    userNameModal?.show();
  }

  userNameForm?.addEventListener('submit', e => {
    e.preventDefault();
    const list = userManager.getData();
    const item = list.find(u => u.id === currentUserId);
    if (!item) {
      userNameModal?.hide();
      return;
    }
    const value = userNameInput.value.trim();
    if (!value) {
      notify(window.transUsernameRequired || 'Benutzername darf nicht leer sein', 'warning');
      return;
    }
    item.username = value;
    userManager.render(list);
    saveUsers(list);
    userNameModal?.hide();
  });

  function normalizeNamespaceList(values = []) {
    const normalized = [];
    values.forEach(value => {
      if (!value) return;
      const normalizedValue = String(value).trim().toLowerCase();
      if (normalizedValue && !normalized.includes(normalizedValue)) {
        normalized.push(normalizedValue);
      }
    });
    return normalized;
  }

  function updateNamespaceSelection(item, selectedValues, defaultValue) {
    const normalizedValues = normalizeNamespaceList(selectedValues);
    if (normalizedValues.length === 0) {
      normalizedValues.push(defaultNamespace);
    }

    let activeDefault = defaultValue ? String(defaultValue).trim().toLowerCase() : '';
    if (!normalizedValues.includes(activeDefault)) {
      activeDefault = normalizedValues[0] || defaultNamespace;
    }

    item.namespaces = normalizedValues.map(namespace => ({
      namespace,
      is_default: namespace === activeDefault
    }));

    return {
      namespaces: normalizedValues,
      defaultNamespace: activeDefault
    };
  }

  function buildNamespaceCell(item) {
    normalizeUserNamespaces(item);
    const wrapper = document.createElement('div');
    wrapper.className = 'uk-flex uk-flex-column';

    const select = document.createElement('select');
    select.className = 'uk-select';
    select.multiple = true;
    select.setAttribute('aria-label', labelNamespaces);
    select.disabled = !canEditNamespaces;

    const selectedSet = new Set(normalizeUserNamespaces(item).map(entry => entry.namespace));
    const namespaceOptions = [...availableNamespaces];
    selectedSet.forEach(namespace => {
      if (!namespaceOptions.includes(namespace)) {
        namespaceOptions.push(namespace);
      }
    });
    const formatNamespaceLabel = (namespace) => {
      const label = availableNamespaceLabels.get(namespace);
      if (label) {
        return `${namespace} – ${label}`;
      }
      return namespace;
    };
    namespaceOptions.forEach(namespace => {
      const option = document.createElement('option');
      option.value = namespace;
      option.textContent = formatNamespaceLabel(namespace);
      option.selected = selectedSet.has(namespace);
      select.appendChild(option);
    });

    wrapper.appendChild(select);

    const summary = document.createElement('div');
    summary.className = 'uk-text-small uk-text-muted uk-margin-small-top';
    wrapper.appendChild(summary);

    const activeRow = document.createElement('div');
    activeRow.className = 'uk-text-meta uk-margin-small-top';
    wrapper.appendChild(activeRow);

    let defaultSelect = null;
    if (canEditNamespaces) {
      const defaultRow = document.createElement('div');
      defaultRow.className = 'uk-margin-small-top uk-flex uk-flex-middle';

      const label = document.createElement('span');
      label.className = 'uk-text-meta uk-margin-small-right';
      label.textContent = `${namespaceDefaultLabel}:`;
      defaultRow.appendChild(label);

      defaultSelect = document.createElement('select');
      defaultSelect.className = 'uk-select uk-form-small';
      defaultSelect.setAttribute('aria-label', namespaceDefaultLabel);
      defaultRow.appendChild(defaultSelect);
      wrapper.appendChild(defaultRow);
    }

    const syncDisplay = (commit = false) => {
      const selectedValues = Array.from(select.selectedOptions).map(option => option.value);
      const defaultValue = defaultSelect ? defaultSelect.value : getDefaultNamespace(item);
      const result = updateNamespaceSelection(item, selectedValues, defaultValue);

      if (selectedValues.length === 0) {
        Array.from(select.options).forEach(option => {
          option.selected = option.value === result.defaultNamespace;
        });
      }

      if (defaultSelect) {
        defaultSelect.innerHTML = '';
        result.namespaces.forEach(namespace => {
          const option = document.createElement('option');
          option.value = namespace;
          option.textContent = formatNamespaceLabel(namespace);
          defaultSelect.appendChild(option);
        });
        defaultSelect.value = result.defaultNamespace || '';
      }

      summary.textContent = result.namespaces.length
        ? result.namespaces.map(formatNamespaceLabel).join(', ')
        : namespaceNoneLabel;
      activeRow.textContent = `${namespaceActiveLabel}: ${result.defaultNamespace || namespaceNoneLabel}`;

      if (commit) {
        saveUsers(userManager.getData());
      }
    };

    select.addEventListener('change', () => syncDisplay(true));
    defaultSelect?.addEventListener('change', () => syncDisplay(true));

    syncDisplay(false);

    return wrapper;
  }

  if (usersListEl) {
    const roleTemplate = document.getElementById('userRoleSelect');
    const userColumns = [
      { key: 'username', label: labelUsername, editable: true },
      {
        key: 'role',
        label: labelRole,
        render: item => {
          let select;
          if (roleTemplate) {
            select = roleTemplate.content.firstElementChild.cloneNode(true);
          } else {
            select = document.createElement('select');
            (window.roles || []).forEach(r => {
              const opt = document.createElement('option');
              opt.value = r;
              opt.textContent = r;
              select.appendChild(opt);
            });
          }
          select.value = item.role || (window.roles && window.roles[0]) || '';
          select.addEventListener('change', () => {
            item.role = select.value;
            saveUsers(userManager.getData());
          });
          return select;
        }
      },
      {
        key: 'namespaces',
        label: labelNamespaces,
        render: item => buildNamespaceCell(item)
      },
      {
        key: 'active',
        label: labelActive,
        className: 'uk-table-shrink',
        render: item => {
          const cb = document.createElement('input');
          cb.type = 'checkbox';
          cb.checked = item.active !== false;
          cb.addEventListener('change', () => {
            item.active = cb.checked;
            saveUsers(userManager.getData());
          });
          return cb;
        }
      },
      {
        className: 'uk-table-shrink',
        render: item => {
          const wrapper = document.createElement('div');
          wrapper.className = 'uk-flex uk-flex-middle uk-flex-right';

          const passBtn = document.createElement('button');
          passBtn.type = 'button';
          passBtn.className = 'uk-icon-button qr-action';
          passBtn.setAttribute('uk-icon', 'key');
          passBtn.setAttribute('aria-label', window.transUserPass || 'Set password');
          passBtn.setAttribute('uk-tooltip', 'title: ' + (window.transUserPass || 'Set password') + '; pos: left');
          passBtn.addEventListener('click', () => openPassModal(item.id));
          wrapper.appendChild(passBtn);

          const delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.className = 'uk-icon-button qr-action uk-text-danger uk-margin-small-left';
          delBtn.setAttribute('uk-icon', 'trash');
          delBtn.setAttribute('aria-label', window.transDelete || 'Delete');
          delBtn.setAttribute('uk-tooltip', 'title: ' + (window.transDelete || 'Delete') + '; pos: left');
          delBtn.addEventListener('click', () => removeUser(item.id));
          wrapper.appendChild(delBtn);

          return wrapper;
        },
        renderCard: item => {
          const wrapper = document.createElement('div');
          wrapper.className = 'uk-flex uk-flex-middle qr-action';

          const passBtn = document.createElement('button');
          passBtn.className = 'uk-icon-button qr-action';
          passBtn.setAttribute('uk-icon', 'key');
          passBtn.setAttribute('aria-label', window.transUserPass || 'Set password');
          passBtn.addEventListener('click', () => openPassModal(item.id));
          wrapper.appendChild(passBtn);

          const delBtn = document.createElement('button');
          delBtn.className = 'uk-icon-button qr-action uk-text-danger uk-margin-small-left';
          delBtn.setAttribute('uk-icon', 'trash');
          delBtn.setAttribute('aria-label', window.transDelete || 'Delete');
          delBtn.addEventListener('click', () => removeUser(item.id));
          wrapper.appendChild(delBtn);

          return wrapper;
        }
      }
    ];
    userManager = new TableManager({
      tbody: usersListEl,
      columns: userColumns,
      sortable: true,
      mobileCards: { container: usersCardsEl },
      onEdit: cell => openUserEditor(cell),
      onReorder: () => saveUsers()
    });
    if (usersPaginationEl) {
      userManager.bindPagination(usersPaginationEl, USERS_PER_PAGE);
    }
    const loadUsers = () => {
      if (!usersListEl || usersInitialized || usersLoading) {
        return;
      }
      usersLoading = true;
      userManager.setColumnLoading('username', true);

      const inline = window.initialUsers;
      if (Array.isArray(inline) && inline.length > 0) {
        window.initialUsers = null;
        renderUsers(inline);
        usersInitialized = true;
        usersLoading = false;
        userManager.setColumnLoading('username', false);
        return;
      }

      apiFetch('/users.json', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
          renderUsers(data);
          usersInitialized = true;
        })
        .catch(() => {})
        .finally(() => {
          usersLoading = false;
          userManager.setColumnLoading('username', false);
        });
    };

    if (userSectionActive) {
      loadUsers();
    }

    if (adminTabs && window.UIkit && UIkit.util) {
      UIkit.util.on(adminTabs, 'shown', (e, tab) => {
        const index = Array.prototype.indexOf.call(adminTabs.children, tab);
        if (adminRoutes[index] === 'logins') {
          loadUsers();
        }
      });
    }
  }

  userAddBtn?.addEventListener('click', e => {
    e.preventDefault();
    addUser();
  });

  userPassForm?.addEventListener('submit', e => {
    e.preventDefault();
    if (!userPassInput || !userPassRepeat) return;
    const p1 = userPassInput.value;
    const p2 = userPassRepeat.value;
    if (p1 === '' || p2 === '') {
      notify(window.transPasswordEmpty || 'Password must not be empty', 'danger');
      return;
    }
    if (p1 !== p2) {
      notify(window.transPasswordMismatch || 'Passwords do not match', 'danger');
      return;
    }
    const list = userManager.getData();
    const item = list.find(u => u.id === currentUserId);
    if (item) {
      item.password = p1;
      saveUsers(list);
    }
    userPassModal?.hide();
    userPassInput.value = '';
    userPassRepeat.value = '';
  });

  const importJsonBtn = managementSection?.querySelector('#importJsonBtn') || null;
  const exportJsonBtn = managementSection?.querySelector('#exportJsonBtn') || null;
  const saveDemoBtn = managementSection?.querySelector('#saveDemoBtn') || null;
  const backupTableBody = managementSection?.querySelector('#backupTableBody') || null;
  const tenantTableBody = document.getElementById('tenantTableBody');
  const tenantCards = document.getElementById('tenantCards');
  const tenantSyncBtn = document.getElementById('tenantSyncBtn');
  const tenantSyncBadge = document.getElementById('tenantSyncBadge');
  const tenantExportBtn = document.getElementById('tenantExportBtn');
  const tenantReportBtn = document.getElementById('tenantReportBtn');
  const tenantStatusFilter = document.getElementById('tenantStatusFilter');
  const tenantSearchInput = document.getElementById('tenantSearchInput');
  const TENANT_STATUS_VALUES = new Set([
    'active',
    'canceled',
    'simulated',
    'pending',
    'provisioning',
    'provisioned',
    'failed'
  ]);
  const normalizeTenantStatus = value => {
    if (typeof value !== 'string') {
      return '';
    }
    const normalized = value.trim().toLowerCase();
    return TENANT_STATUS_VALUES.has(normalized) ? normalized : '';
  };
  let tenantColumnBtn = document.getElementById('tenantColumnBtn');
  const tenantTable = tenantTableBody?.closest('table');
  const tenantTableHeadings = tenantTable?.querySelectorAll('thead th') || [];
  const tenantColumnDefs = [
    { key: 'plan', label: 'Abo', thIndex: 1 },
    { key: 'billing', label: 'Rechnungsinfo', thIndex: 2 },
    { key: 'created', label: 'Erstellt', thIndex: 3 }
  ];
  const tenantColumnDefaults = tenantColumnDefs.map(c => c.key);
  let tenantColumns = [...tenantColumnDefaults];
  let initialTenantListHtml = typeof window.initialTenantListHtml === 'string'
    ? window.initialTenantListHtml
    : '';
  let initialTenantHtmlApplied = false;
  let tenantSyncState = null;

  function normalizeTenantSyncState(raw) {
    if (!raw || typeof raw !== 'object') {
      return null;
    }
    const parseIntSafe = value => {
      if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
      }
      const num = parseInt(String(value ?? ''), 10);
      return Number.isNaN(num) ? 0 : num;
    };
    const parseBoolSafe = value => {
      if (typeof value === 'boolean') {
        return value;
      }
      if (typeof value === 'number') {
        return value !== 0;
      }
      if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        if (['1', 'true', 'yes', 'on'].includes(normalized)) {
          return true;
        }
        if (['0', 'false', 'no', 'off', ''].includes(normalized)) {
          return false;
        }
      }
      return Boolean(value);
    };
    return {
      last_run_at: typeof raw.last_run_at === 'string' && raw.last_run_at !== '' ? raw.last_run_at : null,
      next_allowed_at: typeof raw.next_allowed_at === 'string' && raw.next_allowed_at !== '' ? raw.next_allowed_at : null,
      cooldown_seconds: parseIntSafe(raw.cooldown_seconds),
      stale_after_seconds: parseIntSafe(raw.stale_after_seconds),
      is_stale: parseBoolSafe(raw.is_stale),
      is_throttled: parseBoolSafe(raw.is_throttled)
    };
  }

  function renderTenantSyncBadge() {
    if (!tenantSyncBadge) {
      return;
    }
    if (!tenantSyncState) {
      tenantSyncBadge.classList.add('uk-hidden');
      tenantSyncBadge.removeAttribute('title');
      tenantSyncBadge.removeAttribute('aria-label');
      return;
    }

    const parseDate = value => {
      if (typeof value !== 'string' || value === '') {
        return null;
      }
      const ms = Date.parse(value);
      return Number.isNaN(ms) ? null : ms;
    };

    const now = Date.now();
    const lastRunMs = tenantSyncState.last_run_at ? parseDate(tenantSyncState.last_run_at) : null;
    const nextAllowedMs = tenantSyncState.next_allowed_at ? parseDate(tenantSyncState.next_allowed_at) : null;
    const staleByAge = lastRunMs === null
      ? true
      : (tenantSyncState.stale_after_seconds > 0
        ? (now - lastRunMs) > tenantSyncState.stale_after_seconds * 1000
        : false);
    const isStale = Boolean(tenantSyncState.is_stale) || staleByAge;
    const computedThrottled = nextAllowedMs !== null ? nextAllowedMs > now : false;
    const isThrottled = nextAllowedMs !== null ? computedThrottled : Boolean(tenantSyncState.is_throttled);

    let text = window.transTenantSyncOk || 'Up to date';
    let background = '#32d296';
    if (isStale) {
      text = window.transTenantSyncStale || 'Sync needed';
      background = '#faa05a';
    } else if (isThrottled) {
      text = window.transTenantSyncCooling || 'Cooldown';
      background = '#1e87f0';
    }

    tenantSyncBadge.textContent = text;
    tenantSyncBadge.style.backgroundColor = background;
    tenantSyncBadge.style.color = '#fff';
    tenantSyncBadge.classList.remove('uk-hidden');

    if (lastRunMs) {
      const formatted = new Date(lastRunMs).toLocaleString();
      const label = `${window.transTenantSyncLastRun || 'Letzter Sync'}: ${formatted}`;
      tenantSyncBadge.title = label;
      tenantSyncBadge.setAttribute('aria-label', label);
    } else {
      tenantSyncBadge.removeAttribute('title');
      tenantSyncBadge.removeAttribute('aria-label');
    }
  }

  function updateTenantSyncState(state) {
    tenantSyncState = normalizeTenantSyncState(state);
    if (typeof window !== 'undefined') {
      window.tenantSyncState = tenantSyncState;
    }
    renderTenantSyncBadge();
  }

  function extractTenantSyncState(doc) {
    if (!doc || typeof doc.getElementById !== 'function') {
      return null;
    }
    const meta = doc.getElementById('tenantSyncMeta');
    if (!meta) {
      return null;
    }
    const { dataset } = meta;
    const parseIntSafe = value => {
      const num = parseInt(String(value ?? ''), 10);
      return Number.isNaN(num) ? 0 : num;
    };
    return {
      last_run_at: dataset.lastRun || null,
      next_allowed_at: dataset.nextAllowed || null,
      cooldown_seconds: parseIntSafe(dataset.cooldown),
      stale_after_seconds: parseIntSafe(dataset.staleAfter),
      is_stale: dataset.isStale === '1',
      is_throttled: dataset.isThrottled === '1'
    };
  }

  function applyTenantListHtml(html) {
    if (typeof html !== 'string' || html.trim() === '') {
      return false;
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const newBody = doc.getElementById('tenantTableBody');
    const newCards = doc.getElementById('tenantCards');
    const metaState = extractTenantSyncState(doc);
    let applied = false;

    if (tenantTableBody) {
      if (newBody) {
        tenantTableBody.innerHTML = newBody.innerHTML;
        applied = true;
      } else {
        tenantTableBody.innerHTML = '';
      }
    }

    if (tenantCards) {
      if (newCards) {
        tenantCards.innerHTML = newCards.innerHTML;
        applied = true;
      } else {
        tenantCards.innerHTML = '';
      }
    }

    if (applied) {
      bindTenantColumnButton();
    }

    if (metaState) {
      updateTenantSyncState(metaState);
    }

    if (applied) {
      updateTenantColumnVisibility();
    }

    return applied;
  }

  if (initialTenantListHtml.trim() !== '') {
    initialTenantHtmlApplied = applyTenantListHtml(initialTenantListHtml);
    window.initialTenantListHtml = '';
    initialTenantListHtml = '';
  }

  function showTenantSpinner() {
    if (tenantTableBody) {
      const columnCount = tenantTableHeadings.length || tenantColumnDefs.length || 1;
      tenantTableBody.innerHTML = `<tr><td colspan="${columnCount}" class="uk-text-center uk-padding"><div uk-spinner></div></td></tr>`;
    }
    if (tenantCards) {
      tenantCards.innerHTML = '<div class="uk-text-center uk-padding"><div uk-spinner></div></div>';
    }
  }

  function refreshTenantList(showSpinner = true) {
    const statusValue = normalizeTenantStatus(tenantStatusFilter?.value || '');
    if (tenantStatusFilter && statusValue !== (tenantStatusFilter.value || '')) {
      tenantStatusFilter.value = '';
    }
    loadTenants(statusValue, tenantSearchInput?.value, showSpinner);
  }

  try {
    const stored = JSON.parse(getStored(STORAGE_KEYS.TENANT_COLUMNS));
    if (Array.isArray(stored)) {
      tenantColumns = tenantColumnDefaults.filter(k => stored.includes(k));
    }
  } catch (_) {}
  tenantColumnDefs.forEach(def => {
    tenantTableHeadings[def.thIndex]?.classList.add('col-' + def.key);
  });
  function updateTenantColumnVisibility() {
    tenantColumnDefs.forEach(def => {
      const visible = tenantColumns.includes(def.key);
      if (tenantTableHeadings[def.thIndex]) {
        tenantTableHeadings[def.thIndex].style.display = visible ? '' : 'none';
      }
      tenantTable?.querySelectorAll('.col-' + def.key).forEach(el => {
        el.style.display = visible ? '' : 'none';
      });
    });
  }
  function handleTenantColumnClick() {
    let modal = document.getElementById('tenantColumnModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'tenantColumnModal';
      modal.setAttribute('uk-modal', '');
      const options = tenantColumnDefs.map(def => {
        const checked = tenantColumns.includes(def.key) ? 'checked' : '';
        return `<label><input class="uk-checkbox" type="checkbox" data-col="${def.key}" ${checked}> ${def.label}</label>`;
      }).join('<br>');
      modal.innerHTML = `<div class="uk-modal-dialog uk-modal-body">
        <h2 class="uk-modal-title">${window.transSelectColumns || 'Select columns'}</h2>
        <form>${options}</form>
        <p class="uk-text-right">
          <button class="uk-button uk-button-default uk-modal-close" type="button">${window.transCancel || 'Cancel'}</button>
          <button class="uk-button uk-button-primary" type="button" id="tenantColumnSave">${window.transSave || 'Save'}</button>
        </p>
      </div>`;
      document.body.appendChild(modal);
      modal.querySelector('#tenantColumnSave').addEventListener('click', () => {
        const selected = Array.from(modal.querySelectorAll('input[type="checkbox"]'))
          .filter(cb => cb.checked)
          .map(cb => cb.dataset.col);
        tenantColumns = tenantColumnDefaults.filter(k => selected.includes(k));
        try { setStored(STORAGE_KEYS.TENANT_COLUMNS, JSON.stringify(tenantColumns)); } catch (_) {}
        updateTenantColumnVisibility();
        refreshTenantList();
        if (window.UIkit) UIkit.modal(modal).hide();
      });
    } else {
      modal.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.checked = tenantColumns.includes(cb.dataset.col);
      });
    }
    if (window.UIkit) UIkit.modal(modal).show();
  }

  function bindTenantColumnButton() {
    tenantColumnBtn = document.getElementById('tenantColumnBtn');
    if (!tenantColumnBtn) {
      return;
    }
    tenantColumnBtn.removeEventListener('click', handleTenantColumnClick);
    tenantColumnBtn.addEventListener('click', handleTenantColumnClick);
  }

  bindTenantColumnButton();
  updateTenantColumnVisibility();
  updateTenantSyncState(window.tenantSyncState || null);

  tenantStatusFilter?.addEventListener('change', () => {
    refreshTenantList();
  });

  tenantSearchInput?.addEventListener('input', () => {
    refreshTenantList();
  });

  const initBackupDropdowns = (root) => {
    if (!root || typeof UIkit === 'undefined' || typeof UIkit.dropdown !== 'function') {
      return;
    }
    root.querySelectorAll('[uk-dropdown]').forEach(el => {
      UIkit.dropdown(el);
    });
  };

  function loadBackups() {
    if (!backupTableBody) return;
    apiFetch('/backups')
      .then(r => {
        if (!r.ok) {
          return r.json().then(data => {
            throw new Error(data.error || r.statusText);
          });
        }

        return r.text();
      })
      .then(html => {
        backupTableBody.innerHTML = html;
        initBackupDropdowns(backupTableBody);
      })
      .catch(err => {
        backupTableBody.innerHTML = '<tr><td colspan="2">Fehler</td></tr>';
        notify(err.message || (window.transMissingPermissions || 'Missing permissions or folder'), 'danger');
      });
  }

  backupTableBody?.addEventListener('submit', e => {
    const target = e.target instanceof HTMLFormElement ? e.target.querySelector('[data-action][data-name]') : null;
    if (!target) return;
    e.preventDefault();
  });

  backupTableBody?.addEventListener('click', e => {
    const actionEl = e.target instanceof HTMLElement ? e.target.closest('[data-action][data-name]') : null;
    if (!actionEl) return;
    e.preventDefault();
    const { action, name } = actionEl.dataset;
    if (!name) return;
    if (action === 'restore') {
      apiFetch('/backups/' + encodeURIComponent(name) + '/restore', { method: 'POST' })
        .then(r => {
          if (!r.ok) throw new Error(r.statusText);
          notify(window.transImportComplete || 'Import complete', 'success');
        })
        .catch(() => notify(window.transErrorImportFailed || 'Import failed', 'danger'));
    } else if (action === 'download') {
      apiFetch('/backups/' + encodeURIComponent(name) + '/download')
        .then(r => r.blob())
        .then(blob => {
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = name + '.zip';
          a.click();
          URL.revokeObjectURL(url);
        })
        .catch(() => notify(window.transErrorDownloadFailed || 'Download failed', 'danger'));
    } else if (action === 'delete') {
      apiFetch('/backups/' + encodeURIComponent(name), { method: 'DELETE' })
        .then(r => {
          if (r.ok) {
            loadBackups();
            return;
          }
          return r.json().then(data => {
            throw new Error(data.error || r.statusText);
          });
        })
        .catch(err => notify(err.message || (window.transErrorDeleteFailed || 'Delete failed'), 'danger'));
    }
  });
  importJsonBtn?.addEventListener('click', e => {
    e.preventDefault();
    apiFetch('/restore-default', { method: 'POST' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify(window.transImportComplete || 'Import complete', 'success');
      })
      .catch(err => {
        console.error(err);
        notify(window.transErrorImportFailed || 'Import failed', 'danger');
      });
  });

  saveDemoBtn?.addEventListener('click', e => {
    e.preventDefault();
    apiFetch('/export-default', { method: 'POST' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify(window.transDemoDataSaved || 'Demo data saved', 'success');
      })
      .catch(err => {
        console.error(err);
        notify(window.transErrorSaveFailed || 'Save failed', 'danger');
      });
  });


  exportJsonBtn?.addEventListener('click', e => {
    e.preventDefault();
    apiFetch('/export', { method: 'POST' })
      .then(r => {
        if (!r.ok) {
          return r.json().then(data => {
            throw new Error(data.error || r.statusText);
          });
        }
        notify(window.transExportComplete || 'Export complete', 'success');
        loadBackups();
      })
      .catch(err => {
        console.error(err);
        notify(err.message || (window.transMissingPermissions || 'Missing permissions or folder'), 'danger');
      });
  });

  tenantExportBtn?.addEventListener('click', e => {
    e.preventDefault();
    apiFetch('/tenants/export')
      .then(async r => {
        if (!r.ok) throw new Error('Error');
        const blob = await r.blob();
        const disposition = r.headers.get('Content-Disposition') || '';
        let filename = 'tenants.csv';
        const match = /filename="?([^";]+)"?/i.exec(disposition);
        if (match) {
          filename = match[1];
        }
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
      })
      .catch(() => notify(window.transErrorExportFailed || 'Export failed', 'danger'));
  });

  tenantReportBtn?.addEventListener('click', e => {
    e.preventDefault();
    apiFetch('/tenants/report')
      .then(async r => {
        if (!r.ok) throw new Error('Error');
        const contentType = r.headers.get('Content-Type') || '';
        const disposition = r.headers.get('Content-Disposition') || '';
        if (contentType.includes('pdf')) {
          const blob = await r.blob();
          const url = window.URL.createObjectURL(blob);
          window.open(url, '_blank');
          window.URL.revokeObjectURL(url);
          return;
        }
        if (contentType.includes('html')) {
          const text = await r.text();
          const w = window.open('', '_blank');
          if (w) {
            w.document.write(text);
            w.document.close();
          }
          return;
        }
        const blob = await r.blob();
        let filename = 'tenant-report.csv';
        const match = /filename="?([^";]+)"?/i.exec(disposition);
        if (match) {
          filename = match[1];
        }
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
      })
      .catch(() => notify(window.transErrorReportFailed || 'Report failed', 'danger'));
  });

  tenantSyncBtn?.addEventListener('click', e => {
    e.preventDefault();
    const original = tenantSyncBtn.innerHTML;
    tenantSyncBtn.disabled = true;
    tenantSyncBtn.innerHTML = '<div uk-spinner></div>';
    const params = new URLSearchParams();
    const normalizedStatus = normalizeTenantStatus(tenantStatusFilter?.value || '');
    const searchQueryRaw = typeof tenantSearchInput?.value === 'string' ? tenantSearchInput.value : '';
    const searchQuery = searchQueryRaw.trim();
    if (normalizedStatus) params.set('status', normalizedStatus);
    if (searchQuery) params.set('query', searchQuery);
    const syncUrl = '/tenants/sync' + (params.toString() ? ('?' + params.toString()) : '');
    let shouldRefresh = true;
    apiFetch(syncUrl, { method: 'POST' })
      .then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
          const error = new Error(typeof data?.error === 'string' ? data.error : 'sync-failed');
          error.data = data;
          throw error;
        }
        return data;
      })
      .then(data => {
        if (typeof data?.html === 'string' && applyTenantListHtml(data.html)) {
          shouldRefresh = false;
        }
        if (data?.sync) {
          updateTenantSyncState(data.sync);
        }
        if (data?.throttled) {
          notify(window.transTenantSyncThrottled || 'Sync already in progress \u2013 please try again later', 'warning');
          return;
        }

        const importedRaw = data?.imported;
        const importedNumber = typeof importedRaw === 'number'
          ? importedRaw
          : (typeof importedRaw === 'string' && importedRaw.trim() !== '' ? Number(importedRaw) : NaN);
        const imported = Number.isFinite(importedNumber) ? importedNumber : 0;

        if (imported > 0) {
          notify(window.transTenantSyncSuccess || 'Mandanten eingelesen', 'success');
        } else {
          const template = window.transTenantSyncNoChanges || 'No new tenants found ({count} imported)';
          const message = template.replace('{count}', String(imported));
          notify(message, 'warning');
        }
      })
      .catch(err => {
        console.error(err);
        notify(window.transErrorSyncFailed || 'Sync failed', 'danger');
      })
      .finally(() => {
        if (shouldRefresh) {
          refreshTenantList();
        }
        tenantSyncBtn.disabled = false;
        tenantSyncBtn.innerHTML = original;
      });
  });

  function loadTenants(status = '', query = '', showSpinner = true) {
    if (!tenantTableBody) return;
    if (typeof window.domainType !== 'undefined' && window.domainType !== 'main') {
      notify(window.transMainDomainMisconfigured || 'MAIN_DOMAIN misconfigured \u2013 tenant list not loaded', 'warning');
      return;
    }
    if (showSpinner) {
      showTenantSpinner();
    }
    const params = new URLSearchParams();
    const normalizedStatus = normalizeTenantStatus(status || '');
    const normalizedQuery = typeof query === 'string' ? query : '';
    if (normalizedStatus) params.set('status', normalizedStatus);
    if (normalizedQuery) params.set('query', normalizedQuery);
    const url = '/tenants' + (params.toString() ? ('?' + params.toString()) : '');
    apiFetch(url, { headers: { 'Accept': 'text/html' } })
      .then(r => r.ok ? r.text() : Promise.reject(r))
      .then((html) => {
        if (!applyTenantListHtml(html) && tenantTableBody) {
          tenantTableBody.innerHTML = '';
        }
      })
      .catch(() => notify(window.transTenantsLoadFailed || 'Tenants could not be loaded', 'danger'));
  }

  return {
    refreshTenantList,
    loadBackups,
    initBackupDropdowns,
    backupTableBody,
    get initialTenantHtmlApplied() { return initialTenantHtmlApplied; }
  };
}
