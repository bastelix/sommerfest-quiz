import { ResultsDataService, computeRankings } from './results-data-service.js';
import { formatTimestamp, formatPointsCell, insertSoftHyphens } from './results-utils.js';

const config = window.dashboardConfig || {};
const basePath = window.basePath || '';
const modulesRoot = document.querySelector('[data-dashboard-root]');
const statusLabel = document.getElementById('dashboardStatusLabel');
const refreshBtn = document.getElementById('dashboardRefreshBtn');
const headerContainer = document.querySelector('[data-dashboard-header]');

const moduleState = new Map();

const activeModules = Array.isArray(config.modules) ? config.modules : [];
const infoText = config.infoText || '';
const mediaItems = Array.isArray(config.mediaItems) ? config.mediaItems : [];
const refreshInterval = Math.max(5, Number(config.refreshInterval) || 15);
const eventIdentifier = config.slug || config.eventUid || '';
const competitionMode = Boolean(config.competitionMode);
const DASHBOARD_LAYOUT_OPTIONS = new Set(['auto', 'wide', 'full']);

const dashboardTheme = typeof config.theme === 'string' ? config.theme.trim().toLowerCase() : '';
if (dashboardTheme === 'dark' || dashboardTheme === 'light') {
  document.body?.setAttribute('data-theme', dashboardTheme);
}

const MODULE_DEFAULT_LAYOUTS = {
  header: 'full',
  pointsLeader: 'wide',
  rankings: 'wide',
  results: 'full',
  wrongAnswers: 'auto',
  infoBanner: 'auto',
  qrCodes: 'auto',
  rankingQr: 'auto',
  media: 'auto',
};
const RESULTS_DEFAULT_PAGE_INTERVAL = 10;
const RESULTS_PAGE_INTERVAL_MIN = 1;
const RESULTS_PAGE_INTERVAL_MAX = 300;
const RESULTS_DEFAULT_OPTIONS = {
  limit: null,
  pageSize: 10,
  pageInterval: RESULTS_DEFAULT_PAGE_INTERVAL,
  sort: 'time',
  title: 'Ergebnisliste',
  showPlacement: false,
};
const RESULTS_SORT_OPTIONS = new Set(['time', 'points', 'name']);
const RESULTS_LIMIT_MAX = 50;
const POINTS_LEADER_DEFAULT_OPTIONS = { title: 'Platzierungen', limit: 5 };
const POINTS_LEADER_LIMIT_MAX = 10;

function parseBooleanFlag(value) {
  if (value === null || value === undefined) {
    return false;
  }
  if (typeof value === 'boolean') {
    return value;
  }
  if (typeof value === 'number') {
    return value !== 0;
  }
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    if (normalized === '') {
      return false;
    }
    return ['1', 'true', 'yes', 'on'].includes(normalized);
  }
  return false;
}

const dataService = new ResultsDataService({
  basePath,
  eventUid: config.eventUid || '',
  shareToken: config.shareToken || '',
  variant: config.variant || 'public',
});

let lastUpdatedAt = null;
let pollingTimer = null;
let resultsPagerTimer = null;

function applyHeaderVisibility(modules) {
  if (!headerContainer) return;
  const headerModule = modules.find((module) => module.id === 'header');
  headerContainer.hidden = headerModule ? !headerModule.enabled : false;
}

function updateStatusLabel() {
  if (!statusLabel) return;
  if (!lastUpdatedAt) {
    statusLabel.textContent = 'Noch nicht geladen';
    return;
  }
  const diffSeconds = Math.max(0, Math.round((Date.now() - lastUpdatedAt) / 1000));
  statusLabel.textContent = `Zuletzt aktualisiert vor ${diffSeconds} s`;
}

function clearResultsPagerTimer() {
  if (resultsPagerTimer) {
    clearInterval(resultsPagerTimer);
    resultsPagerTimer = null;
  }
}

function resolveModuleLayout(moduleConfig) {
  if (!moduleConfig || !moduleConfig.id) {
    return 'auto';
  }
  const fallback = MODULE_DEFAULT_LAYOUTS[moduleConfig.id] || 'auto';
  const raw = typeof moduleConfig.layout === 'string' ? moduleConfig.layout.trim() : '';
  if (raw && DASHBOARD_LAYOUT_OPTIONS.has(raw)) {
    return raw;
  }
  return fallback;
}

