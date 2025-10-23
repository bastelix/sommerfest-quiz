import { ResultsDataService, computeRankings } from './results-data-service.js';
import { formatTimestamp, formatPointsCell, insertSoftHyphens, formatDuration } from './results-utils.js';

const config = window.dashboardConfig || {};
const basePath = window.basePath || '';
const modulesRoot = document.querySelector('[data-dashboard-root]');
const statusLabel = document.getElementById('dashboardStatusLabel');
const refreshBtn = document.getElementById('dashboardRefreshBtn');
const headerContainer = document.querySelector('[data-dashboard-header]');

const activeModules = Array.isArray(config.modules) ? config.modules : [];
const infoText = config.infoText || '';
const mediaItems = Array.isArray(config.mediaItems) ? config.mediaItems : [];
const refreshInterval = Math.max(5, Number(config.refreshInterval) || 15);
const eventIdentifier = config.slug || config.eventUid || '';
const DASHBOARD_LAYOUT_OPTIONS = new Set(['auto', 'wide', 'full']);
const MODULE_DEFAULT_LAYOUTS = {
  header: 'full',
  pointsLeader: 'wide',
  rankings: 'wide',
  results: 'full',
  wrongAnswers: 'auto',
  infoBanner: 'auto',
  qrCodes: 'auto',
  media: 'auto',
};

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

const puzzleWordEnabled = parseBooleanFlag(config.puzzleWordEnabled ?? config.puzzle_word_enabled);

const dataService = new ResultsDataService({
  basePath,
  eventUid: config.eventUid || '',
  shareToken: config.shareToken || '',
  variant: config.variant || 'public',
});

let lastUpdatedAt = null;
let pollingTimer = null;

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

