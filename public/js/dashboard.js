import { computeRankings, formatTimestamp, buildScoreboard } from './rankings-core.js';

const MODULE_META = {
  rankings: {
    title: 'Live-Rankings',
    description: 'Top-Platzierungen auf einen Blick',
  },
  results: {
    title: 'Scoreboard',
    description: 'Beste Leistungen je Team',
  },
  questions: {
    title: 'Knackpunkte',
    description: 'Fragen mit den meisten Fehlern',
  },
  info: {
    title: 'Event-Infos',
    description: '',
  },
  media: {
    title: 'Livestream & Highlights',
    description: '',
  },
};

const state = {
  results: [],
  wrongAnswers: [],
  updatedAt: 0,
  catalogCount: 0,
};

function parseConfig() {
  const script = document.getElementById('dashboard-config');
  if (!script) return null;
  try {
    return JSON.parse(script.textContent || '{}');
  } catch (err) {
    console.error('Invalid dashboard config payload', err);
    return null;
  }
}

function withBase(path) {
  const basePath = window.basePath || '';
  return `${basePath}${path}`;
}

function createModuleSection(container, module) {
  const meta = MODULE_META[module.id] || { title: module.id };
  const section = document.createElement('section');
  section.className = `dashboard-module dashboard-module-${module.id}`;

  const header = document.createElement('div');
  header.className = 'dashboard-module-header';

  const title = document.createElement('h2');
  title.className = 'dashboard-module-title';
  title.textContent = meta.title || module.id;
  header.appendChild(title);

  if (meta.description) {
    const desc = document.createElement('p');
    desc.className = 'dashboard-module-description';
    desc.textContent = meta.description;
    header.appendChild(desc);
  }

  const content = document.createElement('div');
  content.className = 'dashboard-module-content';

  section.appendChild(header);
  section.appendChild(content);
  container.appendChild(section);

  return { section, content };
}

function renderRankings(container, options) {
  const list = computeRankings(state.results, state.catalogCount);
  container.innerHTML = '';
  const wrapper = document.createElement('div');
  wrapper.className = 'dashboard-rankings-list';

  const cards = [
    { key: 'puzzleList', title: 'Rätselwort-Bestzeit' },
    { key: 'catalogList', title: 'Katalogmeister' },
    { key: 'pointsList', title: 'Highscore-Champions' },
  ];
  const limit = Math.max(1, Number(options.rankingLimit || 3));

  cards.forEach((card) => {
    const data = list[card.key] || [];
    const cardEl = document.createElement('div');
    cardEl.className = 'dashboard-ranking-card';

    const heading = document.createElement('h3');
    heading.textContent = card.title;
    cardEl.appendChild(heading);

    const ol = document.createElement('ol');
    for (let i = 0; i < limit; i += 1) {
      const item = data[i];
      const li = document.createElement('li');
      if (item) {
        li.textContent = `${item.name} – ${item.value || ''}`;
      } else {
        li.textContent = '—';
        li.className = 'uk-text-muted';
      }
      ol.appendChild(li);
    }
    cardEl.appendChild(ol);
    wrapper.appendChild(cardEl);
  });

  container.appendChild(wrapper);
}

