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

function createModuleCard(title, content) {
  const wrapper = document.createElement('div');
  wrapper.className = 'uk-card uk-card-default uk-card-body uk-margin-large-bottom';
  const heading = document.createElement('h3');
  heading.className = 'uk-heading-bullet';
  heading.textContent = title;
  wrapper.appendChild(heading);
  wrapper.appendChild(content);
  return wrapper;
}

function renderRankingsModule(rankings, moduleConfig) {
  const safeRankings = {
    puzzleList: (rankings && rankings.puzzleList) || [],
    catalogList: (rankings && rankings.catalogList) || [],
    pointsList: (rankings && rankings.pointsList) || [],
    accuracyList: (rankings && rankings.accuracyList) || [],
  };
  const metrics = Array.isArray(moduleConfig.options?.metrics) && moduleConfig.options.metrics.length
    ? moduleConfig.options.metrics
    : ['puzzle', 'catalog', 'points', 'accuracy'];
  const cardDefinitions = {
    puzzle: {
      title: 'Rätselwort-Bestzeit',
      list: safeRankings.puzzleList,
      tooltip: 'Top 3 Teams mit der schnellsten Rätselwort-Lösung',
    },
    catalog: {
      title: 'Katalogmeister',
      list: safeRankings.catalogList,
      tooltip: 'Top 3 Teams, die alle Kataloge am schnellsten abgeschlossen haben',
    },
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
  const grid = document.createElement('div');
  grid.className = 'uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@m';
  grid.setAttribute('uk-grid', '');
  metrics.forEach((metric) => {
    const def = cardDefinitions[metric];
    if (!def) return;
    const col = document.createElement('div');
    const card = document.createElement('div');
    card.className = 'uk-card uk-card-default uk-card-body';
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
        const durationValue = item && Number.isFinite(item.duration) ? formatDuration(item.duration) : null;
        const valueText = durationValue || item.value;
        label.innerHTML = `<span>${i + 1}. ${item.name}</span><span>${valueText}</span>`;
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
  return createModuleCard('Live-Rankings', grid);
}

function renderResultsTable(rows) {
  const table = document.createElement('table');
  table.className = 'uk-table uk-table-divider uk-table-small uk-table-striped';
  const thead = document.createElement('thead');
  thead.innerHTML = '<tr><th>Name</th><th>Versuch</th><th>Katalog</th><th>Punkte</th><th>Zeit</th><th>Rätselwort</th></tr>';
  table.appendChild(thead);
  const tbody = document.createElement('tbody');
  if (Array.isArray(rows) && rows.length > 0) {
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      const nameCell = document.createElement('td');
      nameCell.textContent = row.name;
      const attemptCell = document.createElement('td');
      attemptCell.textContent = row.attempt;
      const catalogCell = document.createElement('td');
      catalogCell.textContent = row.catalogName || row.catalog;
      const pointsCell = document.createElement('td');
      pointsCell.textContent = formatPointsCell(row.points ?? row.correct ?? 0, row.max_points ?? 0);
      const timeCell = document.createElement('td');
      timeCell.textContent = formatTimestamp(row.time);
      const puzzleCell = document.createElement('td');
      puzzleCell.textContent = row.puzzleTime ? formatTimestamp(row.puzzleTime) : '';
      [nameCell, attemptCell, catalogCell, pointsCell, timeCell, puzzleCell].forEach((cell) => {
        tr.appendChild(cell);
      });
      tbody.appendChild(tr);
    });
  } else {
    const emptyRow = document.createElement('tr');
    const emptyCell = document.createElement('td');
    emptyCell.colSpan = 6;
    emptyCell.textContent = 'Noch keine Ergebnisse verfügbar';
    emptyRow.appendChild(emptyCell);
    tbody.appendChild(emptyRow);
  }
  table.appendChild(tbody);
  return createModuleCard('Ergebnisliste', table);
}

function renderWrongAnswersModule(rows) {
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
  return createModuleCard('Falsch beantwortete Fragen', table);
}

function renderInfoBannerModule() {
  const content = document.createElement('div');
  content.className = 'dashboard-info';
  content.innerHTML = infoText;
  return createModuleCard('Hinweise', content);
}

function renderMediaModule() {
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
  return createModuleCard('Highlights', container);
}

function renderModules(rows, questionRows, rankings, catalogCount) {
  if (!modulesRoot) return;
  modulesRoot.innerHTML = '';
  applyHeaderVisibility(activeModules);
  const headerActive = activeModules.some((module) => module && module.id === 'header' && module.enabled);
  let hasModuleOutput = headerActive;
  activeModules.forEach((module) => {
    if (!module || !module.enabled) return;
    if (module.id === 'rankings') {
      modulesRoot.appendChild(renderRankingsModule(rankings, module));
      hasModuleOutput = true;
    } else if (module.id === 'results') {
      modulesRoot.appendChild(renderResultsTable(rows));
      hasModuleOutput = true;
    } else if (module.id === 'wrongAnswers') {
      const wrongRows = questionRows.filter((row) => !row.correct);
      modulesRoot.appendChild(renderWrongAnswersModule(wrongRows));
      hasModuleOutput = true;
    } else if (module.id === 'infoBanner' && infoText.trim() !== '') {
      modulesRoot.appendChild(renderInfoBannerModule());
      hasModuleOutput = true;
    } else if (module.id === 'media' && mediaItems.length > 0) {
      modulesRoot.appendChild(renderMediaModule());
      hasModuleOutput = true;
    }
  });
  if (!hasModuleOutput) {
    const placeholder = document.createElement('div');
    placeholder.className = 'uk-alert uk-alert-primary';
    placeholder.textContent = 'Keine Module aktiviert';
    modulesRoot.appendChild(placeholder);
  }
}

function handleDataLoad(data) {
  lastUpdatedAt = Date.now();
  updateStatusLabel();
  const rankings = computeRankings(data.rows, data.questionRows, data.catalogCount);
  renderModules(data.rows, data.questionRows, rankings, data.catalogCount);
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