function createModuleCard(title, content, layout = 'auto') {
  const normalizedLayout = DASHBOARD_LAYOUT_OPTIONS.has(layout) ? layout : 'auto';
  const wrapper = document.createElement('article');
  wrapper.className = `dashboard-tile dashboard-tile--${normalizedLayout}`;
  const card = document.createElement('div');
  card.className = 'uk-card uk-card-default uk-card-body';
  const heading = document.createElement('h3');
  heading.className = 'uk-heading-bullet';
  heading.textContent = title;
  card.appendChild(heading);
  card.appendChild(content);
  wrapper.appendChild(card);
  return wrapper;
}

function formatPercentage(value) {
  if (!Number.isFinite(value)) {
    return null;
  }
  const clamped = Math.max(0, Math.min(value, 1));
  const percent = Math.round(clamped * 1000) / 10;
  const localized = percent.toString().replace('.', ',');
  return `${localized} %`;
}

function parseResultNumber(value) {
  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null;
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (trimmed === '') {
      return null;
    }
    const parsed = Number(trimmed);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

function resolveResultsOptions(moduleConfig, overrideDefaults = {}) {
  const options = moduleConfig?.options ?? {};
  const defaults = { ...RESULTS_DEFAULT_OPTIONS, ...overrideDefaults };
  const limitCandidate = options.limit ?? defaults.limit;
  let limit = null;
  if (limitCandidate !== null && limitCandidate !== undefined) {
    const parsedLimit = parseResultNumber(limitCandidate);
    if (parsedLimit !== null && parsedLimit > 0) {
      limit = Math.min(Math.floor(parsedLimit), RESULTS_LIMIT_MAX);
    }
  }
  const pageSizeCandidate = options.pageSize ?? defaults.pageSize;
  let pageSize = null;
  const parsedPageSize = parseResultNumber(pageSizeCandidate);
  if (parsedPageSize !== null) {
    const normalizedPageSize = Math.floor(parsedPageSize);
    if (normalizedPageSize > 0) {
      pageSize = Math.min(normalizedPageSize, RESULTS_LIMIT_MAX);
    }
  }
  let effectivePageSize = null;
  if (pageSize !== null) {
    effectivePageSize = pageSize;
    if (limit !== null) {
      effectivePageSize = Math.min(effectivePageSize, limit);
    }
  } else if (limit !== null) {
    effectivePageSize = limit;
  }
  if (effectivePageSize !== null && effectivePageSize <= 0) {
    effectivePageSize = null;
  }
  const intervalCandidate = options.pageInterval ?? defaults.pageInterval;
  let pageInterval = null;
  const parsedInterval = parseResultNumber(intervalCandidate);
  if (parsedInterval !== null) {
    const normalizedInterval = Math.floor(parsedInterval);
    if (normalizedInterval >= RESULTS_PAGE_INTERVAL_MIN) {
      pageInterval = Math.min(normalizedInterval, RESULTS_PAGE_INTERVAL_MAX);
    }
  }
  if (pageInterval === null || !Number.isFinite(pageInterval)) {
    pageInterval = defaults.pageInterval ?? RESULTS_DEFAULT_PAGE_INTERVAL;
  }
  const rawSort = typeof options.sort === 'string' ? options.sort.trim() : '';
  const sort = RESULTS_SORT_OPTIONS.has(rawSort) ? rawSort : defaults.sort;
  const rawTitle = typeof options.title === 'string' ? options.title.trim() : '';
  const title = rawTitle !== '' ? rawTitle : defaults.title;
  const showPlacement = parseBooleanFlag(
    Object.prototype.hasOwnProperty.call(options, 'showPlacement')
      ? options.showPlacement
      : defaults.showPlacement
  );
  return {
    limit,
    pageSize,
    effectivePageSize,
    pageInterval,
    sort,
    title,
    showPlacement,
  };
}

function resolveModuleTitle(moduleConfig, fallback) {
  const safeFallback = typeof fallback === 'string' ? fallback : '';
  const rawTitle = moduleConfig?.options?.title;
  if (typeof rawTitle === 'string') {
    const trimmed = rawTitle.trim();
    if (trimmed !== '') {
      return trimmed;
    }
  }
  return safeFallback;
}

function resolvePointsLeaderOptions(moduleConfig) {
  const defaults = POINTS_LEADER_DEFAULT_OPTIONS;
  const options = moduleConfig?.options ?? {};
  const limitCandidate = options.limit ?? defaults.limit;
  let limit = defaults.limit;
  const parsedLimit = parseResultNumber(limitCandidate);
  if (parsedLimit !== null && parsedLimit > 0) {
    const normalized = Math.max(1, Math.floor(parsedLimit));
    limit = Math.min(normalized, POINTS_LEADER_LIMIT_MAX);
  }
  const title = resolveModuleTitle(moduleConfig, defaults.title);
  return { limit, title };
}

function renderPointsLeaderModule(rankings, moduleConfig) {
  const container = document.createElement('div');
  container.className = 'dashboard-leader';
  const list = Array.isArray(rankings?.pointsList) ? rankings.pointsList : [];
  const { title, limit } = resolvePointsLeaderOptions(moduleConfig);
  if (list.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'uk-text-meta';
    empty.textContent = 'Noch keine Punkte erfasst.';
    container.appendChild(empty);
    return createModuleCard(title, container, resolveModuleLayout(moduleConfig));
  }

  const leader = list[0];
  const runnerUp = list.length > 1 ? list[1] : null;

  const summary = document.createElement('div');
  summary.className = 'dashboard-leader__summary';

  const summaryLabel = document.createElement('div');
  summaryLabel.className = 'dashboard-leader__label';
  summaryLabel.textContent = 'Aktueller Spitzenreiter';
  summary.appendChild(summaryLabel);

  const leaderName = document.createElement('div');
  leaderName.className = 'dashboard-leader__name';
  leaderName.textContent = leader?.name || '–';
  summary.appendChild(leaderName);

  const pointsValue = Number.isFinite(leader?.raw) ? Math.round(leader.raw) : null;
  const leaderPoints = document.createElement('div');
  leaderPoints.className = 'dashboard-leader__points';
  leaderPoints.textContent = pointsValue !== null ? `${pointsValue} Punkte` : (leader?.value || '–');
  summary.appendChild(leaderPoints);

  if (Number.isFinite(leader?.avg)) {
    const efficiency = document.createElement('div');
    efficiency.className = 'dashboard-leader__meta';
    const percentage = formatPercentage(leader.avg);
    if (percentage) {
      efficiency.textContent = `Trefferquote: ${percentage}`;
      summary.appendChild(efficiency);
    }
  }

  if (runnerUp && Number.isFinite(leader?.raw) && Number.isFinite(runnerUp.raw)) {
    const diff = Math.round(leader.raw - runnerUp.raw);
    const leadLine = document.createElement('div');
    leadLine.className = 'dashboard-leader__meta';
    if (diff > 0) {
      leadLine.textContent = `Vorsprung: ${diff} Punkt${diff === 1 ? '' : 'e'} vor ${runnerUp.name}`;
    } else {
      leadLine.textContent = `Gleichauf mit ${runnerUp.name}`;
    }
    summary.appendChild(leadLine);
  }

  container.appendChild(summary);

  const listElement = document.createElement('ol');
  listElement.className = 'dashboard-leader__list uk-list uk-list-striped';

  const leaderPointsRaw = Number.isFinite(leader?.raw) ? leader.raw : null;
  const visibleEntries = Math.max(1, Number.isFinite(limit) ? limit : POINTS_LEADER_DEFAULT_OPTIONS.limit);
  list.slice(0, visibleEntries).forEach((entry, index) => {
    const item = document.createElement('li');

    const row = document.createElement('div');
    row.className = 'uk-flex uk-flex-between';
    const nameSpan = document.createElement('span');
    nameSpan.textContent = `${index + 1}. ${entry.name}`;
    const pointsSpan = document.createElement('span');
    if (Number.isFinite(entry.raw)) {
      pointsSpan.textContent = `${Math.round(entry.raw)} Punkte`;
    } else {
      pointsSpan.textContent = entry.value || '–';
    }
    row.appendChild(nameSpan);
    row.appendChild(pointsSpan);
    item.appendChild(row);

    if (index === 0) {
      const note = document.createElement('div');
      note.className = 'dashboard-leader__delta uk-text-meta';
      note.textContent = 'Führt nach Punkten';
      item.appendChild(note);
    } else if (leaderPointsRaw !== null && Number.isFinite(entry.raw)) {
      const delta = Math.round(leaderPointsRaw - entry.raw);
      const note = document.createElement('div');
      note.className = 'dashboard-leader__delta uk-text-meta';
      if (delta > 0) {
        note.textContent = `Rückstand: ${delta} Punkt${delta === 1 ? '' : 'e'}`;
      } else {
        note.textContent = 'Gleichauf mit der Spitze';
      }
      item.appendChild(note);
    }

    listElement.appendChild(item);
  });

  container.appendChild(listElement);

  return createModuleCard(title, container, resolveModuleLayout(moduleConfig));
}

function findCatalogByIdentifier(identifier, catalogList) {
  const normalized = String(identifier ?? '').trim();
  if (!normalized) {
    return null;
  }
  return catalogList.find((catalog) => {
    if (!catalog) return false;
    const uid = catalog.uid ? String(catalog.uid) : '';
    const slug = catalog.slug ? String(catalog.slug) : '';
    const sortOrder = catalog.sortOrder ?? catalog.sort_order;
    const sortValue = sortOrder !== undefined && sortOrder !== null ? String(sortOrder) : '';
    return normalized === uid || normalized === slug || (sortValue !== '' && normalized === sortValue);
  }) || null;
}

function buildCatalogStartUrl(catalog) {
  if (!catalog) {
    return null;
  }
  const slug = catalog.slug ? String(catalog.slug) : '';
  const uid = catalog.uid ? String(catalog.uid) : '';
  const sortOrder = catalog.sortOrder ?? catalog.sort_order;
  const sortValue = sortOrder !== undefined && sortOrder !== null ? String(sortOrder) : '';
  const identifier = slug || uid || sortValue;
  if (!identifier) {
    return null;
  }
  const startUrl = new URL(`${basePath || ''}/`, window.location.origin);
  if (eventIdentifier) {
    startUrl.searchParams.set('event', eventIdentifier);
  }
  startUrl.searchParams.set('katalog', identifier);
  return startUrl.toString();
}

function buildRankingUrl() {
  const url = new URL(`${basePath || ''}/ranking`, window.location.origin);
  if (config.eventUid) {
    url.searchParams.set('event', config.eventUid);
  }
  return url.toString();
}

function renderQrModule(moduleConfig, catalogList) {
  const selection = Array.isArray(moduleConfig.options?.catalogs)
    ? moduleConfig.options.catalogs
    : [];
  const normalizedSelection = Array.from(new Set(selection.map((value) => String(value ?? '').trim()).filter((value) => value !== '')));
  const grid = document.createElement('div');
  grid.className = 'dashboard-qr-grid';
  let renderedCount = 0;
  const missing = [];

  normalizedSelection.forEach((identifier) => {
    const catalog = findCatalogByIdentifier(identifier, catalogList);
    if (!catalog) {
      missing.push(identifier);
      return;
    }
    const startUrl = buildCatalogStartUrl(catalog);
    if (!startUrl) {
      missing.push(identifier);
      return;
    }
    const qrUrl = new URL(`${basePath}/qr/catalog`, window.location.origin);
    qrUrl.searchParams.set('t', startUrl);
    const card = document.createElement('div');
    card.className = 'dashboard-qr-card uk-card uk-card-default uk-card-body';
    const title = document.createElement('h4');
    title.className = 'uk-card-title';
    title.textContent = catalog.name || catalog.slug || catalog.uid || 'Katalog';
    card.appendChild(title);
    const img = document.createElement('img');
    img.src = qrUrl.toString();
    img.alt = `QR-Code für ${title.textContent}`;
    img.loading = 'lazy';
    card.appendChild(img);
    const link = document.createElement('p');
    link.className = 'dashboard-qr-link uk-text-meta';
    link.textContent = startUrl;
    card.appendChild(link);
    grid.appendChild(card);
    renderedCount += 1;
  });

  if (renderedCount === 0 && normalizedSelection.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'uk-text-meta';
    empty.textContent = 'Keine QR-Codes konfiguriert.';
    grid.appendChild(empty);
  }

  missing.forEach((identifier) => {
    const warning = document.createElement('div');
    warning.className = 'dashboard-qr-missing uk-alert uk-alert-warning';
    warning.textContent = `Katalog ${identifier} nicht gefunden.`;
    grid.appendChild(warning);
  });

  return createModuleCard(resolveModuleTitle(moduleConfig, 'Katalog-QR-Codes'), grid, resolveModuleLayout(moduleConfig));
}

function renderRankingQrModule(moduleConfig) {
  const rankingUrl = buildRankingUrl();
  const container = document.createElement('div');
  container.className = 'dashboard-ranking-qr';

  if (!rankingUrl) {
    const warning = document.createElement('p');
    warning.className = 'uk-text-meta';
    warning.textContent = 'Ranking-Link ist aktuell nicht verfügbar.';
    container.appendChild(warning);
    return createModuleCard(resolveModuleTitle(moduleConfig, 'Ranking-QR'), container, resolveModuleLayout(moduleConfig));
  }

  const card = document.createElement('div');
  card.className = 'dashboard-qr-card uk-card uk-card-default uk-card-body';

  const qrUrl = new URL(`${basePath}/qr.png`, window.location.origin);
  qrUrl.searchParams.set('t', rankingUrl);

  const img = document.createElement('img');
  img.src = qrUrl.toString();
  img.alt = 'QR-Code zum persönlichen Ranking';
  img.loading = 'lazy';
  card.appendChild(img);

  const hint = document.createElement('p');
  hint.className = 'uk-margin-small-top';
  hint.textContent = 'QR-Code scannen, um das persönliche Ranking auf dem eigenen Gerät zu öffnen.';
  card.appendChild(hint);

  const link = document.createElement('p');
  link.className = 'dashboard-qr-link uk-text-meta';
  link.textContent = rankingUrl;
  card.appendChild(link);

  container.appendChild(card);

  return createModuleCard(resolveModuleTitle(moduleConfig, 'Ranking-QR'), container, resolveModuleLayout(moduleConfig));
}

function renderResultsModule(rows, moduleConfig, layoutOverride = null, defaultsOverride = {}, renderFlags = {}) {
  const moduleId = typeof moduleConfig?.id === 'string' && moduleConfig.id.trim() !== ''
    ? moduleConfig.id
    : 'results';
  const storedPageIndex = Number.isInteger(moduleState.get(moduleId)) ? moduleState.get(moduleId) : 0;
  clearResultsPagerTimer();
  const layout = typeof layoutOverride === 'string' && layoutOverride.trim() !== ''
    ? layoutOverride
    : resolveModuleLayout(moduleConfig);
  const options = resolveResultsOptions(moduleConfig, defaultsOverride);
  const showAttemptSuffix = renderFlags && renderFlags.showAttemptSuffix === true;
  const sortedRows = Array.isArray(rows) ? [...rows] : [];
  const resolveTimeValue = (row) => {
    const timeValue = parseResultNumber(row?.time);
    if (timeValue !== null) {
      return timeValue;
    }
    const startedValue = parseResultNumber(row?.startedAt ?? row?.started_at);
    return startedValue !== null ? startedValue : 0;
  };
  const resolvePointsValue = (row) => {
    const candidates = [
      row?.points,
      row?.correct,
      row?.final_points,
      row?.finalPoints,
    ];
    for (let i = 0; i < candidates.length; i += 1) {
      const parsed = parseResultNumber(candidates[i]);
      if (parsed !== null) {
        return parsed;
      }
    }
    return 0;
  };
  const compareByName = (a, b) => {
    const nameA = typeof a?.name === 'string' ? a.name : '';
    const nameB = typeof b?.name === 'string' ? b.name : '';
    return nameA.localeCompare(nameB, undefined, { sensitivity: 'base' });
  };

  if (options.sort === 'points') {
    sortedRows.sort((a, b) => {
      const diff = resolvePointsValue(b) - resolvePointsValue(a);
      if (diff !== 0) {
        return diff;
      }
      const timeDiff = resolveTimeValue(b) - resolveTimeValue(a);
      if (timeDiff !== 0) {
        return timeDiff;
      }
      return compareByName(a, b);
    });
  } else if (options.sort === 'name') {
    sortedRows.sort((a, b) => {
      const nameDiff = compareByName(a, b);
      if (nameDiff !== 0) {
        return nameDiff;
      }
      return resolveTimeValue(b) - resolveTimeValue(a);
    });
  } else {
    sortedRows.sort((a, b) => {
      const timeDiff = resolveTimeValue(b) - resolveTimeValue(a);
      if (timeDiff !== 0) {
        return timeDiff;
      }
      return compareByName(a, b);
    });
  }

  const limitedRows = options.limit ? sortedRows.slice(0, options.limit) : sortedRows;
  const table = document.createElement('table');
  table.className = 'uk-table uk-table-divider uk-table-small uk-table-striped';
  const includePlacement = options.showPlacement === true;
  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');
  if (includePlacement) {
    const placementHeader = document.createElement('th');
    placementHeader.textContent = 'Platz';
    headerRow.appendChild(placementHeader);
  }
  const nameHeader = document.createElement('th');
  nameHeader.textContent = 'Name';
  headerRow.appendChild(nameHeader);
  const pointsHeader = document.createElement('th');
  pointsHeader.textContent = 'Punkte';
  headerRow.appendChild(pointsHeader);
  const timeHeader = document.createElement('th');
  timeHeader.textContent = 'Zeit';
  headerRow.appendChild(timeHeader);
  thead.appendChild(headerRow);
  table.appendChild(thead);

  const pageBodies = [];
  const pagerButtons = [];
  let currentPage = 0;

  const goToPage = (index) => {
    if (!Number.isInteger(index) || index < 0 || index >= pageBodies.length) {
      return;
    }
    pageBodies.forEach((tbodyEl, idx) => {
      // eslint-disable-next-line no-param-reassign
      tbodyEl.hidden = idx !== index;
      tbodyEl.style.display = idx === index ? 'table-row-group' : 'none';
      tbodyEl.setAttribute('aria-hidden', idx === index ? 'false' : 'true');
    });
    pagerButtons.forEach((button, idx) => {
      if (idx === index) {
        button.classList.add('uk-button-primary');
        button.classList.remove('uk-button-default');
        button.setAttribute('aria-current', 'true');
      } else {
        button.classList.add('uk-button-default');
        button.classList.remove('uk-button-primary');
        button.removeAttribute('aria-current');
      }
    });
    currentPage = index;
    moduleState.set(moduleId, currentPage);
  };

  const effectivePageSize = options.effectivePageSize && options.effectivePageSize > 0
    ? options.effectivePageSize
    : limitedRows.length || 1;
  const columnCount = includePlacement ? 4 : 3;
  if (limitedRows.length > 0) {
    const pageCount = Math.max(1, Math.ceil(limitedRows.length / effectivePageSize));
    for (let i = 0; i < pageCount; i += 1) {
      const tbody = document.createElement('tbody');
      tbody.dataset.page = String(i);
      tbody.hidden = true;
      tbody.style.display = 'none';
      const start = i * effectivePageSize;
      const pageRows = limitedRows.slice(start, start + effectivePageSize);
      pageRows.forEach((row, rowIndex) => {
        const tr = document.createElement('tr');
        if (includePlacement) {
          const placementCell = document.createElement('td');
          placementCell.textContent = String(start + rowIndex + 1);
          tr.appendChild(placementCell);
        }
        const nameCell = document.createElement('td');
        const attemptCandidate = parseResultNumber(row?.attempt);
        const attemptNumber = attemptCandidate !== null && attemptCandidate > 0
          ? Math.max(1, Math.floor(attemptCandidate))
          : 1;
        const rawName = row?.name ?? '';
        const baseName = typeof rawName === 'string'
          ? rawName
          : rawName === null || rawName === undefined
            ? ''
            : String(rawName);
        const shouldShowAttempt = showAttemptSuffix && baseName !== '' && attemptNumber > 1;
        nameCell.textContent = shouldShowAttempt
          ? `${baseName} (Versuch ${attemptNumber})`
          : baseName;
        const pointsCell = document.createElement('td');
        pointsCell.textContent = formatPointsCell(row?.points ?? row?.correct ?? 0, row?.max_points ?? 0);
        const timeCell = document.createElement('td');
        timeCell.textContent = formatTimestamp(row?.time);
        tr.appendChild(nameCell);
        tr.appendChild(pointsCell);
        tr.appendChild(timeCell);
        tbody.appendChild(tr);
      });
      table.appendChild(tbody);
      pageBodies.push(tbody);
    }
  } else {
    const tbody = document.createElement('tbody');
    tbody.dataset.page = '0';
    tbody.hidden = true;
    tbody.style.display = 'none';
    const emptyRow = document.createElement('tr');
    const emptyCell = document.createElement('td');
    emptyCell.colSpan = columnCount;
    emptyCell.textContent = 'Noch keine Ergebnisse verfügbar';
    emptyRow.appendChild(emptyCell);
    tbody.appendChild(emptyRow);
    table.appendChild(tbody);
    pageBodies.push(tbody);
  }

  if (pageBodies.length > 0) {
    const initialPage = Math.min(Math.max(storedPageIndex, 0), pageBodies.length - 1);
    goToPage(initialPage);
  }

  let pager = null;
  if (pageBodies.length > 1) {
    pager = document.createElement('div');
    pager.className = 'dashboard-results__pager uk-margin-small-top uk-flex uk-flex-center uk-flex-middle uk-flex-wrap';
    const buttonGroup = document.createElement('div');
    buttonGroup.className = 'uk-button-group';
    pager.appendChild(buttonGroup);

    const restartAutoAdvance = () => {
      clearResultsPagerTimer();
      const intervalMs = Math.max(RESULTS_PAGE_INTERVAL_MIN, options.pageInterval || RESULTS_DEFAULT_PAGE_INTERVAL) * 1000;
      resultsPagerTimer = setInterval(() => {
        const nextPage = (currentPage + 1) % pageBodies.length;
        goToPage(nextPage);
      }, intervalMs);
    };

    pageBodies.forEach((_, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'uk-button uk-button-default uk-button-small';
      button.textContent = String(index + 1);
      button.setAttribute('aria-label', `Seite ${index + 1}`);
      button.addEventListener('click', () => {
        goToPage(index);
        restartAutoAdvance();
      });
      buttonGroup.appendChild(button);
      pagerButtons.push(button);
    });

    goToPage(currentPage);
    restartAutoAdvance();
  } else {
    clearResultsPagerTimer();
  }

  const wrapper = document.createElement('div');
  wrapper.appendChild(table);
  if (pager) {
    wrapper.appendChild(pager);
  }

  return createModuleCard(options.title, wrapper, layout);
}

function renderWrongAnswersModule(rows, moduleConfig, layout) {
  const table = document.createElement('table');
  table.className = 'uk-table uk-table-divider uk-table-small';
  table.innerHTML = '<thead><tr><th>Name</th><th>Katalog</th><th>Frage</th></tr></thead>';
  const tbody = document.createElement('tbody');
  if (Array.isArray(rows) && rows.length > 0) {
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      const name = document.createElement('td');
      name.textContent = row.name;
      const catalog = document.createElement('td');
      catalog.textContent = row.catalogName || row.catalog;
      const prompt = document.createElement('td');
      prompt.textContent = insertSoftHyphens(row.prompt || '');
      tr.appendChild(name);
      tr.appendChild(catalog);
      tr.appendChild(prompt);
      tbody.appendChild(tr);
    });
  } else {
    const emptyRow = document.createElement('tr');
    const emptyCell = document.createElement('td');
    emptyCell.colSpan = 3;
    emptyCell.textContent = 'Keine falschen Antworten vorhanden';
    emptyRow.appendChild(emptyCell);
    tbody.appendChild(emptyRow);
  }
  table.appendChild(tbody);
  return createModuleCard(resolveModuleTitle(moduleConfig, 'Falsch beantwortete Fragen'), table, layout);
}