function renderScoreboard(container, options) {
  const scoreboard = buildScoreboard(state.results);
  container.innerHTML = '';
  if (!scoreboard.length) {
    const empty = document.createElement('p');
    empty.textContent = 'Noch keine Ergebnisse verfügbar.';
    container.appendChild(empty);
    return;
  }
  const table = document.createElement('table');
  table.className = 'dashboard-table';
  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');
  ['Platz', 'Team', 'Punkte', 'Kataloge', 'Versuche', 'Letzte Aktivität'].forEach((label) => {
    const th = document.createElement('th');
    th.textContent = label;
    headerRow.appendChild(th);
  });
  thead.appendChild(headerRow);
  table.appendChild(thead);

  const tbody = document.createElement('tbody');
  const limit = Math.max(1, Math.min(Number(options.rankingLimit || 10), scoreboard.length));
  for (let i = 0; i < limit; i += 1) {
    const entry = scoreboard[i];
    const tr = document.createElement('tr');
    const columns = [
      i + 1,
      entry.name,
      entry.points,
      `${entry.catalogsSolved}/${entry.catalogsPlayed}`,
      entry.attempts,
      entry.lastUpdate ? formatTimestamp(entry.lastUpdate) : '—',
    ];
    columns.forEach((value) => {
      const td = document.createElement('td');
      td.textContent = String(value);
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  }
  table.appendChild(tbody);
  container.appendChild(table);
}

function renderQuestions(container) {
  container.innerHTML = '';
  if (!state.wrongAnswers.length) {
    const empty = document.createElement('p');
    empty.textContent = 'Keine falschen Antworten registriert.';
    container.appendChild(empty);
    return;
  }
  const table = document.createElement('table');
  table.className = 'dashboard-table';
  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');
  ['Team', 'Katalog', 'Frage'].forEach((label) => {
    const th = document.createElement('th');
    th.textContent = label;
    headerRow.appendChild(th);
  });
  thead.appendChild(headerRow);
  table.appendChild(thead);

  const tbody = document.createElement('tbody');
  state.wrongAnswers.slice(0, 20).forEach((row) => {
    const tr = document.createElement('tr');
    const cells = [row.name || '—', row.catalog || '—', row.prompt || '—'];
    cells.forEach((value) => {
      const td = document.createElement('td');
      td.textContent = value;
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);
  container.appendChild(table);
}

function renderInfo(container, infoHtml) {
  container.innerHTML = '';
  if (!infoHtml) {
    const empty = document.createElement('p');
    empty.textContent = 'Noch keine Informationen hinterlegt.';
    container.appendChild(empty);
    return;
  }
  const wrapper = document.createElement('div');
  wrapper.className = 'dashboard-info';
  wrapper.innerHTML = infoHtml;
  container.appendChild(wrapper);
}

function renderMedia(container, url) {
  container.innerHTML = '';
  if (!url) {
    const empty = document.createElement('p');
    empty.textContent = 'Aktuell ist kein Stream hinterlegt.';
    container.appendChild(empty);
    return;
  }
  const wrapper = document.createElement('div');
  wrapper.className = 'dashboard-media';
  const iframe = document.createElement('iframe');
  iframe.src = url;
  iframe.allowFullscreen = true;
  iframe.loading = 'lazy';
  wrapper.appendChild(iframe);
  container.appendChild(wrapper);
}

function updateStatus(statusEl, timestamp) {
  if (!statusEl) return;
  if (!timestamp) {
    statusEl.textContent = 'Noch keine Ergebnisse verfügbar.';
    return;
  }
  statusEl.textContent = `Stand: ${formatTimestamp(timestamp)}`;
}

document.addEventListener('DOMContentLoaded', () => {
  const config = parseConfig();
  if (!config) {
    return;
  }
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get('token') || '';
  const slug = config.slug || '';
  if (!token || !slug) {
    const statusEl = document.getElementById('dashboardStatus');
    if (statusEl) {
      statusEl.textContent = 'Ungültiger oder abgelaufener Link.';
    }
    return;
  }

  const modulesContainer = document.getElementById('dashboardModules');
  const statusEl = document.getElementById('dashboardStatus');
  const modules = Array.isArray(config.modules) ? config.modules : [];
  const enabledModules = modules.filter((module) => module && module.enabled !== false);
  const moduleElements = new Map();

  enabledModules.forEach((module) => {
    const el = createModuleSection(modulesContainer, module);
    moduleElements.set(module.id, el);
  });

  if (!enabledModules.length && modulesContainer) {
    const note = document.createElement('p');
    note.textContent = 'Keine Module aktiv. Bitte das Dashboard in der Eventkonfiguration anpassen.';
    modulesContainer.appendChild(note);
  }

  const refreshInterval = Math.max(5, Number(config.refresh || 15));
  const rankingLimit = Number(config.rankingLimit || 5);
  const infoHtml = config.info || '';
  const mediaUrl = config.mediaUrl || '';

  const renderers = {
    rankings: () => {
      const el = moduleElements.get('rankings');
      if (el) {
        renderRankings(el.content, { rankingLimit });
      }
    },
    results: () => {
      const el = moduleElements.get('results');
      if (el) {
        renderScoreboard(el.content, { rankingLimit });
      }
    },
    questions: () => {
      const el = moduleElements.get('questions');
      if (el) {
        renderQuestions(el.content);
      }
    },
    info: () => {
      const el = moduleElements.get('info');
      if (el) {
        renderInfo(el.content, infoHtml);
      }
    },
    media: () => {
      const el = moduleElements.get('media');
      if (el) {
        renderMedia(el.content, mediaUrl);
      }
    },
  };

  let activeRequestId = 0;
  let refreshTimer = null;

  function renderAll() {
    enabledModules.forEach((module) => {
      const renderer = renderers[module.id];
      if (typeof renderer === 'function') {
        renderer();
      }
    });
  }

  function fetchData() {
    const requestId = ++activeRequestId;
    if (statusEl) {
      statusEl.dataset.loading = 'true';
    }
    return fetch(withBase(`/events/${encodeURIComponent(slug)}/dashboard/data?token=${encodeURIComponent(token)}`), {
      cache: 'no-store',
      credentials: 'same-origin',
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`Request failed with status ${response.status}`);
        }
        return response.json();
      })
      .then((payload) => {
        if (requestId !== activeRequestId) {
          return;
        }
        state.results = Array.isArray(payload.results) ? payload.results : [];
        state.wrongAnswers = Array.isArray(payload.wrongAnswers) ? payload.wrongAnswers : [];
        state.catalogCount = Number(payload.catalogCount || 0);
        state.updatedAt = Number(payload.updatedAt || 0);
        renderAll();
        updateStatus(statusEl, state.updatedAt);
      })
      .catch((err) => {
        console.error('Dashboard update failed', err);
        if (requestId === activeRequestId && statusEl) {
          statusEl.textContent = 'Aktualisierung nicht möglich. Bitte Verbindung prüfen.';
        }
      })
      .finally(() => {
        if (requestId === activeRequestId && statusEl) {
          delete statusEl.dataset.loading;
        }
      });
  }

  function scheduleRefresh() {
    clearInterval(refreshTimer);
    refreshTimer = setInterval(fetchData, refreshInterval * 1000);
  }

  fetchData().then(() => {
    if (refreshInterval > 0) {
      scheduleRefresh();
    }
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      fetchData();
    }
  });
});
