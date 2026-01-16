/* global UIkit, STORAGE_KEYS, getStored, setStored, clearStored */

function insertSoftHyphens(text){
  return text ? text.replace(/\/-/g, '\u00AD') : '';
}

function safeUserName(name){
  if(typeof name !== 'string') return '';
  const trimmed = name.trim();
  if(trimmed === '') return '';
  const base = trimmed.replace(/[\u0000-\u001F<>]/g, '');
  if(base === '') return '';
  const normalized = typeof base.normalize === 'function' ? base.normalize('NFKC') : base;
  let unicodeSafe = normalized;
  try{
    unicodeSafe = normalized.replace(/[^\p{L}\p{N}\p{M}\p{Zs}\p{P}]/gu, '');
  }catch(e){
    unicodeSafe = normalized;
  }
  const trimmedUnicode = unicodeSafe.trim();
  const limitedUnicode = trimmedUnicode.slice(0, 100);
  const fallback = normalized.trim().slice(0, 100);
  return limitedUnicode || fallback;
}

function createPlayerNameMatcher(name){
  const variants = new Set();
  let primary = '';

  const addVariant = value => {
    if(typeof value !== 'string') return;
    const trimmed = value.trim();
    if(!trimmed) return;
    const normalized = typeof trimmed.normalize === 'function' ? trimmed.normalize('NFKC') : trimmed;
    [trimmed, normalized].forEach(variant => {
      if(typeof variant !== 'string' || variant === '') return;
      if(!variants.has(variant)){
        variants.add(variant);
        if(!primary) primary = variant;
      }
    });
  };

  addVariant(name);
  addVariant(safeUserName(name));

  const matches = candidate => {
    if(typeof candidate !== 'string') return false;
    const trimmedCandidate = candidate.trim();
    if(trimmedCandidate && variants.has(trimmedCandidate)){
      return true;
    }
    const normalizedCandidate = typeof trimmedCandidate.normalize === 'function'
      ? trimmedCandidate.normalize('NFKC')
      : trimmedCandidate;
    if(normalizedCandidate && variants.has(normalizedCandidate)){
      return true;
    }
    const sanitizedCandidate = safeUserName(candidate);
    if(!sanitizedCandidate) return false;
    const sanitizedTrimmed = sanitizedCandidate.trim();
    if(sanitizedTrimmed && variants.has(sanitizedTrimmed)){
      return true;
    }
    const sanitizedNormalized = typeof sanitizedTrimmed.normalize === 'function'
      ? sanitizedTrimmed.normalize('NFKC')
      : sanitizedTrimmed;
    return Boolean(sanitizedNormalized && variants.has(sanitizedNormalized));
  };

  return {
    primary,
    hasMatch: variants.size > 0,
    matches
  };
}

function formatPointsDisplay(points, maxPoints){
  const normalizedPoints = Number.isFinite(points) ? points : Number.parseInt(points, 10);
  if(!Number.isFinite(normalizedPoints)){
    return '';
  }
  const normalizedMax = Number.isFinite(maxPoints) ? maxPoints : Number.parseInt(maxPoints, 10);
  if(Number.isFinite(normalizedMax) && normalizedMax > 0){
    return `${normalizedPoints}/${normalizedMax}`;
  }
  return String(normalizedPoints);
}

function parseIntOr(value, fallback = 0){
  if(typeof value === 'number' && Number.isFinite(value)){
    return Math.trunc(value);
  }
  if(typeof value === 'boolean'){
    return value ? 1 : 0;
  }
  if(typeof value === 'string'){
    const trimmed = value.trim();
    if(trimmed === '') return fallback;
    const parsed = Number.parseInt(trimmed, 10);
    if(!Number.isNaN(parsed)){
      return parsed;
    }
    const numeric = Number(trimmed);
    if(Number.isFinite(numeric)){
      return Math.trunc(numeric);
    }
  }
  return fallback;
}

function parseOptionalInt(value){
  if(value === null || value === undefined) return null;
  if(typeof value === 'string' && value.trim() === '') return null;
  const numeric = Number(value);
  if(Number.isFinite(numeric)){
    return Math.trunc(numeric);
  }
  return null;
}

function parseOptionalFloat(value){
  if(value === null || value === undefined) return null;
  if(typeof value === 'string' && value.trim() === '') return null;
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
}

function toFiniteNumber(value){
  if(typeof value === 'number' && Number.isFinite(value)){
    return value;
  }
  if(value === null || value === undefined) return null;
  if(typeof value === 'string'){
    const trimmed = value.trim();
    if(trimmed === '') return null;
    const parsed = Number(trimmed);
    return Number.isFinite(parsed) ? parsed : null;
  }
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
}

function isTruthyFlag(value){
  if(value === null || value === undefined) return false;
  if(typeof value === 'boolean') return value;
  if(typeof value === 'number') return value !== 0;
  if(typeof value === 'string'){
    const normalized = value.trim().toLowerCase();
    return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
  }
  return false;
}

function isTruthyQueryParam(params, key){
  if(!params || typeof params.get !== 'function' || !params.has(key)){
    return false;
  }
  const raw = params.get(key);
  if(raw === null){
    return true;
  }
  if(typeof raw !== 'string'){
    return Boolean(raw);
  }
  const normalized = raw.trim().toLowerCase();
  if(normalized === ''){
    return true;
  }
  if(['0', 'false', 'no', 'off'].includes(normalized)){
    return false;
  }
  return true;
}

function formatEfficiencyPercent(value){
  if(!Number.isFinite(value)) return '';
  const percent = Math.round(value * 1000) / 10;
  const str = Number.isFinite(percent) ? percent.toString() : '0';
  return `${str.replace('.', ',')} %`;
}

function formatTimeInfo(timeLeft, total){
  const totalVal = parseOptionalInt(total);
  const leftVal = parseOptionalInt(timeLeft);
  if(totalVal !== null && totalVal > 0){
    const clamped = Math.max(0, Math.min(leftVal === null ? 0 : leftVal, totalVal));
    return `${clamped}s von ${totalVal}s verbleibend`;
  }
  if(leftVal !== null){
    const safe = Math.max(0, leftVal);
    return `${safe}s verbleibend`;
  }
  return '–';
}