function renderInfoBannerModule(moduleConfig, layout) {
  const content = document.createElement('div');
  content.className = 'dashboard-info';
  content.innerHTML = infoText;
  return createModuleCard(resolveModuleTitle(moduleConfig, 'Hinweise'), content, layout);
}

function renderMediaModule(moduleConfig, layout) {
  const container = document.createElement('div');
  container.className = 'dashboard-media-grid';
  mediaItems.forEach((item) => {
    const url = String(item || '').trim();
    if (!url) return;
    const card = document.createElement('div');
    card.className = 'uk-card uk-card-default uk-card-body';
    if (/\.(png|jpe?g|webp|gif)$/i.test(url)) {
      const img = document.createElement('img');
      img.src = url;
      img.loading = 'lazy';
      img.alt = 'Highlight';
      img.className = 'uk-width-1-1';
      card.appendChild(img);
    } else {
      const iframe = document.createElement('iframe');
      iframe.src = url;
      iframe.loading = 'lazy';
      iframe.className = 'uk-width-1-1';
      iframe.setAttribute('allowfullscreen', '');
      iframe.setAttribute('frameborder', '0');
      card.appendChild(iframe);
    }
    container.appendChild(card);
  });
  return createModuleCard(resolveModuleTitle(moduleConfig, 'Highlights'), container, layout);
}

