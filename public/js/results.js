/* global UIkit */
import { applyLazyImage } from './lazy-images.js';
let catalogCount = 0;
document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('resultsTableBody');
  const wrongBody = document.getElementById('wrongTableBody');
  const refreshBtn = document.getElementById('resultsRefreshBtn');
  const grid = document.getElementById('rankingGrid');
  const pagination = document.getElementById('resultsPagination');
  const basePath = window.basePath || '';
  const withBase = path => basePath + path;
  const params = new URLSearchParams(window.location.search);
  let fallbackEventUid = params.get('event') || '';
  let activeRequestId = 0;

  const PAGE_SIZE = 10;
  let resultsData = [];
  let currentPage = 1;

  function formatTime(ts) {
    const d = new Date(ts * 1000);
    const pad = n => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function formatPointsCell(points, maxPoints) {
    const pts = Number.isFinite(points) ? points : Number.parseInt(points, 10);
    const normalizedPts = Number.isFinite(pts) ? pts : 0;
    const max = Number.isFinite(maxPoints) ? maxPoints : Number.parseInt(maxPoints, 10);
    if (Number.isFinite(max) && max > 0) {
      return `${normalizedPts}/${max}`;
    }
    return String(normalizedPts);
  }

  function insertSoftHyphens(text) {
    return text ? text.replace(/\/-/g, '\u00AD') : '';
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function sanitizePageNumber(num, total) {
    let n = parseInt(num, 10);
    if (Number.isNaN(n) || n < 1) n = 1;
    if (total && n > total) n = total;
    return n;
  }

  function rotatePhotoImpl(path, img, link) {
    const cleanPath = path.replace(/\?.*$/, '');
    return fetch(withBase('/photos/rotate'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ path: cleanPath })
    })
      .then(r => { if (!r.ok) throw new Error('rotate'); })
      .then(() => {
        const t = Date.now();
        const newPath = `${cleanPath}?t=${t}`;
        applyLazyImage(img, withBase(newPath), { forceLoad: true });
        if (link) {
          link.href = withBase(newPath);
          if (link.dataset && link.dataset.caption) {
            const safePath = escapeHtml(newPath);
            link.dataset.caption = link.dataset.caption
              .replace(/data-path='[^']*'/, `data-path='${safePath}'`);
          }
        }
        refreshLightboxes();
        return newPath;
      })
      .catch(() => {});
  }

  function refreshLightboxes() {
    if (typeof UIkit === 'undefined') return;
    document.querySelectorAll('[uk-lightbox]').forEach(el => {
      const lightbox = UIkit.getComponent(el, 'lightbox');
      if (lightbox) {
        lightbox.items = UIkit.lightbox(el).items;
      }
    });
  }

  const rotatePhoto = window.rotatePhoto || rotatePhotoImpl;
  if (!window.rotatePhoto) {
    window.rotatePhoto = rotatePhoto;
  }

  function renderTable(rows) {
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 8;
      td.textContent = 'Keine Daten';
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }
    rows.forEach(r => {
      const tr = document.createElement('tr');
      const pointsCell = formatPointsCell(r.points ?? r.correct ?? 0, r.max_points ?? 0);
      const cells = [
        r.attempt,
        r.catalogName || r.catalog,
        `${r.correct}/${r.total}`,
        pointsCell,
        formatTime(r.time),
        r.puzzleTime ? formatTime(r.puzzleTime) : '',
        null
      ];
      const nameCell = document.createElement('td');
      nameCell.textContent = r.name;
      tr.appendChild(nameCell);
      cells.forEach((c, idx) => {
        const td = document.createElement('td');
        if (idx === cells.length - 1) {
          if (r.photo) {
            const wrap = document.createElement('span');
            wrap.className = 'photo-wrapper';

            const a = document.createElement('a');
            const safePhoto = escapeHtml(r.photo);
            a.className = 'uk-inline rotate-link';
            a.href = withBase(r.photo);
            a.dataset.caption = `<button class='uk-icon-button lightbox-rotate-btn' type='button' uk-icon='history' data-path='${safePhoto}' aria-label='Drehen'></button>`;
            a.dataset.attrs = 'class: uk-inverse-light';

            const img = document.createElement('img');
            img.alt = 'Beweisfoto';
            img.className = 'proof-thumb';
            applyLazyImage(img, withBase(r.photo));

            const btn = document.createElement('button');
            btn.className = 'uk-icon-button photo-rotate-btn';
            btn.type = 'button';
            btn.setAttribute('uk-icon', 'history');
            btn.addEventListener('click', (e) => {
              e.preventDefault();
              rotatePhoto(r.photo, img, a);
            });

            a.appendChild(img);
            wrap.appendChild(a);
            td.appendChild(wrap);
          }
        } else {
          td.textContent = c;
        }
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
  }

  function renderWrongTable(rows) {
    if (!wrongBody) return;
    wrongBody.innerHTML = '';
    if (!rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 3;
      td.textContent = 'Keine Daten';
      tr.appendChild(td);
      wrongBody.appendChild(tr);
      return;
    }
    rows.forEach(r => {
      const tr = document.createElement('tr');
      const tdName = document.createElement('td');
      tdName.textContent = r.name;
      const tdCat = document.createElement('td');
      tdCat.textContent = r.catalogName || r.catalog;
      const tdQ = document.createElement('td');
      tdQ.textContent = insertSoftHyphens(r.prompt || '');
      tr.appendChild(tdName);
      tr.appendChild(tdCat);
      tr.appendChild(tdQ);
      wrongBody.appendChild(tr);
    });
  }

  function renderPage(page) {
    const total = Math.ceil(resultsData.length / PAGE_SIZE) || 1;
    page = sanitizePageNumber(page, total);
    const start = (page - 1) * PAGE_SIZE;
    const slice = resultsData.slice(start, start + PAGE_SIZE);
    renderTable(slice);
    currentPage = page;
  }

  function updatePagination() {
    if (!pagination) return;
    const total = Math.ceil(resultsData.length / PAGE_SIZE);
    if (total <= 1) { pagination.textContent = ''; return; }
    pagination.textContent = '';
    currentPage = sanitizePageNumber(currentPage, total);
    const prevLi = document.createElement('li');
    if (currentPage === 1) prevLi.classList.add('uk-disabled');
    const prevA = document.createElement('a');
    prevA.href = '#';
    prevA.dataset.page = String(currentPage > 1 ? currentPage - 1 : 1);
    prevA.textContent = '«';
    prevLi.appendChild(prevA);
    pagination.appendChild(prevLi);

    for (let i = 1; i <= total; i++) {
      const li = document.createElement('li');
      if (currentPage === i) li.classList.add('uk-active');
      const a = document.createElement('a');
      a.href = '#';
      a.dataset.page = String(i);
      a.textContent = String(i);
      li.appendChild(a);
      pagination.appendChild(li);
    }

    const nextLi = document.createElement('li');
    if (currentPage === total) nextLi.classList.add('uk-disabled');
    const nextA = document.createElement('a');
    nextA.href = '#';
    nextA.dataset.page = String(currentPage < total ? currentPage + 1 : total);
    nextA.textContent = '»';
    nextLi.appendChild(nextA);
    pagination.appendChild(nextLi);
  }

  function computeRankings(rows, qrows) {
    const catalogs = new Set();
    const puzzleTimes = new Map();
    const catTimes = new Map();
    const scorePoints = new Map();
    const attemptMetrics = new Map();

    qrows.forEach(r => {
      const team = r.name || '';
      const catalog = r.catalog || '';
      if (!team || !catalog) return;
      const attempt = Number.isFinite(r.attempt) ? Number(r.attempt) : parseInt(r.attempt, 10) || 1;
      const key = `${team}|${catalog}|${attempt}`;
      const finalPoints = Number.isFinite(r.final_points)
        ? Number(r.final_points)
        : Number.isFinite(r.finalPoints)
          ? Number(r.finalPoints)
          : Number.isFinite(r.points) ? Number(r.points) : 0;
      const efficiency = Number.isFinite(r.efficiency)
        ? Number(r.efficiency)
        : (r.correct ? 1 : 0);
      const summary = attemptMetrics.get(key) || { points: 0, effSum: 0, count: 0 };
      summary.points += Math.max(0, finalPoints || 0);
      summary.effSum += Math.max(0, efficiency || 0);
      summary.count += 1;
      attemptMetrics.set(key, summary);
    });

    rows.forEach(r => {
      const team = r.name || '';
      const catalog = r.catalog || '';
      if (!team || !catalog) return;
      catalogs.add(catalog);

      if (r.puzzleTime) {
        const prev = puzzleTimes.get(team);
        const timeVal = Number(r.puzzleTime);
        if (!prev || timeVal < prev) puzzleTimes.set(team, timeVal);
      }

      let tMap = catTimes.get(team);
      if (!tMap) { tMap = new Map(); catTimes.set(team, tMap); }
      const prevTime = tMap.get(catalog);
      const playedTime = Number(r.time);
      if (prevTime === undefined || playedTime < prevTime) {
        tMap.set(catalog, playedTime);
      }

      const attempt = Number.isFinite(r.attempt) ? Number(r.attempt) : parseInt(r.attempt, 10) || 1;
      const key = `${team}|${catalog}|${attempt}`;
      const summary = attemptMetrics.get(key);
      let finalPoints;
      let effSum;
      let questionCount;
      if (summary && summary.count > 0) {
        finalPoints = summary.points;
        effSum = summary.effSum;
        questionCount = summary.count;
      } else {
        const fallbackPoints = Number.isFinite(r.points) ? Number(r.points) : Number(r.correct) || 0;
        finalPoints = fallbackPoints;
        const totalQuestions = Number.isFinite(r.total) ? Number(r.total) : parseInt(r.total, 10) || 0;
        questionCount = totalQuestions > 0 ? totalQuestions : 0;
        const correctCount = Number.isFinite(r.correct) ? Number(r.correct) : parseInt(r.correct, 10) || 0;
        const avgFallback = questionCount > 0 ? correctCount / questionCount : 0;
        effSum = avgFallback * questionCount;
      }
      const average = questionCount > 0 ? effSum / questionCount : 0;

      let sMap = scorePoints.get(team);
      if (!sMap) { sMap = new Map(); scorePoints.set(team, sMap); }
      const prev = sMap.get(catalog);
      if (!prev || finalPoints > prev.points || (finalPoints === prev.points && average > prev.avg)) {
        sMap.set(catalog, {
          points: finalPoints,
          effSum,
          count: questionCount,
          avg: average
        });
      }
    });

    const puzzleArr = [];
    puzzleTimes.forEach((time, name) => {
      puzzleArr.push({ name, value: formatTime(time), raw: time });
    });
    puzzleArr.sort((a, b) => a.raw - b.raw);
    const puzzleList = puzzleArr.slice(0, 3);

    const totalCats = catalogCount || catalogs.size;
    const finishers = [];
    catTimes.forEach((map, name) => {
      if (map.size === totalCats) {
        let last = -Infinity;
        map.forEach(t => { if (t > last) last = t; });
        finishers.push({ name, finished: last });
      }
    });
    finishers.sort((a, b) => a.finished - b.finished);
    const catalogList = finishers.slice(0, 3).map(item => ({
      name: item.name,
      value: formatTime(item.finished),
      raw: item.finished
    }));

    const totalScores = [];
    scorePoints.forEach((map, name) => {
      let total = 0;
      let effSumTotal = 0;
      let questionCountTotal = 0;
      map.forEach(entry => {
        total += entry.points;
        effSumTotal += entry.effSum;
        questionCountTotal += entry.count;
      });
      const avgEfficiency = questionCountTotal > 0 ? effSumTotal / questionCountTotal : 0;
      const display = `${total} Punkte (Ø ${(avgEfficiency * 100).toFixed(0)}%)`;
      totalScores.push({ name, value: display, raw: total, avg: avgEfficiency });
    });
    totalScores.sort((a, b) => {
      if (b.raw !== a.raw) return b.raw - a.raw;
      return (b.avg ?? 0) - (a.avg ?? 0);
    });
    const pointsList = totalScores.slice(0, 3);

    return { puzzleList, catalogList, pointsList };
  }


  function renderRankings(rankings) {
    if (!grid) return;
    grid.innerHTML = '';
    const cards = [
      {
        title: 'Rätselwort-Bestzeit',
        list: rankings.puzzleList,
        tooltip: 'Top 3 Platzierungen für das schnellste Lösen des Rätselworts'
      },
      {
        title: 'Katalogmeister',
        list: rankings.catalogList,
        tooltip: 'Top 3 Teams/Spieler, die alle Fragenkataloge am schnellsten bearbeitet haben'
      },
      {
        title: 'Highscore-Champions',
        list: rankings.pointsList,
        tooltip: 'Top 3 Teams/Spieler mit den meisten Punkten'
      },
    ];
    const MAX_ITEMS = 3;
    cards.forEach(card => {
      const col = document.createElement('div');
      const c = document.createElement('div');
      c.className = 'uk-card qr-card uk-card-body';
      const h = document.createElement('h4');
      h.className = 'uk-card-title';
      h.append(document.createTextNode(card.title));
      if (card.tooltip) {
        const icon = document.createElement('span');
        icon.className = 'uk-margin-small-left';
        icon.setAttribute('uk-icon', 'icon: question');
        icon.setAttribute('uk-tooltip', `title: ${card.tooltip}; pos: right`);
        h.appendChild(icon);
        if (typeof UIkit !== 'undefined') {
          UIkit.icon(icon);
        }
      }
      c.appendChild(h);
      const ol = document.createElement('ol');
      // show numbering manually, so suppress default list style
      ol.className = 'uk-list';
      for (let i = 0; i < MAX_ITEMS; i++) {
        const li = document.createElement('li');
        const item = card.list[i];
        if (item) {
          const gridItem = document.createElement('div');
            gridItem.className = 'uk-grid-small';
            gridItem.setAttribute('uk-grid', '');

            const teamDiv = document.createElement('div');
            teamDiv.className = 'uk-width-expand ranking-team';
            teamDiv.setAttribute('uk-leader', '');
            teamDiv.style.setProperty('--uk-leader-fill-content', ' ');
            teamDiv.textContent = `${i + 1}. ${item.name}`;

            const timeDiv = document.createElement('div');
            timeDiv.textContent = item.value;

            gridItem.appendChild(teamDiv);
          gridItem.appendChild(timeDiv);
          li.appendChild(gridItem);
        } else {
          li.textContent = '-';
        }
        ol.appendChild(li);
      }
      c.appendChild(ol);
      col.appendChild(c);
      grid.appendChild(col);
    });
  }

  let catalogMap = null;

  function fetchCatalogMap() {
    if (catalogMap) return Promise.resolve(catalogMap);
    return fetch(withBase('/kataloge/catalogs.json'), {
      headers: { 'Accept': 'application/json' }
    })
      .then(r => r.json())
      .then(list => {
        const map = {};
        if (Array.isArray(list)) {
          catalogCount = list.length;
          list.forEach(c => {
            const name = c.name || '';
            if (c.uid) map[c.uid] = name;
            if (c.sort_order) map[c.sort_order] = name;
            if (c.slug) map[c.slug] = name;
          });
        } else {
          catalogCount = 0;
        }
        catalogMap = map;
        return map;
      })
      .catch(() => {
        catalogMap = {};
        return catalogMap;
      });
  }

  function renderNoEvent() {
    if (grid) {
      grid.innerHTML = '<p>Kein Event ausgewählt</p>';
    }
    if (tbody) {
      tbody.innerHTML = '';
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 8;
      td.textContent = 'Kein Event ausgewählt';
      tr.appendChild(td);
      tbody.appendChild(tr);
    }
    if (wrongBody) {
      wrongBody.innerHTML = '';
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 3;
      td.textContent = 'Kein Event ausgewählt';
      tr.appendChild(td);
      wrongBody.appendChild(tr);
    }
    if (pagination) {
      pagination.textContent = '';
    }
  }

  const buildEventQuery = uid => (uid ? `?event=${encodeURIComponent(uid)}` : '');
  const getCurrentEventUid = () => {
    const configUid = (window.quizConfig || {}).event_uid || '';
    return configUid || fallbackEventUid;
  };

  function load() {
    const currentEventUid = getCurrentEventUid();
    if (!currentEventUid) {
      renderNoEvent();
      return;
    }
    const requestId = ++activeRequestId;
    const eventQuery = buildEventQuery(currentEventUid);
    Promise.all([
      fetchCatalogMap(),
      fetch(withBase('/results.json' + eventQuery)).then(r => r.json()),
      fetch(withBase('/question-results.json' + eventQuery)).then(r => r.json())
    ])
      .then(([catMap, rows, qrows]) => {
        if (requestId !== activeRequestId) {
          return;
        }
        rows.forEach(r => {
          if (!r.catalogName && catMap[r.catalog]) r.catalogName = catMap[r.catalog];
          if (catMap[r.catalog]) r.catalog = catMap[r.catalog];
        });
        rows.sort((a, b) => b.time - a.time);
        resultsData = rows;
        currentPage = 1;
        renderPage(currentPage);
        initRotateButtons();
        refreshLightboxes();
        updatePagination();

        const rankings = computeRankings(rows, qrows);
        renderRankings(rankings);

        qrows.forEach(r => {
          if (!r.catalogName && catMap[r.catalog]) r.catalogName = catMap[r.catalog];
          if (catMap[r.catalog]) r.catalog = catMap[r.catalog];
        });
        const wrongOnly = qrows.filter(r => !r.correct);
        renderWrongTable(wrongOnly);
      })
      .catch(err => {
        if (requestId === activeRequestId) {
          console.error(err);
        }
      });
  }

  refreshBtn?.addEventListener('click', e => {
    e.preventDefault();
    load();
  });

  pagination?.addEventListener('click', e => {
    const target = e.target;
    if (target instanceof HTMLElement && target.dataset.page) {
      e.preventDefault();
      const page = parseInt(target.dataset.page, 10);
      if (!Number.isNaN(page)) {
        renderPage(page);
        initRotateButtons();
        refreshLightboxes();
        updatePagination();
      }
    }
  });

  function initRotateButtons() {
    document.querySelectorAll('.photo-rotate-btn').forEach(btn => {
      const wrap = btn.closest('.photo-wrapper');
      const img = wrap ? wrap.querySelector('img') : null;
      const link = wrap ? wrap.querySelector('a') : null;
      const path = btn.dataset.path || (link ? link.getAttribute('href') : '');
      if (!img || !path) return;
      btn.addEventListener('click', e => {
        e.preventDefault();
        rotatePhoto(path, img, link);
      });
    });
  }

  if (refreshBtn && typeof UIkit !== 'undefined') {
    UIkit.icon(refreshBtn);
  }

  if (!window.lightboxRotateHandler) {
    window.lightboxRotateHandler = e => {
      const btn = e.target.closest('.lightbox-rotate-btn');
      if (!btn) return;
      e.preventDefault();
      const path = btn.dataset.path || '';
      const panel = document.querySelector('.uk-lightbox-panel .uk-active');
      const img = panel ? panel.querySelector('picture img, img') :
                  document.querySelector('.uk-lightbox-items .uk-active img') ||
                  document.querySelector('.uk-lightbox-panel img.uk-inverse-light');
      if (img && path) {
        const clean = path.replace(/\?.*$/, '');
        const links = document.querySelectorAll(`a.rotate-link[href^='${clean}']`);
        const link = links[0] || null;
        rotatePhoto(path, img, link).then(newPath => {
          if (newPath) {
            const safePath = escapeHtml(newPath);
            links.forEach(a => {
              a.href = newPath;
              if (a.dataset && a.dataset.caption) {
                a.dataset.caption = a.dataset.caption
                  .replace(/data-path='[^']*'/, `data-path='${safePath}'`);
              }
            });
            btn.dataset.path = newPath;
          }
        });
      }
    };
    document.body.addEventListener('click', window.lightboxRotateHandler, { capture: true });
  }

  document.addEventListener('event:changed', e => {
    const detail = e.detail || {};
    fallbackEventUid = typeof detail.uid === 'string' ? detail.uid : fallbackEventUid;
    catalogMap = null;
    catalogCount = 0;
    resultsData = [];
    currentPage = 1;
    load();
  });

  load();
});