function computePlayerRankings(rows, questionRows, catalogCount, matcher){
  const nameMatcher = matcher && typeof matcher.matches === 'function'
    ? matcher
    : createPlayerNameMatcher(typeof matcher === 'string' ? matcher : '');
  if(!nameMatcher.hasMatch){
    return null;
  }
  const safeRows = Array.isArray(rows) ? rows : [];
  const safeQuestionRows = Array.isArray(questionRows) ? questionRows : [];

  const attemptMetrics = new Map();
  safeQuestionRows.forEach(row => {
    if(!row) return;
    const team = typeof row.name === 'string' ? row.name : '';
    const catalogRaw = row.catalog ?? '';
    const catalog = catalogRaw !== null && catalogRaw !== undefined ? String(catalogRaw) : '';
    if(!team || !catalog) return;
    const attempt = parseIntOr(row.attempt, 1);
    const key = `${team}|${catalog}|${attempt}`;
    const pointsVal = parseIntOr(row.final_points ?? row.finalPoints ?? row.points ?? row.correct, 0);
    const efficiencyVal = parseOptionalFloat(row.efficiency);
    const efficiency = efficiencyVal !== null ? Math.max(0, Math.min(efficiencyVal, 1)) : (parseIntOr(row.correct, 0) === 1 ? 1 : 0);
    const summary = attemptMetrics.get(key) || { points: 0, effSum: 0, count: 0 };
    summary.points += pointsVal;
    summary.effSum += efficiency;
    summary.count += 1;
    attemptMetrics.set(key, summary);
  });

  const puzzleTimes = new Map();
  const catalogTimes = new Map();
  const scorePoints = new Map();
  const catalogs = new Set();

  safeRows.forEach(row => {
    if(!row) return;
    const team = typeof row.name === 'string' ? row.name : '';
    const catalogRaw = row.catalog ?? '';
    const catalog = catalogRaw !== null && catalogRaw !== undefined ? String(catalogRaw) : '';
    if(!team || !catalog) return;
    catalogs.add(catalog);
    const attempt = parseIntOr(row.attempt, 1);
    const key = `${team}|${catalog}|${attempt}`;
    const summary = attemptMetrics.get(key);
    let finalPoints;
    let effSum;
    let questionCount;
    if(summary && summary.count > 0){
      finalPoints = summary.points;
      effSum = summary.effSum;
      questionCount = summary.count;
    }else{
      const fallbackPoints = parseIntOr(row.points ?? row.correct, 0);
      finalPoints = fallbackPoints;
      const totalQuestions = Math.max(0, parseIntOr(row.total, 0));
      questionCount = totalQuestions;
      const correctCount = Math.max(0, parseIntOr(row.correct, 0));
      const avgFallback = totalQuestions > 0 ? Math.max(0, Math.min(correctCount / totalQuestions, 1)) : 0;
      effSum = avgFallback * totalQuestions;
    }
    const average = questionCount > 0 ? Math.max(0, Math.min(effSum / questionCount, 1)) : 0;

    const puzzleTime = parseOptionalInt(row.puzzleTime);
    if(puzzleTime !== null){
      const prev = puzzleTimes.get(team);
      if(prev === undefined || puzzleTime < prev){
        puzzleTimes.set(team, puzzleTime);
      }
    }

    const timeVal = parseOptionalInt(row.time);
    if(timeVal !== null){
      let map = catalogTimes.get(team);
      if(!map){
        map = new Map();
        catalogTimes.set(team, map);
      }
      const prevTime = map.get(catalog);
      if(prevTime === undefined || timeVal < prevTime){
        map.set(catalog, timeVal);
      }
    }

    let scoreMap = scorePoints.get(team);
    if(!scoreMap){
      scoreMap = new Map();
      scorePoints.set(team, scoreMap);
    }
    const prevScore = scoreMap.get(catalog);
    if(!prevScore || finalPoints > prevScore.points || (finalPoints === prevScore.points && average > prevScore.avg)){
      scoreMap.set(catalog, {
        points: finalPoints,
        effSum,
        count: questionCount,
        avg: average
      });
    }
  });

  const totalCatalogs = catalogCount > 0 ? catalogCount : catalogs.size;

  const puzzleList = Array.from(puzzleTimes.entries())
    .map(([name, time]) => ({ name, time }))
    .sort((a, b) => a.time - b.time);
  const puzzleIndex = puzzleList.findIndex(entry => nameMatcher.matches(entry.name));
  const puzzlePlace = puzzleIndex >= 0 ? puzzleIndex + 1 : null;
  const puzzleValue = puzzleIndex >= 0 ? puzzleList[puzzleIndex].time : null;

  const finisherList = [];
  catalogTimes.forEach((map, name) => {
    if(totalCatalogs > 0 && map.size === totalCatalogs){
      let last = -Infinity;
      map.forEach(val => {
        if(typeof val === 'number' && Number.isFinite(val) && val > last){
          last = val;
        }
      });
      if(Number.isFinite(last)){
        finisherList.push({ name, time: last });
      }
    }
  });
  finisherList.sort((a, b) => a.time - b.time);
  const catalogIndex = finisherList.findIndex(entry => nameMatcher.matches(entry.name));
  const catalogPlace = catalogIndex >= 0 ? catalogIndex + 1 : null;
  const catalogValue = catalogIndex >= 0 ? finisherList[catalogIndex].time : null;

  const scoreList = [];
  scorePoints.forEach((map, name) => {
    let total = 0;
    let effSumTotal = 0;
    let questionCountTotal = 0;
    map.forEach(entry => {
      total += Number.isFinite(entry.points) ? entry.points : 0;
      effSumTotal += Number.isFinite(entry.effSum) ? entry.effSum : 0;
      questionCountTotal += Number.isFinite(entry.count) ? entry.count : 0;
    });
    const avg = questionCountTotal > 0 ? Math.max(0, Math.min(effSumTotal / questionCountTotal, 1)) : 0;
    scoreList.push({ name, points: total, avg });
  });
  scoreList.sort((a, b) => {
    if(b.points !== a.points){
      return b.points - a.points;
    }
    return (b.avg ?? 0) - (a.avg ?? 0);
  });
  const pointsIndex = scoreList.findIndex(entry => nameMatcher.matches(entry.name));
  const pointsPlace = pointsIndex >= 0 ? pointsIndex + 1 : null;
  const pointsValue = pointsIndex >= 0 ? scoreList[pointsIndex].points : null;
  const pointsAvg = pointsIndex >= 0 ? scoreList[pointsIndex].avg : null;

  return {
    puzzle: { place: puzzlePlace, total: puzzleList.length, value: puzzleValue },
    catalog: { place: catalogPlace, total: finisherList.length, value: catalogValue },
    points: { place: pointsPlace, total: scoreList.length, value: pointsValue, avg: pointsAvg }
  };
}
function initSummaryPage(options = {}) {
  const cfg = options.config || window.quizConfig || {};
  const params = options.params instanceof URLSearchParams
    ? options.params
    : new URLSearchParams(window.location.search);
  const eventUidFromQuery = params.get('event_uid');
  const eventUid = options.eventUid || eventUidFromQuery || (window.getActiveEventId ? window.getActiveEventId() : '');
  const eventQuery = eventUid ? `?event_uid=${encodeURIComponent(eventUid)}` : '';
  const resultsJsonPath = '/results.json' + eventQuery;
  const questionResultsPath = '/question-results.json' + eventQuery;
  const playerUidKey = STORAGE_KEYS.PLAYER_UID;
  const resultsBtn = document.getElementById('show-results-btn');
  const puzzleBtn = document.getElementById('check-puzzle-btn');
  const photoBtn = document.getElementById('upload-photo-btn');
  const finishBtn = document.getElementById('finish-session-btn');
  const basePath = window.basePath || '';
  const withBase = path => basePath + path;
  const resultsContainer = options.resultsContainer || null;
  const resultsViewMode = String(
    options.resultsViewMode || cfg.resultsViewMode || cfg.results_view_mode || 'split'
  ).toLowerCase();
  const autoShowResults = options.autoShowResults ?? true;
  const forcedResultsFromWindow = typeof window.forceResults === 'string'
    ? window.forceResults === 'true'
    : Boolean(window.forceResults);
  const forcedResultsFromQuery = (
    isTruthyQueryParam(params, 'results')
    || isTruthyQueryParam(params, 'showResults')
    || isTruthyQueryParam(params, 'forceResults')
  );
  const shouldForceResults = forcedResultsFromWindow || forcedResultsFromQuery;
  const resultsEnabled = shouldForceResults || !(cfg && cfg.teamResults === false);
  const puzzleEnabled = isTruthyFlag(cfg.puzzleWordEnabled ?? cfg.puzzle_word_enabled);
  if (resultsBtn && !resultsEnabled) {
    resultsBtn.remove();
  }
  const photoEnabled = !(cfg && cfg.photoUpload === false);
  if (photoBtn && !photoEnabled) {
    photoBtn.remove();
  }
  if (puzzleBtn && !puzzleEnabled) {
    puzzleBtn.remove();
  }
  const puzzleInfo = document.getElementById('puzzle-solved-text');
  const storedNameValue = getStored(STORAGE_KEYS.PLAYER_NAME);
  const playerName = typeof storedNameValue === 'string' ? storedNameValue : '';
  const trimmedPlayerName = playerName.trim();
  const sanitizedPlayerName = safeUserName(playerName);
  const playerNameMatcher = createPlayerNameMatcher(playerName);
  const user = sanitizedPlayerName || trimmedPlayerName || 'Unbekannt';
  const countdownEnabled = isTruthyFlag(cfg.countdownEnabled ?? cfg.countdown_enabled);
  const defaultCountdown = parseIntOr(cfg.countdown ?? cfg.defaultCountdown ?? 0, 0);

  if (finishBtn) {
    finishBtn.addEventListener('click', () => {
      const params = new URLSearchParams();
      const storedPlayerUid = typeof getStored === 'function' && typeof STORAGE_KEYS === 'object'
        ? getStored(playerUidKey)
        : '';
      if (eventUid) {
        params.set('event_uid', eventUid);
      }
      if (storedPlayerUid) {
        params.set('player_uid', storedPlayerUid);
      }
      [
        STORAGE_KEYS.CATALOG,
        STORAGE_KEYS.CATALOG_NAME,
        STORAGE_KEYS.CATALOG_DESC,
        STORAGE_KEYS.CATALOG_COMMENT,
        STORAGE_KEYS.CATALOG_UID,
        STORAGE_KEYS.CATALOG_SORT,
        STORAGE_KEYS.LETTER,
        STORAGE_KEYS.PUZZLE_SOLVED,
        STORAGE_KEYS.PUZZLE_TIME,
        STORAGE_KEYS.QUIZ_SOLVED
      ].forEach(key => clearStored(key));
      const query = params.toString();
      const destination = resultsViewMode === 'hub' ? '/results-hub' : '/ranking';
      const target = `${destination}${query ? `?${query}` : ''}`;
      window.location.href = withBase(target);
    });
  }

  let catalogMap = null;
  let catalogCount = 0;
  function fetchCatalogMap() {
    if (catalogMap) return Promise.resolve(catalogMap);
    const catalogQuery = eventUid ? `?event=${encodeURIComponent(eventUid)}` : '';
    return fetch(withBase('/kataloge/catalogs.json' + catalogQuery), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(list => {
        const map = {};
        if (Array.isArray(list)) {
          catalogCount = list.length;
          list.forEach(c => {
            const entry = { name: c.name || '', slug: c.slug || '' };
            if (c.uid) map[c.uid] = entry;
            if (c.sort_order) map[c.sort_order] = entry;
            if (c.slug) map[c.slug] = entry;
          });
        } else {
          catalogCount = 0;
        }
        catalogMap = map;
        return map;
      })
      .catch(() => {
        catalogCount = 0;
        catalogMap = {};
        return catalogMap;
      });
  }

  const formatTs = window.formatPuzzleTime || function(ts){
    const d = new Date(ts * 1000);
    const pad = n => n.toString().padStart(2,'0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };

  async function fetchPuzzleTimeFromResults(nameOrMatcher){
    const matcher = nameOrMatcher && typeof nameOrMatcher.matches === 'function'
      ? nameOrMatcher
      : createPlayerNameMatcher(nameOrMatcher);
    if(!matcher.hasMatch){
      return null;
    }
    try{
      const list = await fetch(withBase(resultsJsonPath)).then(r => r.json());
      if(Array.isArray(list)){
        for(let i=list.length-1; i>=0; i--){
          const e = list[i];
          if(e && matcher.matches(e.name) && e.puzzleTime){
            return parseInt(e.puzzleTime, 10);
          }
        }
      }
    }catch(e){
      return null;
    }
    return null;
  }

  async function updatePuzzleInfo(){
    let solved = getStored(STORAGE_KEYS.PUZZLE_SOLVED) === 'true';
    let ts = parseInt(getStored(STORAGE_KEYS.PUZZLE_TIME) || '0', 10);
    if(!solved){
      const name = getStored(STORAGE_KEYS.PLAYER_NAME) || '';
      if(name){
        const t = await fetchPuzzleTimeFromResults(createPlayerNameMatcher(name));
        if(t){
          solved = true;
          ts = t;
          setStored(STORAGE_KEYS.PUZZLE_SOLVED, 'true');
          setStored(STORAGE_KEYS.PUZZLE_TIME, String(t));
        }
      }
    }
    if(solved){
      if (puzzleBtn) puzzleBtn.remove();
      if(ts && puzzleInfo){
        puzzleInfo.textContent = `Rätselwort gelöst: ${formatTs(ts)}`;
      }
    }else{
      if(puzzleInfo) puzzleInfo.textContent = '';
    }
  }

  function renderQuestionPreview(q, catMap){
    const card = document.createElement('div');
    card.className = 'uk-card qr-card uk-card-body question-preview';
    const title = document.createElement('h5');
    const info = catMap[q.catalog];
    const cat = q.catalogName || (info ? info.name : q.catalog);
    title.textContent = insertSoftHyphens(cat);
    card.appendChild(title);

    const h = document.createElement('h4');
    h.textContent = insertSoftHyphens(q.prompt || '');
    card.appendChild(h);

    const type = q.type || 'mc';
    if(type === 'sort' && Array.isArray(q.items)){
      const ul = document.createElement('ul');
      q.items.forEach(it => {
        const li = document.createElement('li');
        li.textContent = insertSoftHyphens(it);
        ul.appendChild(li);
      });
      card.appendChild(ul);
    }else if(type === 'assign' && Array.isArray(q.terms)){
      const ul = document.createElement('ul');
      q.terms.forEach(p => {
        const li = document.createElement('li');
        li.textContent = insertSoftHyphens(p.term || '') + ' – ' + insertSoftHyphens(p.definition || '');
        ul.appendChild(li);
      });
      card.appendChild(ul);
    }else if(type === 'swipe' && Array.isArray(q.cards)){
      const ul = document.createElement('ul');
      q.cards.forEach(c => {
        const li = document.createElement('li');
        li.textContent = insertSoftHyphens(c.text) + (c.correct ? ' ✓' : '');
        ul.appendChild(li);
      });
      card.appendChild(ul);
    }else{
      const ul = document.createElement('ul');
      if(Array.isArray(q.options)){
        const answers = Array.isArray(q.answers) ? q.answers : [];
        q.options.forEach((opt, i) => {
          const li = document.createElement('li');
          const correct = answers.includes(i);
          li.textContent = insertSoftHyphens(opt) + (correct ? ' ✓' : '');
          if(correct) li.classList.add('uk-text-success');
          ul.appendChild(li);
        });
      }
      card.appendChild(ul);
    }

    return card;
  }

  function renderResultsContent(contentWrap) {
    if (!contentWrap) {
      return;
    }
    contentWrap.innerHTML = '';
    Promise.all([
      fetchCatalogMap(),
      fetch(withBase(resultsJsonPath)).then(r => r.json()),
      fetch(withBase(questionResultsPath)).then(r => r.json())
    ])
      .then(([catMap, rows, qrows]) => {
        const catalogLookup = (catMap && typeof catMap === 'object') ? catMap : {};
        const safeRows = Array.isArray(rows) ? rows : [];
        const safeQuestions = Array.isArray(qrows) ? qrows : [];
        const filtered = safeRows.filter(row => row && playerNameMatcher.matches(row.name));
        const summaryMap = new Map();
        filtered.forEach(r => {
          if(!r) return;
          const info = catalogLookup[r.catalog] || { name: r.catalog, slug: r.catalog };
          const baseName = info && info.name ? info.name : r.catalog;
          const displayName = r.catalogName || baseName;
          const finalPointsRaw = parseOptionalInt(r.final_points ?? r.finalPoints);
          const basePointsRaw = parseOptionalInt(r.points);
          const fallbackPoints = parseIntOr(r.correct, 0);
          const resolvedPointsValue = finalPointsRaw !== null ? finalPointsRaw : (basePointsRaw !== null ? basePointsRaw : fallbackPoints);
          const numericPoints = toFiniteNumber(resolvedPointsValue) ?? 0;
          const maxPointsVal = parseOptionalInt(r.max_points ?? r.maxPoints);
          const normalizedMaxPoints = toFiniteNumber(maxPointsVal) ?? 0;
          const correctVal = parseIntOr(r.correct, 0);
          const totalVal = parseIntOr(r.total, 0);
          const attemptVal = parseIntOr(r.attempt, 1);
          const pointsText = formatPointsDisplay(numericPoints, normalizedMaxPoints);
          const correctText = `${correctVal}/${totalVal}`;
          summaryMap.set(displayName, {
            slug: info.slug,
            points: numericPoints,
            maxPoints: normalizedMaxPoints,
            correct: correctVal,
            total: totalVal,
            attempt: attemptVal,
            catalogRef: r.catalog,
            displayName,
            pointsText,
            correctText
          });
        });

        const attemptByCatalog = new Map();
        const displayNameByCatalog = new Map();
        summaryMap.forEach(entry => {
          if(entry.catalogRef){
            const key = String(entry.catalogRef);
            attemptByCatalog.set(key, entry.attempt);
            displayNameByCatalog.set(key, entry.displayName);
          }
        });

        const questionList = safeQuestions.filter(row => row && playerNameMatcher.matches(row.name));
        const relevantQuestions = questionList.filter(row => {
          if(!row) return false;
          const catalogKeyRaw = row.catalog ?? '';
          const catalogKey = catalogKeyRaw !== null && catalogKeyRaw !== undefined ? String(catalogKeyRaw) : '';
          if(!catalogKey) return false;
          const expectedAttempt = attemptByCatalog.get(catalogKey);
          if(expectedAttempt === undefined) return false;
          const attemptVal = parseIntOr(row.attempt, 1);
          return attemptVal === expectedAttempt;
        });

        let efficiencySum = 0;
        let efficiencyCount = 0;
        relevantQuestions.forEach(row => {
          const effVal = parseOptionalFloat(row.efficiency);
          if(effVal !== null){
            const clamped = Math.max(0, Math.min(effVal, 1));
            efficiencySum += clamped;
            efficiencyCount += 1;
          }
        });
        const averageEfficiency = efficiencyCount > 0 ? efficiencySum / efficiencyCount : null;

        const questionAggregates = new Map();
        relevantQuestions.forEach(row => {
          const catalogKeyRaw = row.catalog ?? '';
          const catalogKey = catalogKeyRaw !== null && catalogKeyRaw !== undefined ? String(catalogKeyRaw) : '';
          if(!catalogKey) return;
          const attemptVal = parseIntOr(row.attempt, 1);
          const aggregateKey = `${catalogKey}|${attemptVal}`;
          const finalPoints = parseIntOr(row.finalPoints ?? row.final_points ?? row.points, 0);
          const questionPoints = parseIntOr(row.questionPoints ?? row.points, 0);
          const existing = questionAggregates.get(aggregateKey) || { points: 0, maxPoints: 0, count: 0, hasNonZeroPoints: false };
          existing.points += finalPoints;
          if(questionPoints > 0){
            existing.maxPoints += questionPoints;
          }
          existing.count += 1;
          if(finalPoints !== 0){
            existing.hasNonZeroPoints = true;
          }
          questionAggregates.set(aggregateKey, existing);
        });

        summaryMap.forEach(entry => {
          if(!entry.catalogRef) return;
          const key = `${String(entry.catalogRef)}|${entry.attempt}`;
          const aggregate = questionAggregates.get(key);
          if(aggregate && aggregate.count > 0){
            const aggregatedPoints = toFiniteNumber(aggregate.points);
            const currentPoints = toFiniteNumber(entry.points) ?? 0;
            if(aggregatedPoints !== null){
              if(aggregatedPoints === 0 && currentPoints > 0 && !aggregate.hasNonZeroPoints){
                entry.points = currentPoints;
              }else{
                entry.points = aggregatedPoints;
              }
            }else{
              entry.points = currentPoints;
            }
            const aggregatedMaxPoints = toFiniteNumber(aggregate.maxPoints);
            if(aggregatedMaxPoints !== null && aggregatedMaxPoints > entry.maxPoints){
              entry.maxPoints = aggregatedMaxPoints;
            }
            const resolvedMax = entry.maxPoints;
            entry.pointsText = formatPointsDisplay(entry.points, resolvedMax);
          }
        });

        const summaryValues = Array.from(summaryMap.values());
        const totalPoints = summaryValues.reduce((sum, entry) => {
          const value = toFiniteNumber(entry.points);
          return sum + (value !== null ? value : 0);
        }, 0);
        const totalMaxPoints = summaryValues.reduce((sum, entry) => {
          const value = toFiniteNumber(entry.maxPoints);
          return sum + (value !== null ? value : 0);
        }, 0);
        const totalCorrect = summaryValues.reduce((sum, entry) => {
          const value = toFiniteNumber(entry.correct);
          return sum + (value !== null ? value : 0);
        }, 0);
        const totalQuestions = summaryValues.reduce((sum, entry) => {
          const value = toFiniteNumber(entry.total);
          return sum + (value !== null ? value : 0);
        }, 0);

        const createStatCard = (label, value, description = '') => {
          const col = document.createElement('div');
          const card = document.createElement('div');
          card.className = 'uk-card qr-card uk-card-body uk-padding-small uk-text-center';
          const heading = document.createElement('h5');
          heading.className = 'uk-margin-remove';
          heading.textContent = label;
          const valueEl = document.createElement('div');
          valueEl.className = 'uk-text-large uk-margin-small-top';
          valueEl.textContent = value;
          card.append(heading, valueEl);
          if(description){
            const desc = document.createElement('p');
            desc.className = 'uk-text-meta uk-margin-small-top';
            desc.textContent = description;
            card.appendChild(desc);
          }
          col.appendChild(card);
          return col;
        };

        if(summaryValues.length && contentWrap){
          const statsGrid = document.createElement('div');
          statsGrid.className = 'uk-grid-small uk-child-width-1-1 uk-child-width-1-3@s uk-margin-top';
          statsGrid.setAttribute('uk-grid', 'margin: small');
          statsGrid.appendChild(createStatCard('Gesamtpunkte', formatPointsDisplay(totalPoints, totalMaxPoints)));
          const correctValue = totalQuestions > 0 ? `${totalCorrect}/${totalQuestions}` : String(totalCorrect);
          statsGrid.appendChild(createStatCard('Richtige Antworten', correctValue));
          if(averageEfficiency !== null){
            statsGrid.appendChild(createStatCard('Ø Effizienz', formatEfficiencyPercent(averageEfficiency)));
          }
          contentWrap.appendChild(statsGrid);
        }

        const rankingInfo = computePlayerRankings(safeRows, safeQuestions, catalogCount, playerNameMatcher);
        if(rankingInfo && contentWrap){
          const pointsRanking = rankingInfo.points || { place: null, total: 0 };
          const catalogRanking = rankingInfo.catalog || { place: null, total: 0 };
          const puzzleRanking = puzzleEnabled ? (rankingInfo.puzzle || { place: null, total: 0 }) : { place: null, total: 0 };
          const rankingSources = [pointsRanking, catalogRanking];
          if(puzzleEnabled){
            rankingSources.push(puzzleRanking);
          }
          const hasRankingData = rankingSources.some(info => info.total > 0);
          if(hasRankingData){
            const rankingHeading = document.createElement('h4');
            rankingHeading.className = 'uk-heading-bullet uk-margin-top';
            rankingHeading.textContent = 'Ranglisten';
            const rankingGrid = document.createElement('div');
            rankingGrid.className = 'uk-grid-small uk-child-width-1-1 uk-child-width-1-3@s';
            rankingGrid.setAttribute('uk-grid', 'margin: small');

            const appendRankingCard = (title, info, valueText, emptyText) => {
              const col = document.createElement('div');
              const card = document.createElement('div');
              card.className = 'uk-card qr-card uk-card-body uk-padding-small uk-text-center';
              const headingEl = document.createElement('h5');
              headingEl.className = 'uk-margin-remove';
              headingEl.textContent = title;
              const placeEl = document.createElement('div');
              placeEl.className = 'uk-text-large uk-margin-small-top';
              if(info.total > 0 && typeof info.place === 'number' && Number.isFinite(info.place)){
                placeEl.textContent = `Platz ${info.place} von ${info.total}`;
              }else if(info.total > 0){
                placeEl.textContent = 'Noch nicht platziert';
              }else{
                placeEl.textContent = emptyText;
              }
              card.append(headingEl, placeEl);
              if(valueText){
                const meta = document.createElement('p');
                meta.className = 'uk-text-meta uk-margin-small-top';
                meta.textContent = valueText;
                card.appendChild(meta);
              }
              col.appendChild(card);
              rankingGrid.appendChild(col);
            };

            const pointsDetails = (() => {
              const parts = [];
              if(typeof pointsRanking.value === 'number' && Number.isFinite(pointsRanking.value)){
                parts.push(`Punkte: ${pointsRanking.value}`);
              }
              if(typeof pointsRanking.avg === 'number' && Number.isFinite(pointsRanking.avg)){
                parts.push(`Ø ${formatEfficiencyPercent(pointsRanking.avg)}`);
              }
              return parts.join(' · ');
            })();

            const catalogDetails = (typeof catalogRanking.value === 'number' && Number.isFinite(catalogRanking.value))
              ? `Abschluss: ${formatTs(catalogRanking.value)}`
              : '';
            const puzzleDetails = (
              puzzleEnabled
              && typeof puzzleRanking.value === 'number'
              && Number.isFinite(puzzleRanking.value)
            )
              ? `Zeit: ${formatTs(puzzleRanking.value)}`
              : '';

            const hasMultipleCatalogs = Number.isFinite(catalogCount) ? catalogCount > 1 : false;
            appendRankingCard('Highscore', pointsRanking, pointsDetails, 'Noch keine Punktewertung');
            if(hasMultipleCatalogs){
              appendRankingCard('Katalogmeister', catalogRanking, catalogDetails, 'Noch nicht alle Kataloge abgeschlossen');
            }
            if(puzzleEnabled){
              appendRankingCard('Rätselwort', puzzleRanking, puzzleDetails, 'Noch kein Rätselwort gelöst');
            }

            if(rankingGrid.children.length){
              contentWrap.appendChild(rankingHeading);
              contentWrap.appendChild(rankingGrid);
            }
          }
        }

        const catHeading = document.createElement('h4');
        catHeading.className = 'uk-heading-bullet uk-margin-top';
        catHeading.textContent = 'Katalogübersicht';
        if(contentWrap) contentWrap.appendChild(catHeading);

        const tableWrap = document.createElement('div');
        tableWrap.className = 'uk-overflow-auto';
        const table = document.createElement('table');
        table.className = 'uk-table uk-table-divider';
        const thead = document.createElement('thead');
        const trh = document.createElement('tr');
        const th1 = document.createElement('th');
        th1.textContent = 'Katalog';
        const th2 = document.createElement('th');
        th2.textContent = 'Richtige Antworten';
        const th3 = document.createElement('th');
        th3.textContent = 'Punkte';
        trh.append(th1, th2, th3);
        thead.appendChild(trh);
        table.appendChild(thead);
        const tb = document.createElement('tbody');
        if(summaryMap.size === 0){
          const tr = document.createElement('tr');
          const td = document.createElement('td');
          td.colSpan = 3;
          td.textContent = 'Keine Daten';
          tr.appendChild(td);
          tb.appendChild(tr);
        }else{
          summaryMap.forEach((info, cat) => {
            const tr = document.createElement('tr');
            const td1 = document.createElement('td');
            const link = document.createElement('a');
            if (info.slug) {
              let href = '/?katalog=' + encodeURIComponent(info.slug);
              if(eventUid) href += '&event=' + encodeURIComponent(eventUid);
              link.href = href;
              link.target = '_blank';
            } else {
              link.href = '#';
            }
            link.textContent = cat;
            td1.appendChild(link);
            const td2 = document.createElement('td');
            td2.textContent = info.correctText || '–';
            const td3 = document.createElement('td');
            td3.textContent = info.pointsText || '–';
            tr.appendChild(td1);
            tr.appendChild(td2);
            tr.appendChild(td3);
            tb.appendChild(tr);
          });
        }
        table.appendChild(tb);
        tableWrap.appendChild(table);
        if(contentWrap) contentWrap.appendChild(tableWrap);

        if(countdownEnabled && relevantQuestions.length && contentWrap){
          const questionHeading = document.createElement('h4');
          questionHeading.className = 'uk-heading-bullet uk-margin-top';
          questionHeading.textContent = 'Punkte pro Frage';
          contentWrap.appendChild(questionHeading);

          const questionWrap = document.createElement('div');
          questionWrap.className = 'uk-overflow-auto';
          const qTable = document.createElement('table');
          qTable.className = 'uk-table uk-table-divider uk-table-small';
          const qThead = document.createElement('thead');
          const qHeadRow = document.createElement('tr');
          ['Katalog', 'Frage', 'Punkte', 'Restzeit', 'Effizienz', 'Ergebnis'].forEach(text => {
            const th = document.createElement('th');
            th.textContent = text;
            qHeadRow.appendChild(th);
          });
          qThead.appendChild(qHeadRow);
          qTable.appendChild(qThead);
          const qTbody = document.createElement('tbody');
          relevantQuestions.forEach(row => {
            const tr = document.createElement('tr');
            const catalogKeyRaw = row.catalog ?? '';
            const catalogKey = catalogKeyRaw !== null && catalogKeyRaw !== undefined ? String(catalogKeyRaw) : '';
            const displayName = row.catalogName || displayNameByCatalog.get(catalogKey) || (catalogLookup[catalogKey] ? catalogLookup[catalogKey].name : catalogKey);
            const finalPoints = parseIntOr(row.finalPoints ?? row.final_points ?? row.points, 0);
            const questionPoints = parseIntOr(row.questionPoints ?? row.points, 0);
            const timeLeft = parseOptionalInt(row.timeLeftSec ?? row.time_left_sec);
            let totalTime = parseOptionalInt(row.questionCountdown ?? row.countdown);
            if((totalTime === null || totalTime <= 0) && countdownEnabled && defaultCountdown > 0){
              totalTime = defaultCountdown;
            }
            const efficiencyVal = parseOptionalFloat(row.efficiency);
            const efficiencyText = efficiencyVal !== null ? formatEfficiencyPercent(efficiencyVal) : '–';
            const isCorrectRaw = row.isCorrect;
            const correctFlag = parseIntOr(row.correct, 0) === 1;
            const isCorrect = isCorrectRaw === undefined || isCorrectRaw === null ? correctFlag : !!isCorrectRaw;
            const catalogTd = document.createElement('td');
            catalogTd.textContent = insertSoftHyphens(displayName || '');
            const questionTd = document.createElement('td');
            questionTd.textContent = insertSoftHyphens(row.prompt || '');
            const pointsTd = document.createElement('td');
            pointsTd.textContent = formatPointsDisplay(finalPoints, questionPoints);
            const timeTd = document.createElement('td');
            timeTd.textContent = formatTimeInfo(timeLeft, totalTime);
            const efficiencyTd = document.createElement('td');
            efficiencyTd.textContent = efficiencyText;
            const resultTd = document.createElement('td');
            resultTd.textContent = isCorrect ? 'Richtig' : 'Falsch';
            resultTd.className = isCorrect ? 'uk-text-success' : 'uk-text-danger';
            tr.append(catalogTd, questionTd, pointsTd, timeTd, efficiencyTd, resultTd);
            qTbody.appendChild(tr);
          });
          if(!qTbody.children.length){
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 6;
            td.textContent = 'Keine Fragen mit Zeitwertung';
            tr.appendChild(td);
            qTbody.appendChild(tr);
          }
          qTable.appendChild(qTbody);
          questionWrap.appendChild(qTable);
          contentWrap.appendChild(questionWrap);
        }

        const wrong = relevantQuestions.filter(row => {
          if(!row) return false;
          const isCorrectRaw = row.isCorrect;
          if(isCorrectRaw !== undefined && isCorrectRaw !== null){
            return !isCorrectRaw;
          }
          return parseIntOr(row.correct, 0) !== 1;
        });
        if (wrong.length && contentWrap) {
          const wrongSection = document.createElement('div');
          wrongSection.className = 'uk-margin-top';
          const h = document.createElement('h4');
          h.textContent = 'Falsch beantwortete Fragen';
          wrongSection.appendChild(h);
          wrong.forEach(w => {
            const card = renderQuestionPreview(w, catalogLookup);
            wrongSection.appendChild(card);
          });
          contentWrap.appendChild(wrongSection);
        }
      })
      .catch(() => {
        if(contentWrap) contentWrap.textContent = 'Fehler beim Laden';
      });

    ui.show();
  }

  function showPuzzle(){
    const solvedBefore = getStored(STORAGE_KEYS.PUZZLE_SOLVED) === 'true';
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    const dialog = document.createElement('div');
    dialog.className = 'uk-modal-dialog uk-modal-body';
    const title = document.createElement('h3');
    title.className = 'uk-modal-title uk-text-center';
    title.textContent = 'Rätselwort überprüfen';
    let input = null;
    if(!solvedBefore){
      input = document.createElement('input');
      input.id = 'puzzle-input';
      input.className = 'uk-input';
      input.type = 'text';
      input.placeholder = 'Rätselwort eingeben';
    }
    const feedback = document.createElement('div');
    feedback.id = 'puzzle-feedback';
    feedback.className = 'uk-margin-top uk-text-center';
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
    btn.textContent = solvedBefore ? 'Schließen' : 'Überprüfen';
    dialog.append(title);
    if(input) dialog.appendChild(input);
    dialog.append(feedback, btn);
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    if(!solvedBefore && input) UIkit.util.on(modal, 'shown', () => { input.focus(); });
    function handleCheck(){
          const valRaw = (input.value || '').trim();
          const ts = Math.floor(Date.now()/1000);
          const userName = getStored(STORAGE_KEYS.PLAYER_NAME) || '';
          const catalog = getStored('quizCatalog') || 'unknown';
          const data = { name: userName, catalog, puzzleTime: ts, puzzleAnswer: valRaw };
          if(cfg.collectPlayerUid){
            const uid = getStored(playerUidKey);
            if(uid) data.player_uid = uid;
          }
          let debugTimer = null;
          const debugQuery = '?debug=1' + (eventUid ? `&event_uid=${encodeURIComponent(eventUid)}` : '');
          fetch(withBase('/results' + debugQuery), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              ...data,
              ...(eventUid ? { event_uid: eventUid } : {})
            })
          })
        .then(async r => {
          if(!r.ok){
            throw new Error('HTTP ' + r.status);
          }
          try{
            return await r.json();
          }catch(e){
            return null;
          }
        })
        .then(data => {
          if(data){
          if(data.normalizedAnswer !== undefined && data.normalizedExpected !== undefined){
              feedback.textContent = `Debug: ${data.normalizedAnswer} vs ${data.normalizedExpected}`;
              debugTimer = setTimeout(() => { feedback.textContent = ''; }, 3000);
            }
          if(data.success){
              if(debugTimer) clearTimeout(debugTimer);
              const cfg = window.quizConfig || {};
              const msg = (data.feedback && data.feedback.trim())
                ? data.feedback
                : (cfg.puzzleFeedback && cfg.puzzleFeedback.trim())
                  ? cfg.puzzleFeedback
                  : 'Herzlichen Glückwunsch, das Rätselwort ist korrekt!';
              feedback.textContent = msg;
              feedback.className = 'uk-margin-top uk-text-center uk-text-success';
              setStored(STORAGE_KEYS.PUZZLE_SOLVED, 'true');
              setStored(STORAGE_KEYS.PUZZLE_TIME, String(ts));
              updatePuzzleInfo();
              input.disabled = true;
              btn.textContent = 'Schließen';
              btn.removeEventListener('click', handleCheck);
              btn.addEventListener('click', () => ui.hide());
              return;
            }
          }
          feedback.textContent = 'Das ist leider nicht korrekt. Viel Glück beim nächsten Versuch!';
          feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
        })
        .catch(() => {
          feedback.textContent = 'Fehler bei der Überprüfung.';
          feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
        });
    }
    if(solvedBefore){
      feedback.textContent = 'Du hast das Rätselwort bereits gelöst.';
      feedback.className = 'uk-margin-top uk-text-center uk-text-success';
      btn.addEventListener('click', () => ui.hide());
    }else{
      btn.addEventListener('click', handleCheck);
    }
    ui.show();
  }

  function showPhotoModal(cb, requireConsent = true){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    const dialog = document.createElement('div');
    dialog.className = 'uk-modal-dialog uk-modal-body';
    const card = document.createElement('div');
    card.className = 'uk-card qr-card uk-card-body uk-padding-small uk-width-1-1';

    const p = document.createElement('p');
    p.className = 'uk-text-small';
    p.append(
      'Hinweis zum Hochladen von Gruppenfotos:',
      document.createElement('br'),
      'Ich bestätige, dass alle auf dem Foto abgebildeten Personen vor der Aufnahme darüber informiert wurden, dass das Gruppenfoto zu Dokumentationszwecken erstellt und ggf. veröffentlicht wird. Alle Anwesenden hatten Gelegenheit, der Aufnahme zu widersprechen, indem sie den Aufnahmebereich verlassen oder dies ausdrücklich mitteilen konnten.'
    );

    const fileDiv = document.createElement('div');
    fileDiv.className = 'uk-margin-small-bottom';
    const label = document.createElement('label');
    label.className = 'uk-form-label';
    label.setAttribute('for', 'photo-input');
    label.textContent = 'Beweisfoto auswählen';
    const uploadDiv = document.createElement('div');
    uploadDiv.className = 'stacked-upload';
    uploadDiv.setAttribute('uk-form-custom', 'target: true');
    const input = document.createElement('input');
    input.id = 'photo-input';
    input.type = 'file';
    input.accept = 'image/*';
    input.setAttribute('capture', 'environment');
    input.setAttribute('aria-label', 'Datei auswählen');
    const textInput = document.createElement('input');
    textInput.className = 'uk-input uk-width-1-1';
    textInput.type = 'text';
    textInput.placeholder = 'Keine Datei ausgewählt';
    textInput.disabled = true;
    const browseBtn = document.createElement('button');
    browseBtn.className = 'uk-button uk-button-default uk-width-1-1 uk-margin-small-top';
    browseBtn.type = 'button';
    browseBtn.tabIndex = -1;
    browseBtn.textContent = 'Durchsuchen';
    uploadDiv.append(input, textInput, browseBtn);
    fileDiv.append(label, uploadDiv);

    card.append(p, fileDiv);

    let consent = null;
    if (requireConsent) {
      const consentLabel = document.createElement('label');
      consentLabel.className = 'uk-form-label uk-margin-small-bottom';
      consent = document.createElement('input');
      consent.type = 'checkbox';
      consent.id = 'consent-checkbox';
      consent.className = 'uk-checkbox uk-margin-small-right';
      consentLabel.append(consent, 'Einverständnis aller abgebildeten Personen wurde eingeholt ');
      card.appendChild(consentLabel);
    }

    const feedback = document.createElement('div');
    feedback.id = 'photo-feedback';
    feedback.className = 'uk-margin-small uk-text-center';
    const btn = document.createElement('button');
    btn.id = 'upload-btn';
    btn.className = 'uk-button uk-button-primary uk-width-1-1';
    btn.disabled = true;
    btn.textContent = 'Hochladen';
    card.append(feedback, btn);

    dialog.appendChild(card);
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });

    function toggleBtn(){
      btn.disabled = !input.files.length || (requireConsent && consent && !consent.checked);
    }
    input.addEventListener('change', toggleBtn);
    if(consent) consent.addEventListener('change', toggleBtn);
    btn.addEventListener('click', () => {
      const file = input.files && input.files[0];
      if(!file || (requireConsent && consent && !consent.checked)) return;
      const fd = new FormData();
      fd.append('photo', file);
      const uploadName = playerName || user;
      fd.append('name', uploadName);
      fd.append('catalog', 'summary');
      fd.append('team', uploadName);
      if(cfg.collectPlayerUid){
        const uid = getStored(playerUidKey);
        if(uid) fd.append('player_uid', uid);
      }

      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = '';
      const spinner = document.createElement('div');
      spinner.setAttribute('uk-spinner', '');
      btn.appendChild(spinner);

      fetch(withBase('/photos'), { method: 'POST', body: fd })
        .then(async r => {
          if (!r.ok) {
            throw new Error(await r.text());
          }
          const ct = r.headers.get('Content-Type') || '';
          if (!ct.includes('application/json')) {
            throw new Error(await r.text());
          }
          return r.json();
        })
        .then(data => {
          feedback.textContent = 'Foto gespeichert';
          feedback.className = 'uk-margin-top uk-text-center uk-text-success';
          btn.disabled = true;
          input.disabled = true;
          if(consent) consent.disabled = true;
          setTimeout(() => {
            ui.hide();
            if (typeof UIkit !== 'undefined' && UIkit.notification) {
              UIkit.notification({
                message: 'Bild erfolgreich gespeichert',
                status: 'success',
                pos: 'top-center',
                timeout: 2000
              });
            } else {
              alert('Bild erfolgreich gespeichert');
            }
          }, 1000);
          if(typeof cb === 'function') cb(data.path);
        })
        .catch(e => {
          feedback.textContent = e.message || 'Fehler beim Hochladen';
          feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
        })
        .finally(() => {
          btn.textContent = originalText;
        });
    });
    ui.show();
  }

  function showResults(){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    const dialog = document.createElement('div');
    dialog.className = 'uk-modal-dialog uk-modal-body';
    const title = document.createElement('h3');
    title.className = 'uk-modal-title uk-text-center';
    title.textContent = 'Ergebnisübersicht';
    const userP = document.createElement('p');
    userP.className = 'uk-text-center';
    userP.textContent = user;
    const contentWrap = document.createElement('div');
    contentWrap.id = 'team-results';
    contentWrap.className = 'results-modal-content';
    const closeBtn = document.createElement('button');
    closeBtn.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
    closeBtn.textContent = 'Schließen';
    dialog.append(title, userP, contentWrap, closeBtn);
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    closeBtn.addEventListener('click', () => ui.hide());

    renderResultsContent(contentWrap);
    ui.show();
  }

  if (resultsBtn) {
    resultsBtn.addEventListener('click', () => {
      if (resultsContainer) {
        renderResultsContent(resultsContainer);
        if (typeof resultsContainer.scrollIntoView === 'function') {
          resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      } else {
        showResults();
      }
    });
  }
  if (puzzleBtn && puzzleEnabled) { puzzleBtn.addEventListener('click', showPuzzle); }
  if (photoBtn && photoEnabled) { photoBtn.addEventListener('click', showPhotoModal); }

  if (resultsEnabled && autoShowResults) {
    if (resultsContainer) {
      renderResultsContent(resultsContainer);
    } else {
      showResults();
    }
  }

  if(puzzleEnabled){
    updatePuzzleInfo();
  }
}

window.initSummaryPage = initSummaryPage;
const autoInitSummary = () => initSummaryPage();
if (!window.disableSummaryAutoInit) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInitSummary);
  } else {
    autoInitSummary();
  }
}