function renderModules(rows, questionRows, rankings, catalogList) {
  if (!modulesRoot) return;
  clearResultsPagerTimer();
  modulesRoot.innerHTML = '';
  applyHeaderVisibility(activeModules);
  const headerActive = activeModules.some((module) => module && module.id === 'header' && module.enabled);
  let hasModuleOutput = headerActive;
  activeModules.forEach((module) => {
    if (!module || !module.enabled) return;
    const layout = resolveModuleLayout(module);
    if (module.id === 'pointsLeader') {
      modulesRoot.appendChild(renderPointsLeaderModule(rankings, module));
      hasModuleOutput = true;
    } else if (module.id === 'rankings') {
      modulesRoot.appendChild(
        renderResultsModule(rows, module, layout, { title: 'Live-Rankings' }, { showAttemptSuffix: !competitionMode })
      );
      hasModuleOutput = true;
    } else if (module.id === 'results') {
      modulesRoot.appendChild(renderResultsModule(rows, module, layout));
      hasModuleOutput = true;
    } else if (module.id === 'wrongAnswers') {
      const wrongRows = questionRows.filter((row) => !parseBooleanFlag(row?.isCorrect ?? row?.correct));
      modulesRoot.appendChild(renderWrongAnswersModule(wrongRows, module, layout));
      hasModuleOutput = true;
    } else if (module.id === 'infoBanner' && infoText.trim() !== '') {
      modulesRoot.appendChild(renderInfoBannerModule(module, layout));
      hasModuleOutput = true;
    } else if (module.id === 'qrCodes') {
      modulesRoot.appendChild(renderQrModule(module, Array.isArray(catalogList) ? catalogList : []));
      hasModuleOutput = true;
    } else if (module.id === 'rankingQr') {
      modulesRoot.appendChild(renderRankingQrModule(module));
      hasModuleOutput = true;
    } else if (module.id === 'media' && mediaItems.length > 0) {
      modulesRoot.appendChild(renderMediaModule(module, layout));
      hasModuleOutput = true;
    }
  });
  if (!hasModuleOutput) {
    const placeholder = document.createElement('div');
    placeholder.className = 'uk-alert uk-alert-primary';
    placeholder.textContent = 'Keine Module aktiviert';
    modulesRoot.appendChild(placeholder);
  }

  if (window.UIkit?.update) {
    window.UIkit.update(modulesRoot, 'mutation');
  }
}

function handleDataLoad(data) {
  lastUpdatedAt = Date.now();
  updateStatusLabel();
  const rankings = computeRankings(data.rows, data.questionRows, data.catalogCount);
  renderModules(data.rows, data.questionRows, rankings, data.catalogList);
}

function fetchData() {
  if (!config.eventUid) return;
  dataService.load().then(handleDataLoad).catch(() => {
    if (statusLabel) {
      statusLabel.textContent = 'Laden fehlgeschlagen';
    }
  });
}

function startPolling() {
  if (pollingTimer) {
    clearInterval(pollingTimer);
  }
  pollingTimer = setInterval(fetchData, refreshInterval * 1000);
}

if (refreshBtn) {
  refreshBtn.addEventListener('click', (event) => {
    event.preventDefault();
    fetchData();
  });
}

fetchData();
updateStatusLabel();
startPolling();
setInterval(updateStatusLabel, 1000);