function renderPointsLeaderModule(rankings, moduleConfig) {
  const container = document.createElement('div');
  container.className = 'dashboard-leader';
  const list = Array.isArray(rankings?.pointsList) ? rankings.pointsList : [];
  if (list.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'uk-text-meta';
    empty.textContent = 'Noch keine Punkte erfasst.';
    container.appendChild(empty);
    return createModuleCard('Platzierungen', container, resolveModuleLayout(moduleConfig));
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
  list.slice(0, 5).forEach((entry, index) => {
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

  return createModuleCard('Platzierungen', container, resolveModuleLayout(moduleConfig));
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

  return createModuleCard('Katalog-QR-Codes', grid, resolveModuleLayout(moduleConfig));
}

function renderRankingsModule(rankings, moduleConfig, catalogCount = 0) {
  const hasMultipleCatalogs = Number.isFinite(catalogCount) ? catalogCount > 1 : false;
  const puzzleActive = puzzleWordEnabled;
  const safeRankings = {
    puzzleList: puzzleActive && rankings ? rankings.puzzleList || [] : [],
    catalogList: (rankings && rankings.catalogList) || [],
    pointsList: (rankings && rankings.pointsList) || [],
    accuracyList: (rankings && rankings.accuracyList) || [],
  };
  const metrics = Array.isArray(moduleConfig.options?.metrics) && moduleConfig.options.metrics.length
    ? moduleConfig.options.metrics
    : ['puzzle', 'catalog', 'points', 'accuracy'];
  const normalizedMetrics = metrics.filter((metric) => {
    if (metric === 'catalog') {
      return hasMultipleCatalogs;
    }
    if (metric === 'puzzle') {
      return puzzleActive;
    }
    return true;
  });
  const cardDefinitions = {
    points: {
      title: 'Highscore-Champions',
      list: safeRankings.pointsList,
      tooltip: 'Top 3 Teams mit den meisten Punkten',
    },
    accuracy: {
      title: 'Trefferquote-Champions',
      list: safeRankings.accuracyList,
      tooltip: 'Top 3 Teams mit der höchsten durchschnittlichen Effizienz',
    },
  };
  if (puzzleActive) {
    cardDefinitions.puzzle = {
      title: 'Rätselwort-Bestzeit',
      list: safeRankings.puzzleList,
      tooltip: 'Top 3 Teams mit der schnellsten Rätselwort-Lösung',
    };
  }
  if (hasMultipleCatalogs) {
    cardDefinitions.catalog = {
      title: 'Ranking-Champions',
      list: safeRankings.catalogList,
      tooltip: 'Top 3 Teams nach gelösten Fragen (Tie-Breaker: Punkte, Gesamtzeit)',
    };
  }
  const grid = document.createElement('div');
  grid.className = 'dashboard-rankings-grid';
  normalizedMetrics.forEach((metric) => {
    const def = cardDefinitions[metric];
    if (!def) return;
    const col = document.createElement('div');
    col.className = 'dashboard-rankings-grid__column';
    const card = document.createElement('div');
    card.className = 'dashboard-rankings-card uk-card uk-card-default uk-card-body';
    const title = document.createElement('h4');
    title.className = 'uk-card-title';
    title.textContent = def.title;
    card.appendChild(title);
    const list = document.createElement('ol');
    list.className = 'uk-list uk-list-striped';
    for (let i = 0; i < 3; i += 1) {
      const li = document.createElement('li');
      const item = def.list[i];
      if (item) {
        const label = document.createElement('div');
        label.className = 'uk-flex uk-flex-between';
        const extras = [];
        if (Number.isFinite(item.solved)) {
          extras.push(`${item.solved} gelöst`);
        }
        if (Number.isFinite(item.points)) {
          extras.push(`${item.points} Punkte`);
        }
        const leftText = extras.length
          ? `${i + 1}. ${item.name} – ${extras.join(' • ')}`
          : `${i + 1}. ${item.name}`;
        const durationValue = item && Number.isFinite(item.duration) ? formatDuration(item.duration) : null;
        const finishedValue = item && Number.isFinite(item.finished) ? formatTimestamp(item.finished) : null;
        const valueText = durationValue || finishedValue || item.value || '–';
        label.innerHTML = `<span>${leftText}</span><span>${valueText}</span>`;
        li.appendChild(label);
      } else {
        li.textContent = '-';
      }
      list.appendChild(li);
    }
    card.appendChild(list);
    col.appendChild(card);
    grid.appendChild(col);
  });
  if (!grid.hasChildNodes()) {
    const emptyState = document.createElement('p');
    emptyState.className = 'uk-text-meta';
    if (
      normalizedMetrics.length === 0
      && metrics.includes('puzzle')
      && !puzzleActive
      && metrics.every((metric) => metric === 'puzzle' || (metric === 'catalog' && !hasMultipleCatalogs))
    ) {
      emptyState.textContent = 'Rätselwort-Rankings sind deaktiviert.';
    } else {
      emptyState.textContent = 'Keine Rankings ausgewählt.';
    }
    grid.appendChild(emptyState);
  }
  return createModuleCard('Live-Rankings', grid, resolveModuleLayout(moduleConfig));
}

function renderResultsTable(rows, layout) {
  const table = document.createElement('table');
  table.className = 'uk-table uk-table-divider uk-table-small uk-table-striped';
  const thead = document.createElement('thead');
  thead.innerHTML = '<tr><th>Name</th><th>Punkte</th><th>Zeit</th></tr>';
  table.appendChild(thead);
  const tbody = document.createElement('tbody');
  if (Array.isArray(rows) && rows.length > 0) {
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      const nameCell = document.createElement('td');
      nameCell.textContent = row.name;
      const pointsCell = document.createElement('td');
      pointsCell.textContent = formatPointsCell(row.points ?? row.correct ?? 0, row.max_points ?? 0);
      const timeCell = document.createElement('td');
      timeCell.textContent = formatTimestamp(row.time);
      [nameCell, pointsCell, timeCell].forEach((cell) => {
        tr.appendChild(cell);
      });
      tbody.appendChild(tr);
    });
  } else {
    const emptyRow = document.createElement('tr');
    const emptyCell = document.createElement('td');
    emptyCell.colSpan = 3;
    emptyCell.textContent = 'Noch keine Ergebnisse verfügbar';
    emptyRow.appendChild(emptyCell);
    tbody.appendChild(emptyRow);
  }
  table.appendChild(tbody);
  return createModuleCard('Ergebnisliste', table, layout);
}

function renderWrongAnswersModule(rows, layout) {
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
  return createModuleCard('Falsch beantwortete Fragen', table, layout);
}

function renderInfoBannerModule(layout) {
  const content = document.createElement('div');
  content.className = 'dashboard-info';
  content.innerHTML = infoText;
  return createModuleCard('Hinweise', content, layout);
}

function renderMediaModule(layout) {
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
  return createModuleCard('Highlights', container, layout);
}

function renderModules(rows, questionRows, rankings, catalogCount, catalogList) {
  if (!modulesRoot) return;
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
      modulesRoot.appendChild(renderRankingsModule(rankings, module, catalogCount));
      hasModuleOutput = true;
    } else if (module.id === 'results') {
      modulesRoot.appendChild(renderResultsTable(rows, layout));
      hasModuleOutput = true;
    } else if (module.id === 'wrongAnswers') {
      const wrongRows = questionRows.filter((row) => !row.correct);
      modulesRoot.appendChild(renderWrongAnswersModule(wrongRows, layout));
      hasModuleOutput = true;
    } else if (module.id === 'infoBanner' && infoText.trim() !== '') {
      modulesRoot.appendChild(renderInfoBannerModule(layout));
      hasModuleOutput = true;
    } else if (module.id === 'qrCodes') {
      modulesRoot.appendChild(renderQrModule(module, Array.isArray(catalogList) ? catalogList : []));
      hasModuleOutput = true;
    } else if (module.id === 'media' && mediaItems.length > 0) {
      modulesRoot.appendChild(renderMediaModule(layout));
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
  renderModules(data.rows, data.questionRows, rankings, data.catalogCount, data.catalogList);
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
