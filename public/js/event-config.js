(function(){
  const currentScript = document.currentScript;
  const basePath = window.basePath || (currentScript ? currentScript.dataset.base || '' : '');
  const withBase = (p) => basePath + p;
  let eventId = document.body?.dataset.eventId || currentScript?.dataset.eventId || window.eventId || '';
  const currentEventSelects = new Set();
  const notify = (message, status = 'primary') => {
    if (window.UIkit?.notification) {
      UIkit.notification({ message, status });
    }
  };
  const modulesList = document.querySelector('[data-dashboard-modules]');
  const modulesInput = document.getElementById('dashboardModules');
  const shareInputs = {
    public: document.querySelector('[data-share-link="public"]'),
    sponsor: document.querySelector('[data-share-link="sponsor"]')
  };
  let currentShareToken = '';
  let currentSponsorToken = '';
  let currentEventSlug = '';
  const teamNameHistorySection = document.querySelector('[data-team-name-history]');
  const teamNameHistoryTableBody = teamNameHistorySection?.querySelector('[data-team-name-history-body]') || null;
  const teamNameHistoryLimitSelect = teamNameHistorySection?.querySelector('[data-team-name-history-limit]') || null;
  const teamNameHistoryEmpty = teamNameHistorySection?.querySelector('[data-team-name-history-empty]') || null;
  const teamNameHistoryError = teamNameHistorySection?.querySelector('[data-team-name-history-error]') || null;
  const teamNameHistoryLoading = teamNameHistorySection?.querySelector('[data-team-name-history-loading]') || null;
  const teamNameHistoryEmptyDefault = teamNameHistoryEmpty?.textContent || '';
  const TEAM_NAME_HISTORY_STATUS_LABELS = {
    reserved: 'Reserviert',
    assigned: 'Zugewiesen',
    released: 'Freigegeben'
  };
  let teamNameHistoryFormatter;
  let teamNameHistoryEntries = [];
  let teamNameHistoryEpoch = 0;
  let teamNameHistorySort = { key: 'reserved_at', direction: 'desc' };
  const formatTeamNameHistoryStatus = (status) => {
    if (!status) {
      return '—';
    }
    return TEAM_NAME_HISTORY_STATUS_LABELS[status] || status;
  };
  const formatTeamNameHistoryDate = (value) => {
    if (!value) {
      return '—';
    }
    const timestamp = Date.parse(value);
    if (Number.isNaN(timestamp)) {
      return value;
    }
    if (!teamNameHistoryFormatter) {
      teamNameHistoryFormatter = new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'short',
        timeStyle: 'short'
      });
    }
    return teamNameHistoryFormatter.format(new Date(timestamp));
  };
  const setTeamNameHistoryLoading = (loading) => {
    if (!teamNameHistoryLoading) return;
    teamNameHistoryLoading.hidden = !loading;
  };
  const resetTeamNameHistory = (message = teamNameHistoryEmptyDefault) => {
    if (!teamNameHistorySection) return;
    teamNameHistoryEntries = [];
    if (teamNameHistoryTableBody) {
      teamNameHistoryTableBody.innerHTML = '';
    }
    if (teamNameHistoryError) {
      teamNameHistoryError.hidden = true;
    }
    if (teamNameHistoryEmpty) {
      teamNameHistoryEmpty.textContent = message;
      teamNameHistoryEmpty.hidden = false;
    }
    setTeamNameHistoryLoading(false);
    updateTeamNameHistorySortIndicators();
  };
  const parseTeamNameHistoryLimit = () => {
    if (!teamNameHistoryLimitSelect) {
      return null;
    }
    const parsed = Number.parseInt(teamNameHistoryLimitSelect.value || '', 10);
    if (Number.isNaN(parsed) || parsed <= 0) {
      return null;
    }
    return parsed;
  };
  const parseTeamNameHistoryTimestamp = (value) => {
    if (!value) {
      return null;
    }
    const timestamp = Date.parse(value);
    if (Number.isNaN(timestamp)) {
      return null;
    }
    return timestamp;
  };
  const compareTeamNameHistoryEntries = (a, b) => {
    const { key, direction } = teamNameHistorySort;
    const order = direction === 'asc' ? 1 : -1;
    if (key === 'name' || key === 'status') {
      const aValue = (a?.[key] ?? '').toString();
      const bValue = (b?.[key] ?? '').toString();
      const result = aValue.localeCompare(bValue, 'de', { sensitivity: 'base' });
      return result * order;
    }
    const aValue = parseTeamNameHistoryTimestamp(a?.[key]);
    const bValue = parseTeamNameHistoryTimestamp(b?.[key]);
    if (aValue === null && bValue === null) {
      return 0;
    }
    if (aValue === null) {
      return 1;
    }
    if (bValue === null) {
      return -1;
    }
    if (aValue === bValue) {
      return 0;
    }
    return aValue < bValue ? -order : order;
  };
  function updateTeamNameHistorySortIndicators() {
    if (!teamNameHistorySection) {
      return;
    }
    teamNameHistorySection.querySelectorAll('[data-sort-key]').forEach((header) => {
      const key = header.dataset.sortKey || '';
      if (!key) {
        return;
      }
      if (key === teamNameHistorySort.key) {
        header.dataset.sortDirection = teamNameHistorySort.direction;
        header.setAttribute('aria-sort', teamNameHistorySort.direction === 'asc' ? 'ascending' : 'descending');
      } else {
        header.dataset.sortDirection = '';
        header.setAttribute('aria-sort', 'none');
      }
    });
  }
  function renderTeamNameHistory() {
    if (!teamNameHistorySection || !teamNameHistoryTableBody) {
      return;
    }
    teamNameHistoryTableBody.innerHTML = '';
    if (teamNameHistoryEntries.length === 0) {
      if (teamNameHistoryEmpty) {
        teamNameHistoryEmpty.textContent = teamNameHistoryEmptyDefault;
        teamNameHistoryEmpty.hidden = false;
      }
      updateTeamNameHistorySortIndicators();
      return;
    }
    if (teamNameHistoryEmpty) {
      teamNameHistoryEmpty.textContent = teamNameHistoryEmptyDefault;
      teamNameHistoryEmpty.hidden = true;
    }
    teamNameHistoryEntries
      .slice()
      .sort(compareTeamNameHistoryEntries)
      .forEach((entry) => {
        const row = document.createElement('tr');
        const nameCell = document.createElement('td');
        nameCell.textContent = entry?.name || '—';
        row.appendChild(nameCell);
        const statusCell = document.createElement('td');
        statusCell.textContent = formatTeamNameHistoryStatus(entry?.status);
        row.appendChild(statusCell);
        const reservedCell = document.createElement('td');
        reservedCell.textContent = formatTeamNameHistoryDate(entry?.reserved_at);
        row.appendChild(reservedCell);
        const assignedCell = document.createElement('td');
        assignedCell.textContent = formatTeamNameHistoryDate(entry?.assigned_at);
        row.appendChild(assignedCell);
        const releasedCell = document.createElement('td');
        releasedCell.textContent = formatTeamNameHistoryDate(entry?.released_at);
        row.appendChild(releasedCell);
        teamNameHistoryTableBody.appendChild(row);
      });
    updateTeamNameHistorySortIndicators();
  }
  function refreshTeamNameHistory() {
    if (!teamNameHistorySection) {
      return;
    }
    if (!eventId) {
      resetTeamNameHistory('Kein Event ausgewählt.');
      return;
    }
    const params = new URLSearchParams({ event_uid: eventId });
    const limit = parseTeamNameHistoryLimit();
    if (limit) {
      params.set('limit', String(limit));
    }
    const currentEpoch = ++teamNameHistoryEpoch;
    if (teamNameHistoryError) {
      teamNameHistoryError.hidden = true;
    }
    if (teamNameHistoryEmpty) {
      teamNameHistoryEmpty.textContent = teamNameHistoryEmptyDefault;
    }
    setTeamNameHistoryLoading(true);
    fetch(withBase(`/api/team-names/history?${params.toString()}`), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' }
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('history-load-failed');
        }
        return response.json();
      })
      .then((payload) => {
        if (currentEpoch !== teamNameHistoryEpoch) {
          return;
        }
        const entries = Array.isArray(payload?.entries) ? payload.entries : [];
        teamNameHistoryEntries = entries;
        renderTeamNameHistory();
      })
      .catch(() => {
        if (currentEpoch !== teamNameHistoryEpoch) {
          return;
        }
        teamNameHistoryEntries = [];
        if (teamNameHistoryTableBody) {
          teamNameHistoryTableBody.innerHTML = '';
        }
        if (teamNameHistoryError) {
          teamNameHistoryError.hidden = false;
        }
        if (teamNameHistoryEmpty) {
          teamNameHistoryEmpty.hidden = true;
        }
        updateTeamNameHistorySortIndicators();
      })
      .finally(() => {
        if (currentEpoch === teamNameHistoryEpoch) {
          setTeamNameHistoryLoading(false);
        }
      });
  }
  function handleTeamNameHistorySort(key) {
    if (!key) {
      return;
    }
    if (teamNameHistorySort.key === key) {
      teamNameHistorySort = {
        key,
        direction: teamNameHistorySort.direction === 'asc' ? 'desc' : 'asc'
      };
    } else {
      teamNameHistorySort = {
        key,
        direction: key === 'name' ? 'asc' : 'desc'
      };
    }
    renderTeamNameHistory();
  }
  function setupTeamNameHistory() {
    if (!teamNameHistorySection) {
      return;
    }
    updateTeamNameHistorySortIndicators();
    teamNameHistorySection.querySelectorAll('[data-sort-key]').forEach((header) => {
      if (!header.hasAttribute('tabindex')) {
        header.setAttribute('tabindex', '0');
      }
      header.setAttribute('role', 'button');
      header.addEventListener('click', () => {
        handleTeamNameHistorySort(header.dataset.sortKey || '');
      });
      header.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          handleTeamNameHistorySort(header.dataset.sortKey || '');
        }
      });
    });
    teamNameHistoryLimitSelect?.addEventListener('change', () => {
      refreshTeamNameHistory();
    });
  }
  const RESULTS_DEFAULT_PAGE_INTERVAL = 10;
  const RESULTS_PAGE_INTERVAL_MIN = 1;
  const RESULTS_PAGE_INTERVAL_MAX = 300;
  const DEFAULT_MODULES = [
    { id: 'header', enabled: true, layout: 'full' },
    { id: 'pointsLeader', enabled: true, layout: 'wide', options: { title: 'Platzierungen', limit: 5 } },
    {
      id: 'rankings',
      enabled: true,
      layout: 'wide',
      options: {
        limit: null,
        pageSize: 10,
        pageInterval: RESULTS_DEFAULT_PAGE_INTERVAL,
        sort: 'time',
        title: 'Live-Rankings',
        showPlacement: false,
      },
    },
    {
      id: 'results',
      enabled: true,
      layout: 'full',
      options: {
        limit: null,
        pageSize: 10,
        pageInterval: RESULTS_DEFAULT_PAGE_INTERVAL,
        sort: 'time',
        title: 'Ergebnisliste',
        showPlacement: false,
      }
    },
    { id: 'wrongAnswers', enabled: false, layout: 'auto', options: { title: 'Falsch beantwortete Fragen' } },
    { id: 'infoBanner', enabled: false, layout: 'auto', options: { title: 'Hinweise' } },
    { id: 'rankingQr', enabled: false, layout: 'auto', options: { title: 'Ranking-QR' } },
    { id: 'qrCodes', enabled: false, layout: 'auto', options: { catalogs: [], title: 'Katalog-QR-Codes' } },
    { id: 'media', enabled: false, layout: 'auto', options: { title: 'Highlights' } },
  ];
  const LAYOUT_OPTIONS = ['auto', 'wide', 'full'];
  const RESULTS_SORT_OPTIONS = ['time', 'points', 'name'];
  const RESULTS_MAX_LIMIT = 50;
  const POINTS_LEADER_MIN_LIMIT = 1;
  const POINTS_LEADER_MAX_LIMIT = 10;
  const POINTS_LEADER_DEFAULT_LIMIT = 5;
  const DEFAULT_MODULE_MAP = new Map(DEFAULT_MODULES.map((module) => [module.id, module]));
  const parseBooleanOption = (candidate) => {
    if (candidate === null || candidate === undefined) {
      return null;
    }
    if (typeof candidate === 'boolean') {
      return candidate;
    }
    if (typeof candidate === 'number') {
      if (!Number.isFinite(candidate)) {
        return null;
      }
      if (candidate === 0) {
        return false;
      }
      if (candidate === 1) {
        return true;
      }
      return candidate > 0;
    }
    if (typeof candidate === 'string') {
      const normalized = candidate.trim().toLowerCase();
      if (normalized === '') {
        return null;
      }
      if (['1', 'true', 'yes', 'on'].includes(normalized)) {
        return true;
      }
      if (['0', 'false', 'no', 'off'].includes(normalized)) {
        return false;
      }
    }
    return null;
  };
  const resolveBooleanOption = (value, fallback = false) => {
    const parsed = parseBooleanOption(value);
    if (parsed !== null) {
      return parsed;
    }
    const fallbackParsed = parseBooleanOption(fallback);
    if (fallbackParsed !== null) {
      return fallbackParsed;
    }
    return Boolean(fallback);
  };
  const normalizeResultsLimit = (value) => {
    if (value === null || value === undefined) {
      return null;
    }
    const normalized = String(value).trim();
    if (normalized === '' || normalized === '0') {
      return null;
    }
    const parsed = Number.parseInt(normalized, 10);
    if (Number.isNaN(parsed) || parsed <= 0) {
      return null;
    }
    return Math.min(parsed, RESULTS_MAX_LIMIT);
  };

  const normalizeResultsPageSize = (value, limit) => {
    if (value === null || value === undefined) {
      return null;
    }
    const normalized = String(value).trim();
    if (normalized === '' || normalized === '0') {
      return null;
    }
    const parsed = Number.parseInt(normalized, 10);
    if (Number.isNaN(parsed) || parsed <= 0) {
      return null;
    }
    let resolved = Math.min(parsed, RESULTS_MAX_LIMIT);
    const normalizedLimit = typeof limit === 'number' && Number.isFinite(limit) && limit > 0
      ? Math.floor(limit)
      : null;
    if (normalizedLimit !== null && resolved > normalizedLimit) {
      resolved = normalizedLimit;
    }
    return resolved;
  };

  const normalizeResultsPageInterval = (value) => {
    if (value === null || value === undefined) {
      return null;
    }
    const normalized = String(value).trim();
    if (normalized === '' || normalized === '0') {
      return null;
    }
    const parsed = Number.parseInt(normalized, 10);
    if (Number.isNaN(parsed) || parsed < RESULTS_PAGE_INTERVAL_MIN) {
      return null;
    }
    return Math.min(parsed, RESULTS_PAGE_INTERVAL_MAX);
  };

  const normalizePointsLeaderLimit = (value) => {
    if (value === null || value === undefined) {
      return null;
    }
    const normalized = String(value).trim();
    if (normalized === '') {
      return null;
    }
    const parsed = Number.parseInt(normalized, 10);
    if (Number.isNaN(parsed) || parsed < POINTS_LEADER_MIN_LIMIT) {
      return null;
    }
    if (parsed > POINTS_LEADER_MAX_LIMIT) {
      return POINTS_LEADER_MAX_LIMIT;
    }
    return parsed;
  };

  const applyResultsOptionFields = (item, moduleId, options = {}) => {
    if (!item) return;
    const defaults = DEFAULT_MODULE_MAP.get(moduleId)?.options || {};
    const limitField = item.querySelector('[data-module-results-option="limit"]');
    const limitValue = normalizeResultsLimit(
      options && Object.prototype.hasOwnProperty.call(options, 'limit')
        ? options.limit
        : defaults.limit
    );
    if (limitField) {
      limitField.value = limitValue === null ? '' : String(limitValue);
    }
    const pageSizeField = item.querySelector('[data-module-results-option="pageSize"]');
    if (pageSizeField) {
      const pageSizeValue = normalizeResultsPageSize(
        options && Object.prototype.hasOwnProperty.call(options, 'pageSize')
          ? options.pageSize
          : defaults.pageSize,
        limitValue
      );
      pageSizeField.value = pageSizeValue === null ? '' : String(pageSizeValue);
    }
    const pageIntervalField = item.querySelector('[data-module-results-option="pageInterval"]');
    if (pageIntervalField) {
      const fallbackInterval = normalizeResultsPageInterval(defaults.pageInterval)
        ?? RESULTS_DEFAULT_PAGE_INTERVAL;
      const intervalValue = normalizeResultsPageInterval(
        options && Object.prototype.hasOwnProperty.call(options, 'pageInterval')
          ? options.pageInterval
          : defaults.pageInterval
      );
      const resolvedInterval = intervalValue ?? fallbackInterval;
      pageIntervalField.value = resolvedInterval === null ? '' : String(resolvedInterval);
    }
    const sortField = item.querySelector('[data-module-results-option="sort"]');
    if (sortField) {
      const fallbackSort = defaults.sort || 'time';
      const rawSort = typeof options?.sort === 'string' ? options.sort.trim() : '';
      sortField.value = RESULTS_SORT_OPTIONS.includes(rawSort) ? rawSort : fallbackSort;
    }
    const titleField = item.querySelector('[data-module-results-option="title"]');
    if (titleField) {
      const fallbackTitle = defaults.title || (moduleId === 'rankings' ? 'Live-Rankings' : 'Ergebnisliste');
      const rawTitle = typeof options?.title === 'string' ? options.title.trim() : '';
      titleField.value = rawTitle !== '' ? rawTitle : fallbackTitle;
    }
    const placementField = item.querySelector('[data-module-results-option="showPlacement"]');
    if (placementField) {
      const fallbackPlacement = resolveBooleanOption(defaults.showPlacement, false);
      const rawPlacement = Object.prototype.hasOwnProperty.call(options || {}, 'showPlacement')
        ? options.showPlacement
        : defaults.showPlacement;
      placementField.checked = resolveBooleanOption(rawPlacement, fallbackPlacement);
    }
    syncResultsPageSizeState(item);
  };

  const applyPointsLeaderOptionFields = (item, options = {}) => {
    if (!item) {
      return;
    }
    const field = item.querySelector('[data-module-points-leader-limit]');
    if (!field) {
      return;
    }
    const defaults = DEFAULT_MODULE_MAP.get('pointsLeader')?.options || {};
    const fallback = normalizePointsLeaderLimit(
      options && Object.prototype.hasOwnProperty.call(options, 'limit')
        ? options.limit
        : defaults.limit
    ) ?? normalizePointsLeaderLimit(defaults.limit) ?? POINTS_LEADER_DEFAULT_LIMIT;
    field.value = String(fallback);
  };

  const syncResultsPageSizeState = (item) => {
    if (!item) return;
    const limitField = item.querySelector('[data-module-results-option="limit"]');
    const pageSizeField = item.querySelector('[data-module-results-option="pageSize"]');
    if (!pageSizeField) return;
    pageSizeField.disabled = false;
    const limitValue = normalizeResultsLimit(limitField?.value);
    const rawValue = typeof pageSizeField.value === 'string' ? pageSizeField.value.trim() : '';
    if (rawValue === '') {
      return;
    }
    const normalized = normalizeResultsPageSize(rawValue, limitValue);
    if (normalized === null) {
      if (limitValue !== null) {
        pageSizeField.value = String(limitValue);
      } else {
        pageSizeField.value = '';
      }
    } else if (String(normalized) !== rawValue) {
      pageSizeField.value = String(normalized);
    }
  };
  const applyModuleTitleField = (item, moduleId, options = {}) => {
    if (!item) {
      return;
    }
    const field = item.querySelector('[data-module-title]');
    if (!field) {
      return;
    }
    const defaults = DEFAULT_MODULE_MAP.get(moduleId)?.options || {};
    const fallback = typeof defaults.title === 'string' && defaults.title.trim() !== ''
      ? defaults.title
      : (field.placeholder || '');
    const raw = typeof options?.title === 'string' ? options.title.trim() : '';
    field.value = raw !== '' ? options.title : fallback;
  };
  const readModuleTitle = (item, moduleId) => {
    const field = item.querySelector('[data-module-title]');
    if (!field) {
      return null;
    }
    const defaults = DEFAULT_MODULE_MAP.get(moduleId)?.options || {};
    const fallback = typeof defaults.title === 'string' ? defaults.title : '';
    const placeholder = typeof field.placeholder === 'string' ? field.placeholder : '';
    const base = fallback.trim() !== '' ? fallback : placeholder.trim();
    const raw = typeof field.value === 'string' ? field.value.trim() : '';
    const resolved = raw !== '' ? raw : base;
    return resolved !== '' ? resolved : null;
  };
  const QR_MODULE_ID = 'qrCodes';
  const qrModuleElement = modulesList?.querySelector('[data-module-id="' + QR_MODULE_ID + '"]') || null;
  const qrCatalogContainer = qrModuleElement?.querySelector('[data-module-catalogs]') || null;
  const qrCatalogEmptyNote = qrModuleElement?.querySelector('[data-module-catalogs-empty]') || null;
  let dashboardCatalogs = [];
  let catalogFetchEpoch = 0;
  let currentModulesConfig = DEFAULT_MODULES;

  const normalizeCatalogList = (rawList) => {
    if (!Array.isArray(rawList)) {
      return [];
    }
    return rawList.map((item) => {
      const uid = item?.uid ? String(item.uid) : '';
      const slug = item?.slug ? String(item.slug) : '';
      const sortOrder = item?.sort_order !== undefined && item?.sort_order !== null
        ? String(item.sort_order)
        : '';
      const name = item?.name ? String(item.name) : (slug || sortOrder || uid);
      return { uid, slug, sortOrder, name };
    });
  };

  const getQrModuleSelection = (modules) => {
    if (!Array.isArray(modules)) {
      return [];
    }
    const module = modules.find((entry) => entry && entry.id === QR_MODULE_ID);
    if (!module) {
      return [];
    }
    const raw = module.options?.catalogs;
    if (Array.isArray(raw)) {
      return raw
        .map((value) => String(value ?? '').trim())
        .filter((value) => value !== '');
    }
    if (typeof raw === 'string' && raw.trim() !== '') {
      return [raw.trim()];
    }
    return [];
  };

  function syncQrModuleOptions(selectedIds = [], mark = false) {
    if (!qrCatalogContainer) {
      if (mark) {
        updateModulesInput(true);
      }
      return;
    }
    const normalizedSelection = Array.isArray(selectedIds)
      ? Array.from(new Set(selectedIds.map((value) => String(value ?? '').trim()).filter((value) => value !== '')))
      : [];

    qrCatalogContainer.innerHTML = '';
    if (!dashboardCatalogs.length) {
      if (qrCatalogEmptyNote) {
        qrCatalogEmptyNote.hidden = false;
      }
      if (mark) {
        updateModulesInput(true);
      } else {
        updateModulesInput(false);
      }
      return;
    }

    if (qrCatalogEmptyNote) {
      qrCatalogEmptyNote.hidden = true;
    }

    const seen = new Set();
    dashboardCatalogs.forEach((catalog) => {
      const identifier = catalog.uid || catalog.slug || catalog.sortOrder || '';
      if (!identifier) {
        return;
      }
      const label = document.createElement('label');
      label.className = 'uk-display-block uk-margin-small-bottom';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'uk-checkbox';
      checkbox.value = identifier;
      checkbox.dataset.moduleCatalog = '1';
      if (catalog.uid) {
        checkbox.dataset.catalogUid = catalog.uid;
      }
      if (catalog.slug) {
        checkbox.dataset.catalogSlug = catalog.slug;
      }
      if (catalog.sortOrder) {
        checkbox.dataset.catalogSort = catalog.sortOrder;
      }
      if (
        normalizedSelection.includes(identifier)
        || (catalog.uid && normalizedSelection.includes(catalog.uid))
        || (catalog.slug && normalizedSelection.includes(catalog.slug))
        || (catalog.sortOrder && normalizedSelection.includes(String(catalog.sortOrder)))
      ) {
        checkbox.checked = true;
        seen.add(identifier);
        if (catalog.uid) seen.add(catalog.uid);
        if (catalog.slug) seen.add(catalog.slug);
        if (catalog.sortOrder) seen.add(String(catalog.sortOrder));
      }
      label.appendChild(checkbox);
      const span = document.createElement('span');
      span.className = 'uk-margin-small-left';
      span.textContent = catalog.name;
      label.appendChild(span);
      qrCatalogContainer.appendChild(label);
    });

    normalizedSelection.forEach((id) => {
      const normalizedId = String(id);
      if (seen.has(normalizedId)) {
        return;
      }
      const label = document.createElement('label');
      label.className = 'uk-display-block uk-margin-small-bottom uk-text-muted';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'uk-checkbox';
      checkbox.value = normalizedId;
      checkbox.dataset.moduleCatalog = '1';
      checkbox.checked = true;
      label.appendChild(checkbox);
      const span = document.createElement('span');
      span.className = 'uk-margin-small-left';
      span.textContent = `Nicht gefunden (${normalizedId})`;
      label.appendChild(span);
      qrCatalogContainer.appendChild(label);
    });

    if (mark) {
      updateModulesInput(true);
    } else {
      updateModulesInput(false);
    }
  }

  function loadDashboardCatalogOptions(selectedIds = []) {
    if (!modulesList) {
      return Promise.resolve();
    }
    const requestId = ++catalogFetchEpoch;
    return fetch(withBase('/kataloge/catalogs.json'), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then((res) => {
        if (!res.ok) {
          throw new Error('catalogs');
        }
        return res.json();
      })
      .then((payload) => {
        if (requestId !== catalogFetchEpoch) {
          return;
        }
        const list = Array.isArray(payload)
          ? payload
          : Array.isArray(payload?.items)
            ? payload.items
            : [];
        dashboardCatalogs = normalizeCatalogList(list);
        const domSelection = getQrModuleSelection(readModulesFromDom());
        const effectiveSelection = domSelection.length ? domSelection : selectedIds;
        syncQrModuleOptions(effectiveSelection, false);
      })
      .catch(() => {
        if (requestId !== catalogFetchEpoch) {
          return;
        }
        dashboardCatalogs = [];
        const domSelection = getQrModuleSelection(readModulesFromDom());
        const effectiveSelection = domSelection.length ? domSelection : selectedIds;
        syncQrModuleOptions(effectiveSelection, false);
      });
  }

  const getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
    currentScript?.dataset.csrf ||
    window.csrfToken || '';

  const csrfFetch = (path, options = {}) => {
    const token = getCsrfToken();
    const headers = {
      ...(options.headers || {}),
      ...(token ? { 'X-CSRF-Token': token } : {})
    };
    if (options.body instanceof FormData) {
      delete headers['Content-Type'];
    }
    return fetch(withBase(path), { credentials: 'same-origin', cache: 'no-store', ...options, headers });
  };

  const syncCurrentEventSelect = (select, uid) => {
    if (!select) return;
    const value = uid || '';
    const options = Array.from(select.options || []);
    const hasOption = options.some((opt) => (opt.value || '') === value);
    if (hasOption) {
      select.value = value;
    } else {
      select.value = '';
    }
    select.dataset.currentEventValue = hasOption ? value : '';
  };

  let eventSwitcherImport;

  const getEventSwitcher = () => {
    if (!eventSwitcherImport) {
      eventSwitcherImport = import(withBase('/js/event-switcher.js'));
    }
    return eventSwitcherImport;
  };

  const initializeCurrentEventSelect = (select) => {
    if (!select || currentEventSelects.has(select)) return;
    currentEventSelects.add(select);
    syncCurrentEventSelect(select, eventId);
    select.addEventListener('change', () => {
      const prevValue = select.dataset.currentEventValue || '';
      const prevDisabled = select.disabled;
      const uid = select.value || '';
      if (uid === prevValue) {
        return;
      }
      const option = select.options[select.selectedIndex] || null;
      const name = uid ? ((option?.textContent || '').trim()) : '';
      select.disabled = true;
      getEventSwitcher()
        .then(({ setCurrentEvent }) => {
          if (typeof setCurrentEvent !== 'function') {
            throw new Error('Fehler beim Wechseln des Events');
          }
          return setCurrentEvent(uid, name);
        })
        .then(() => {
          syncCurrentEventSelect(select, uid);
        })
        .catch((err) => {
          console.error(err);
          UIkit?.notification({ message: err.message || 'Fehler beim Wechseln des Events', status: 'danger' });
          syncCurrentEventSelect(select, prevValue);
        })
        .finally(() => {
          select.disabled = prevDisabled;
        });
    });
  };

  function collectData() {
    const data = new FormData();
    document.querySelectorAll('form input, form textarea, form select').forEach((el) => {
      const key = el.name || el.id;
      if (!key) return;
      if (el.hasAttribute('data-skip-save')) return;
      if (el.type === 'checkbox') {
        data.append(key, el.checked ? '1' : '0');
      } else if (el.type === 'file') {
        if (el.files?.[0]) data.append(key, el.files[0]);
      } else {
        let value = el.value;
        if (key === 'pageTitle' && !value) {
          value = 'Modernes Quiz mit UIkit';
        }
        data.append(key, value);
      }
    });
    return data;
  }

  function readModulesFromDom() {
    if (!modulesList) return [];
    const modules = [];
    modulesList.querySelectorAll('[data-module-id]').forEach((item) => {
      const id = item.dataset.moduleId || '';
      if (!id) return;
      const toggle = item.querySelector('[data-module-toggle]');
      const enabled = toggle ? toggle.checked : true;
      const entry = { id, enabled };
      const layoutField = item.querySelector('[data-module-layout]');
      const defaultLayout = DEFAULT_MODULE_MAP.get(id)?.layout
        || layoutField?.dataset.defaultLayout
        || 'auto';
      let layout = layoutField ? (layoutField.value || layoutField.dataset.defaultLayout || defaultLayout) : defaultLayout;
      layout = String(layout || '').trim();
      if (!LAYOUT_OPTIONS.includes(layout)) {
        layout = defaultLayout;
      }
      entry.layout = layout;
      if (id === 'rankings' || id === 'results') {
        const defaults = DEFAULT_MODULE_MAP.get(id)?.options || {};
        const limitField = item.querySelector('[data-module-results-option="limit"]');
        const pageSizeField = item.querySelector('[data-module-results-option="pageSize"]');
        const pageIntervalField = item.querySelector('[data-module-results-option="pageInterval"]');
        const sortField = item.querySelector('[data-module-results-option="sort"]');
        const titleField = item.querySelector('[data-module-results-option="title"]');
        const limitValue = limitField
          ? normalizeResultsLimit(limitField.value)
          : normalizeResultsLimit(defaults.limit);
        let pageSizeValue = pageSizeField
          ? normalizeResultsPageSize(pageSizeField.value, limitValue)
          : null;
        if (pageSizeValue === null) {
          pageSizeValue = normalizeResultsPageSize(defaults.pageSize, limitValue);
        }
        const defaultInterval = normalizeResultsPageInterval(defaults.pageInterval)
          ?? RESULTS_DEFAULT_PAGE_INTERVAL;
        let pageIntervalValue = pageIntervalField
          ? normalizeResultsPageInterval(pageIntervalField.value)
          : null;
        if (pageIntervalValue === null) {
          pageIntervalValue = defaultInterval;
        }
        const rawSort = sortField ? String(sortField.value || '').trim() : '';
        const sortValue = RESULTS_SORT_OPTIONS.includes(rawSort)
          ? rawSort
          : (defaults.sort || 'time');
        let titleValue = titleField ? titleField.value.trim() : '';
        if (titleValue === '') {
          titleValue = defaults.title || (id === 'rankings' ? 'Live-Rankings' : 'Ergebnisliste');
        }
        const placementField = item.querySelector('[data-module-results-option="showPlacement"]');
        let placementValue = false;
        if (placementField) {
          placementValue = placementField.checked;
        } else if (Object.prototype.hasOwnProperty.call(defaults, 'showPlacement')) {
          placementValue = defaults.showPlacement === true;
        }
        const supportsPlacement = Object.prototype.hasOwnProperty.call(defaults, 'showPlacement');
        entry.options = {
          limit: limitValue,
          pageSize: pageSizeValue,
          pageInterval: pageIntervalValue,
          sort: sortValue,
          title: titleValue,
          ...(supportsPlacement ? { showPlacement: placementValue } : {}),
        };
      } else if (id === 'pointsLeader') {
        const defaults = DEFAULT_MODULE_MAP.get(id)?.options || {};
        const limitField = item.querySelector('[data-module-points-leader-limit]');
        const fallbackLimit = normalizePointsLeaderLimit(defaults.limit) ?? POINTS_LEADER_DEFAULT_LIMIT;
        const limitValue = limitField
          ? normalizePointsLeaderLimit(limitField.value)
          : null;
        const resolvedLimit = limitValue ?? fallbackLimit;
        entry.options = { limit: resolvedLimit };
      } else if (id === QR_MODULE_ID) {
        const catalogs = [];
        item.querySelectorAll('[data-module-catalog]').forEach((catalogEl) => {
          if (!catalogEl.checked) {
            return;
          }
          const rawValue = catalogEl.value
            || catalogEl.dataset.catalogUid
            || catalogEl.dataset.catalogSlug
            || catalogEl.dataset.catalogSort
            || '';
          const normalized = String(rawValue).trim();
          if (normalized && !catalogs.includes(normalized)) {
            catalogs.push(normalized);
          }
        });
        entry.options = { catalogs };
      }
      const titleValue = readModuleTitle(item, id);
      if (titleValue !== null) {
        if (!entry.options) {
          entry.options = {};
        }
        entry.options.title = titleValue;
      }
      modules.push(entry);
    });
    return modules;
  }

  function applyModules(modules) {
    if (!modulesList) return;
    const configured = Array.isArray(modules) && modules.length ? modules : DEFAULT_MODULES;
    currentModulesConfig = configured;
    const map = new Map();
    modulesList.querySelectorAll('[data-module-id]').forEach((item) => {
      map.set(item.dataset.moduleId, item);
    });
    configured.forEach((module) => {
      const item = map.get(module.id);
      if (!item) return;
      modulesList.appendChild(item);
      const toggle = item.querySelector('[data-module-toggle]');
      if (toggle) toggle.checked = !!module.enabled;
      const defaultLayout = DEFAULT_MODULE_MAP.get(module.id)?.layout
        || item.querySelector('[data-module-layout]')?.dataset.defaultLayout
        || 'auto';
      const layoutField = item.querySelector('[data-module-layout]');
      const layoutValue = LAYOUT_OPTIONS.includes(module.layout) ? module.layout : defaultLayout;
      if (layoutField) {
        layoutField.value = layoutValue;
      }
      if (module.id === 'rankings' || module.id === 'results') {
        applyResultsOptionFields(item, module.id, module.options || {});
      } else if (module.id === 'pointsLeader') {
        applyPointsLeaderOptionFields(item, module.options || {});
      }
      applyModuleTitleField(item, module.id, module.options || {});
    });
    DEFAULT_MODULES.forEach((module) => {
      if (!configured.some((entry) => entry.id === module.id)) {
        const item = map.get(module.id);
        if (!item) return;
        modulesList.appendChild(item);
        const toggle = item.querySelector('[data-module-toggle]');
        if (toggle) toggle.checked = !!module.enabled;
        const defaultLayout = DEFAULT_MODULE_MAP.get(module.id)?.layout
          || item.querySelector('[data-module-layout]')?.dataset.defaultLayout
          || 'auto';
        const layoutField = item.querySelector('[data-module-layout]');
        if (layoutField) {
          layoutField.value = defaultLayout;
        }
        if (module.id === 'rankings' || module.id === 'results') {
          applyResultsOptionFields(item, module.id, module.options || {});
        } else if (module.id === 'pointsLeader') {
          applyPointsLeaderOptionFields(item, module.options || {});
        }
        applyModuleTitleField(item, module.id, module.options || {});
      }
    });
    const qrSelection = getQrModuleSelection(configured);
    syncQrModuleOptions(qrSelection, false);
  }

  function updateModulesInput(mark = false) {
    if (!modulesInput) return;
    const modules = readModulesFromDom();
    try {
      modulesInput.value = JSON.stringify(modules);
    } catch (err) {
      modulesInput.value = '[]';
    }
    if (mark) {
      markDirty();
    }
  }

  function buildShareLink(variant) {
    const token = variant === 'sponsor' ? currentSponsorToken : currentShareToken;
    if (!token || !currentEventSlug) return '';
    const path = `/event/${encodeURIComponent(currentEventSlug)}/dashboard/${encodeURIComponent(token)}`;
    const url = new URL(withBase(path), window.location.origin);
    if (variant === 'sponsor') {
      url.searchParams.set('variant', 'sponsor');
    }
    return url.toString();
  }

  function updateShareInputs() {
    if (shareInputs.public) {
      shareInputs.public.value = buildShareLink('public');
    }
    if (shareInputs.sponsor) {
      shareInputs.sponsor.value = buildShareLink('sponsor');
    }
  }

  const puzzleWordEnabled = document.getElementById('puzzleWordEnabled');
  const puzzleWord = document.getElementById('puzzleWord');
  const puzzleFeedback = document.getElementById('puzzleFeedback');
  const countdownEnabled = document.getElementById('countdownEnabled');
  const countdownInput = document.getElementById('countdown');
  const logoInput = document.getElementById('logo');
  const logoPreview = document.getElementById('logoPreview');
  const publishBtn = document.querySelector('.event-config-sidebar .uk-button-primary');
  const eventSettingsHeading = document.getElementById('eventSettingsHeading');

  function applyRules() {
    if (puzzleWordEnabled && puzzleWord && puzzleFeedback) {
      const enabled = puzzleWordEnabled.checked;
      puzzleWord.disabled = !enabled;
      puzzleFeedback.disabled = !enabled;
    }
    if (countdownEnabled && countdownInput) {
      const enabledCountdown = countdownEnabled.checked;
      countdownInput.disabled = !enabledCountdown;
    }
  }

  function clearForm() {
    document.querySelectorAll('form input, form textarea, form select').forEach((el) => {
      if (el.type === 'checkbox') {
        el.checked = false;
      } else if (el.type === 'file') {
        el.value = '';
      } else {
        el.value = '';
      }
    });
    if (logoPreview) {
      logoPreview.src = '';
      logoPreview.hidden = true;
    }
    currentShareToken = '';
    currentSponsorToken = '';
    currentEventSlug = '';
    dashboardCatalogs = [];
    catalogFetchEpoch += 1;
    if (modulesList) {
      applyModules([]);
    }
    updateShareInputs();
    applyRules();
    resetTeamNameHistory();
  }

  function loadConfig(uid) {
    if (!uid) {
      applyRules();
      return Promise.resolve();
    }
    return fetch(withBase(`/admin/event/${uid}`), { credentials: 'same-origin', cache: 'no-store' })
      .then((res) => {
        if (!res.ok) throw new Error('Failed to load');
        return res.json();
      })
      .then(({ event, config }) => {
        const data = { ...config };
        if (event?.name && !data.pageTitle) data.pageTitle = event.name;
        const modulesConfig = Array.isArray(config?.dashboardModules) ? config.dashboardModules : [];
        Object.entries(data).forEach(([key, value]) => {
          if (Array.isArray(value)) return;
          const el = document.getElementById(key);
          if (!el) return;
          if (el.type === 'checkbox') {
            el.checked = !!value;
          } else if (el.tagName === 'IMG') {
            el.src = value;
            el.hidden = !value;
          } else if (el.type !== 'file') {
            el.value = value ?? '';
          }
        });
        if (config?.logoPath && logoPreview) {
          logoPreview.src = withBase(config.logoPath);
          logoPreview.hidden = false;
        }
        currentEventSlug = event?.slug || currentEventSlug;
        currentShareToken = config?.dashboardShareToken || '';
        currentSponsorToken = config?.dashboardSponsorToken || '';
        if (modulesList) {
          applyModules(modulesConfig);
          updateModulesInput(false);
        }
        loadDashboardCatalogOptions(getQrModuleSelection(modulesConfig));
        updateShareInputs();
        applyRules();
        isDirty = false;
      })
      .catch(() => {
        UIkit?.notification({ message: 'Konfiguration konnte nicht geladen werden', status: 'danger' });
        applyRules();
      });
  }

  function save() {
    if (!eventId) return;
    const body = collectData();
    const method = (typeof FormData !== 'undefined' && body instanceof FormData) ? 'POST' : 'PATCH';
    csrfFetch(`/admin/event/${eventId}`, {
      method,
      body
    })
      .then((res) => {
        if (!res.ok) {
          throw new Error('Failed to save');
        }
        UIkit?.notification({ message: 'Einstellungen gespeichert', status: 'success' });
      })
      .catch(() => {
        UIkit?.notification({ message: 'Fehler beim Speichern', status: 'danger' });
      })
      .finally(() => {
        isDirty = false;
      });
  }

  let autosaveTimer;
  let isDirty = false;
  function markDirty() {
    isDirty = true;
    queueAutosave();
  }
  function queueAutosave() {
    if (!isDirty) return;
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(save, 800);
  }

  document.addEventListener('event:changed', (e) => {
    const { uid = '', name = '', pending = false } = e.detail || {};
    eventId = pending ? '' : uid;
    clearTimeout(autosaveTimer);
    isDirty = false;
    clearForm();
    currentEventSelects.forEach((select) => {
      syncCurrentEventSelect(select, eventId);
    });
    if (eventSettingsHeading) {
      eventSettingsHeading.textContent = name
        ? `${name} – ${eventSettingsHeading.dataset.title}`
        : eventSettingsHeading.dataset.title;
    }
    if (eventId) {
      loadConfig(eventId).catch(() => {
        window.location.href = withBase(`/admin/event-config?event=${eventId}`);
      });
    } else {
      applyRules();
    }
    refreshTeamNameHistory();
  });

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-current-event-select]').forEach((select) => {
      initializeCurrentEventSelect(select);
    });
    setupTeamNameHistory();
    if (eventId) {
      loadConfig(eventId);
    } else {
      applyRules();
      if (modulesList) {
        applyModules([]);
      }
      updateShareInputs();
    }
    refreshTeamNameHistory();
    puzzleWordEnabled?.addEventListener('change', applyRules);
    countdownEnabled?.addEventListener('change', applyRules);
    publishBtn?.addEventListener('click', (e) => { e.preventDefault(); save(); });
    document.querySelectorAll('form input, form textarea, form select').forEach((el) => {
      if (el.hasAttribute('data-skip-save')) return;
      el.addEventListener('input', markDirty);
      el.addEventListener('change', markDirty);
    });
    logoInput?.addEventListener('change', () => {
      const file = logoInput.files?.[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (ev) => {
        if (logoPreview) {
          logoPreview.src = ev.target.result;
          logoPreview.hidden = false;
        }
      };
      reader.readAsDataURL(file);
    });
    modulesList?.addEventListener('change', (event) => {
      if (event.target.matches('[data-module-results-option="limit"]')) {
        const moduleItem = event.target.closest('[data-module-id]');
        syncResultsPageSizeState(moduleItem);
      }
      if (event.target.matches('[data-module-points-leader-limit]')) {
        updateModulesInput(true);
        return;
      }
      if (event.target.matches('[data-module-toggle], [data-module-catalog], [data-module-layout], [data-module-results-option], [data-module-title]')) {
        updateModulesInput(true);
      }
    });
    modulesList?.addEventListener('input', (event) => {
      if (event.target.matches('[data-module-results-option="limit"]')) {
        const moduleItem = event.target.closest('[data-module-id]');
        syncResultsPageSizeState(moduleItem);
      }
      if (event.target.matches('[data-module-points-leader-limit]')) {
        updateModulesInput(true);
        return;
      }
      if (event.target.matches('[data-module-results-option], [data-module-title]')) {
        updateModulesInput(true);
      }
    });
    modulesList?.addEventListener('moved', () => {
      updateModulesInput(true);
    });
    document.querySelectorAll('[data-copy-link]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const variant = btn.dataset.copyLink === 'sponsor' ? 'sponsor' : 'public';
        const input = shareInputs[variant];
        if (!input || !input.value) {
          notify('Kein Link verfügbar', 'warning');
          return;
        }
        if (navigator.clipboard?.writeText) {
          navigator.clipboard
            .writeText(input.value)
            .then(() => notify('Link kopiert', 'success'))
            .catch(() => notify('Kopieren fehlgeschlagen', 'danger'));
        } else {
          input.select();
          try {
            document.execCommand('copy');
            notify('Link kopiert', 'success');
          } catch (err) {
            notify('Kopieren fehlgeschlagen', 'danger');
          }
        }
      });
    });
    document.querySelectorAll('[data-rotate-token]').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (!eventId) {
          notify('Kein Event ausgewählt', 'warning');
          return;
        }
        const variant = btn.dataset.rotateToken === 'sponsor' ? 'sponsor' : 'public';
        btn.disabled = true;
        csrfFetch(`/admin/event/${eventId}/dashboard-token`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ variant })
        })
          .then((res) => {
            if (!res.ok) throw new Error('rotate-failed');
            return res.json();
          })
          .then((payload) => {
            const token = payload?.token || '';
            if (variant === 'sponsor') {
              currentSponsorToken = token;
            } else {
              currentShareToken = token;
            }
            updateShareInputs();
            notify('Neues Token erstellt', 'success');
          })
          .catch(() => {
            notify('Token konnte nicht erneuert werden', 'danger');
          })
          .finally(() => {
            btn.disabled = false;
          });
      });
    });
    if (modulesInput && !modulesInput.value) {
      updateModulesInput(false);
    }
  });
})();
