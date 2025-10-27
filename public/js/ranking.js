import { ResultsDataService } from './results-data-service.js';
import { formatTimestamp } from './results-utils.js';

const safeUserName = (name) => {
  if (typeof name !== 'string') return '';
  const trimmed = name.trim();
  if (trimmed === '') return '';
  const base = trimmed.replace(/[\u0000-\u001F<>]/g, '');
  if (base === '') return '';
  const normalized = typeof base.normalize === 'function' ? base.normalize('NFKC') : base;
  let unicodeSafe = normalized;
  try {
    unicodeSafe = normalized.replace(/[^\p{L}\p{N}\p{M}\p{Zs}\p{P}]/gu, '');
  } catch (e) {
    unicodeSafe = normalized;
  }
  const trimmedUnicode = unicodeSafe.trim();
  const limitedUnicode = trimmedUnicode.slice(0, 100);
  const fallback = normalized.trim().slice(0, 100);
  return limitedUnicode || fallback;
};

const normalizeEmail = (value) => {
  if (typeof value !== 'string') return '';
  const trimmed = value.trim();
  if (trimmed === '') return '';
  return trimmed;
};

const isValidEmail = (value) => {
  if (typeof value !== 'string') return false;
  const trimmed = value.trim();
  if (trimmed === '') return false;
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailPattern.test(trimmed);
};

const createPlayerNameMatcher = (name) => {
  const variants = new Set();
  let primary = '';

  const addVariant = (value) => {
    if (typeof value !== 'string') return;
    const trimmed = value.trim();
    if (!trimmed) return;
    const normalized = typeof trimmed.normalize === 'function' ? trimmed.normalize('NFKC') : trimmed;
    [trimmed, normalized].forEach((variant) => {
      if (typeof variant !== 'string' || variant === '') return;
      if (!variants.has(variant)) {
        variants.add(variant);
        if (!primary) primary = variant;
      }
    });
  };

  addVariant(name);
  addVariant(safeUserName(name));

  const matches = (candidate) => {
    if (typeof candidate !== 'string') return false;
    const trimmedCandidate = candidate.trim();
    if (trimmedCandidate && variants.has(trimmedCandidate)) {
      return true;
    }
    const normalizedCandidate = typeof trimmedCandidate.normalize === 'function'
      ? trimmedCandidate.normalize('NFKC')
      : trimmedCandidate;
    if (normalizedCandidate && variants.has(normalizedCandidate)) {
      return true;
    }
    const sanitizedCandidate = safeUserName(candidate);
    if (!sanitizedCandidate) return false;
    const sanitizedTrimmed = sanitizedCandidate.trim();
    if (sanitizedTrimmed && variants.has(sanitizedTrimmed)) {
      return true;
    }
    const sanitizedNormalized = typeof sanitizedTrimmed.normalize === 'function'
      ? sanitizedTrimmed.normalize('NFKC')
      : sanitizedTrimmed;
    return Boolean(sanitizedNormalized && variants.has(sanitizedNormalized));
  };

  return { primary, hasMatch: variants.size > 0, matches };
};

const parseIntOr = (value, fallback = 0) => {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return Math.trunc(value);
  }
  if (typeof value === 'boolean') {
    return value ? 1 : 0;
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (trimmed === '') return fallback;
    const parsed = Number.parseInt(trimmed, 10);
    if (!Number.isNaN(parsed)) {
      return parsed;
    }
    const numeric = Number(trimmed);
    if (Number.isFinite(numeric)) {
      return Math.trunc(numeric);
    }
  }
  return fallback;
};

const parseOptionalInt = (value) => {
  if (value === null || value === undefined) return null;
  if (typeof value === 'string' && value.trim() === '') return null;
  const numeric = Number(value);
  if (Number.isFinite(numeric)) {
    return Math.trunc(numeric);
  }
  return null;
};

const parseOptionalFloat = (value) => {
  if (value === null || value === undefined) return null;
  if (typeof value === 'string' && value.trim() === '') return null;
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
};

const isTruthyFlag = (value) => {
  if (value === null || value === undefined) return false;
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value !== 0;
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
  }
  return false;
};

const formatEfficiencyPercent = (value) => {
  if (!Number.isFinite(value)) return '';
  const percent = Math.round(value * 1000) / 10;
  const str = Number.isFinite(percent) ? percent.toString() : '0';
  return `${str.replace('.', ',')} %`;
};

const computePlayerRankingDetails = (rows, questionRows, catalogCount, matcher) => {
  const nameMatcher = matcher && typeof matcher.matches === 'function'
    ? matcher
    : createPlayerNameMatcher(typeof matcher === 'string' ? matcher : '');
  if (!nameMatcher.hasMatch) {
    return null;
  }
  const safeRows = Array.isArray(rows) ? rows : [];
  const safeQuestionRows = Array.isArray(questionRows) ? questionRows : [];

  const attemptMetrics = new Map();
  safeQuestionRows.forEach((row) => {
    if (!row) return;
    const team = typeof row.name === 'string' ? row.name : '';
    const catalogRaw = row.catalog ?? '';
    const catalog = catalogRaw !== null && catalogRaw !== undefined ? String(catalogRaw) : '';
    if (!team || !catalog) return;
    const attempt = parseIntOr(row.attempt, 1);
    const key = `${team}|${catalog}|${attempt}`;
    const pointsVal = parseIntOr(row.final_points ?? row.finalPoints ?? row.points ?? row.correct, 0);
    const efficiencyVal = parseOptionalFloat(row.efficiency);
    const efficiency = efficiencyVal !== null
      ? Math.max(0, Math.min(efficiencyVal, 1))
      : (parseIntOr(row.correct, 0) === 1 ? 1 : 0);
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

  safeRows.forEach((row) => {
    if (!row) return;
    const team = typeof row.name === 'string' ? row.name : '';
    const catalogRaw = row.catalog ?? '';
    const catalog = catalogRaw !== null && catalogRaw !== undefined ? String(catalogRaw) : '';
    if (!team || !catalog) return;
    catalogs.add(catalog);
    const attempt = parseIntOr(row.attempt, 1);
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
    if (puzzleTime !== null) {
      const prev = puzzleTimes.get(team);
      if (prev === undefined || puzzleTime < prev) {
        puzzleTimes.set(team, puzzleTime);
      }
    }

    const timeVal = parseOptionalInt(row.time);
    if (timeVal !== null) {
      let map = catalogTimes.get(team);
      if (!map) {
        map = new Map();
        catalogTimes.set(team, map);
      }
      const prevTime = map.get(catalog);
      if (prevTime === undefined || timeVal < prevTime) {
        map.set(catalog, timeVal);
      }
    }

    let scoreMap = scorePoints.get(team);
    if (!scoreMap) {
      scoreMap = new Map();
      scorePoints.set(team, scoreMap);
    }
    const prevScore = scoreMap.get(catalog);
    if (!prevScore || finalPoints > prevScore.points || (finalPoints === prevScore.points && average > prevScore.avg)) {
      scoreMap.set(catalog, {
        points: finalPoints,
        effSum,
        count: questionCount,
        avg: average,
      });
    }
  });

  const totalCatalogs = catalogCount > 0 ? catalogCount : catalogs.size;

  const puzzleList = Array.from(puzzleTimes.entries())
    .map(([name, time]) => ({ name, time }))
    .sort((a, b) => a.time - b.time);
  const puzzleIndex = puzzleList.findIndex((entry) => nameMatcher.matches(entry.name));
  const puzzlePlace = puzzleIndex >= 0 ? puzzleIndex + 1 : null;
  const puzzleValue = puzzleIndex >= 0 ? puzzleList[puzzleIndex].time : null;

  const finisherList = [];
  catalogTimes.forEach((map, name) => {
    if (totalCatalogs > 0 && map.size === totalCatalogs) {
      let last = -Infinity;
      map.forEach((val) => {
        if (typeof val === 'number' && Number.isFinite(val) && val > last) {
          last = val;
        }
      });
      if (Number.isFinite(last)) {
        finisherList.push({ name, time: last });
      }
    }
  });
  finisherList.sort((a, b) => a.time - b.time);
  const catalogIndex = finisherList.findIndex((entry) => nameMatcher.matches(entry.name));
  const catalogPlace = catalogIndex >= 0 ? catalogIndex + 1 : null;
  const catalogValue = catalogIndex >= 0 ? finisherList[catalogIndex].time : null;

  const scoreList = [];
  scorePoints.forEach((map, name) => {
    let total = 0;
    let effSumTotal = 0;
    let questionCountTotal = 0;
    map.forEach((entry) => {
      total += Number.isFinite(entry.points) ? entry.points : 0;
      effSumTotal += Number.isFinite(entry.effSum) ? entry.effSum : 0;
      questionCountTotal += Number.isFinite(entry.count) ? entry.count : 0;
    });
    const avg = questionCountTotal > 0 ? Math.max(0, Math.min(effSumTotal / questionCountTotal, 1)) : 0;
    scoreList.push({ name, points: total, avg });
  });
  scoreList.sort((a, b) => {
    if (b.points !== a.points) {
      return b.points - a.points;
    }
    return (b.avg ?? 0) - (a.avg ?? 0);
  });
  const pointsIndex = scoreList.findIndex((entry) => nameMatcher.matches(entry.name));
  const pointsPlace = pointsIndex >= 0 ? pointsIndex + 1 : null;
  const pointsValue = pointsIndex >= 0 ? scoreList[pointsIndex].points : null;
  const pointsAvg = pointsIndex >= 0 ? scoreList[pointsIndex].avg : null;

  return {
    points: { place: pointsPlace, total: scoreList.length, value: pointsValue, avg: pointsAvg },
    catalog: { place: catalogPlace, total: finisherList.length, value: catalogValue },
    puzzle: { place: puzzlePlace, total: puzzleList.length, value: puzzleValue },
    lists: {
      points: scoreList,
      catalog: finisherList,
      puzzle: puzzleList,
    },
  };
};

const formatListTime = (timestamp) => {
  if (!Number.isFinite(timestamp)) return '';
  return formatTimestamp(timestamp);
};

const formatUpdatedAt = (date) => {
  const pad = (num) => String(num).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const createCard = ({ title, icon, placeText, metaText, highlight = false, label = '', headingId = '' }) => {
  const col = document.createElement('div');
  const card = document.createElement('div');
  card.className = 'uk-card uk-card-default uk-card-body player-ranking-card';
  if (highlight) {
    card.classList.add('player-ranking-card--highlight');
  }

  const heading = document.createElement('h3');
  heading.className = 'player-ranking-card__title';
  if (headingId) {
    heading.id = headingId;
  }
  heading.textContent = title;
  card.appendChild(heading);

  if (label) {
    const labelEl = document.createElement('p');
    labelEl.className = 'player-ranking-card__label';
    labelEl.textContent = label;
    card.appendChild(labelEl);
  }

  const iconWrap = document.createElement('div');
  iconWrap.className = 'player-ranking-card__icon';
  if (icon) {
    const span = document.createElement('span');
    span.setAttribute('uk-icon', `icon: ${icon}; ratio: 1.6`);
    iconWrap.appendChild(span);
  }
  card.appendChild(iconWrap);

  const place = document.createElement('p');
  place.className = 'player-ranking-card__place';
  place.textContent = placeText;
  card.appendChild(place);
  if (metaText) {
    const meta = document.createElement('p');
    meta.className = 'player-ranking-card__meta';
    meta.textContent = metaText;
    card.appendChild(meta);
  }
  col.appendChild(card);
  return col;
};

const renderTopList = ({ title, icon, items, matcher, formatter }) => {
  const col = document.createElement('div');
  const card = document.createElement('div');
  card.className = 'uk-card uk-card-default uk-card-body player-toplist-card';
  const header = document.createElement('div');
  header.className = 'player-toplist-card__header';
  if (icon) {
    const span = document.createElement('span');
    span.setAttribute('uk-icon', `icon: ${icon}; ratio: 1.2`);
    header.appendChild(span);
  }
  const titleEl = document.createElement('h4');
  titleEl.textContent = title;
  header.appendChild(titleEl);
  card.appendChild(header);
  const list = document.createElement('ol');
  list.className = 'player-toplist-card__list';
  if (!items.length) {
    const empty = document.createElement('li');
    empty.textContent = 'Noch keine Einträge';
    list.appendChild(empty);
  } else {
    items.forEach((entry, idx) => {
      const li = document.createElement('li');
      li.className = 'player-toplist-card__item';
      const name = entry.name || 'Unbekannt';
      const value = formatter(entry);
      const rankSpan = document.createElement('span');
      rankSpan.className = 'player-toplist-card__rank';
      rankSpan.textContent = `${idx + 1}.`;
      const nameSpan = document.createElement('span');
      nameSpan.className = 'player-toplist-card__name';
      nameSpan.textContent = name;
      li.append(rankSpan, nameSpan);
      if (value) {
        const meta = document.createElement('span');
        meta.className = 'player-toplist-card__value';
        meta.textContent = value;
        li.appendChild(meta);
      }
      if (matcher && matcher.matches(name)) {
        li.classList.add('is-active');
      }
      list.appendChild(li);
    });
  }
  card.appendChild(list);
  col.appendChild(card);
  return col;
};

document.addEventListener('DOMContentLoaded', () => {
  const cfg = window.quizConfig || {};
  const params = new URLSearchParams(window.location.search);
  const urlEventUid = params.get('event') || params.get('event_uid') || '';
  const configEventUid = cfg.event_uid || '';
  const eventUid = urlEventUid || configEventUid;
  const urlPlayerUid = params.get('uid') || params.get('player_uid') || '';
  const contactToken = params.get('contact_token') || '';

  const basePath = window.basePath || '';
  const dataService = new ResultsDataService({ basePath, eventUid });

  let nameEl = document.getElementById('rankingPlayerName');
  const statusEl = document.getElementById('rankingStatus');
  const warningEl = document.getElementById('rankingWarning');
  const highlightsContainer = document.getElementById('rankingHighlights');
  const cardsContainer = document.getElementById('rankingCards');
  const emptyEl = document.getElementById('rankingEmpty');
  const updatedEl = document.getElementById('rankingUpdatedAt');
  const topLists = document.getElementById('rankingTopLists');
  const nameHint = document.getElementById('rankingNameHint');
  const refreshBtn = document.getElementById('rankingRefreshBtn');
  const changeNameBtn = document.getElementById('rankingChangeNameBtn');
  const contactForm = document.getElementById('rankingContactForm');
  const emailInput = document.getElementById('rankingEmail');
  const consentCheckbox = document.getElementById('rankingConsent');
  const formMessage = document.getElementById('rankingFormMessage');

  const clearStoredValue = (key) => {
    if (typeof clearStored === 'function' && typeof STORAGE_KEYS === 'object') {
      clearStored(key);
    }
  };

  const getStoredName = () => {
    if (typeof getStored === 'function' && typeof STORAGE_KEYS === 'object') {
      const storedValue = getStored(STORAGE_KEYS.PLAYER_NAME);
      if (typeof storedValue === 'string') {
        return storedValue;
      }
    }
    return '';
  };

  const setStoredName = (value) => {
    if (typeof setStored === 'function' && typeof STORAGE_KEYS === 'object') {
      setStored(STORAGE_KEYS.PLAYER_NAME, value);
    }
  };

  const getStoredEmail = () => {
    if (typeof getStored === 'function' && typeof STORAGE_KEYS === 'object') {
      const storedValue = getStored(STORAGE_KEYS.PLAYER_EMAIL);
      if (typeof storedValue === 'string') {
        return normalizeEmail(storedValue);
      }
    }
    return '';
  };

  const setStoredEmail = (value) => {
    if (typeof setStored === 'function' && typeof STORAGE_KEYS === 'object') {
      setStored(STORAGE_KEYS.PLAYER_EMAIL, value);
    }
  };

  const clearStoredEmail = () => {
    clearStoredValue(STORAGE_KEYS.PLAYER_EMAIL);
  };

  const getStoredConsent = () => {
    if (typeof getStored === 'function' && typeof STORAGE_KEYS === 'object') {
      const storedValue = getStored(STORAGE_KEYS.PLAYER_EMAIL_CONSENT);
      if (typeof storedValue === 'string') {
        const normalized = storedValue.trim().toLowerCase();
        return normalized === '1' || normalized === 'true' || normalized === 'yes';
      }
    }
    return false;
  };

  const setStoredConsent = (value) => {
    if (typeof setStored === 'function' && typeof STORAGE_KEYS === 'object') {
      setStored(STORAGE_KEYS.PLAYER_EMAIL_CONSENT, value ? '1' : '0');
    }
  };

  const clearStoredConsent = () => {
    clearStoredValue(STORAGE_KEYS.PLAYER_EMAIL_CONSENT);
  };

  let memoizedPlayerUid = '';

  const getStoredPlayerUid = () => {
    if (memoizedPlayerUid) {
      return memoizedPlayerUid;
    }
    if (typeof getStored === 'function' && typeof STORAGE_KEYS === 'object') {
      const storedValue = getStored(STORAGE_KEYS.PLAYER_UID);
      if (typeof storedValue === 'string') {
        const trimmed = storedValue.trim();
        if (trimmed !== '') {
          memoizedPlayerUid = trimmed;
          return memoizedPlayerUid;
        }
      }
    }
    return '';
  };

  const setStoredPlayerUid = (value) => {
    if (typeof value !== 'string') {
      return;
    }
    const trimmed = value.trim();
    if (!trimmed) {
      return;
    }
    memoizedPlayerUid = trimmed;
    if (typeof setStored === 'function' && typeof STORAGE_KEYS === 'object') {
      setStored(STORAGE_KEYS.PLAYER_UID, trimmed);
    }
  };

  const generatePlayerUid = () => {
    let cryptoSource = null;
    if (typeof self !== 'undefined' && self && typeof self.crypto !== 'undefined') {
      cryptoSource = self.crypto;
    } else if (typeof globalThis !== 'undefined' && globalThis && typeof globalThis.crypto !== 'undefined') {
      cryptoSource = globalThis.crypto;
    }
    if (cryptoSource && typeof cryptoSource.randomUUID === 'function') {
      try {
        const uid = cryptoSource.randomUUID();
        if (typeof uid === 'string' && uid) {
          return uid;
        }
      } catch (error) {
        console.error('Failed to generate UUID via crypto.randomUUID', error);
      }
    }
    return `player-${Math.random().toString(36).slice(2, 11)}`;
  };

  const ensurePlayerUid = () => {
    const existing = getStoredPlayerUid();
    if (existing) {
      return existing;
    }
    if (urlPlayerUid) {
      setStoredPlayerUid(urlPlayerUid);
      return urlPlayerUid;
    }
    const generated = generatePlayerUid();
    setStoredPlayerUid(generated);
    return generated;
  };

  if (urlPlayerUid) {
    setStoredPlayerUid(urlPlayerUid);
  } else {
    memoizedPlayerUid = getStoredPlayerUid();
  }

  const setFormMessage = (text, variant = 'info') => {
    if (!formMessage) return;
    formMessage.textContent = text;
    formMessage.hidden = !text;
    formMessage.classList.remove('uk-text-danger', 'uk-text-success', 'uk-text-meta');
    if (!text) {
      formMessage.classList.add('uk-text-meta');
      return;
    }
    if (variant === 'error') {
      formMessage.classList.add('uk-text-danger');
    } else if (variant === 'success') {
      formMessage.classList.add('uk-text-success');
    } else {
      formMessage.classList.add('uk-text-meta');
    }
  };

  const resetFormMessage = () => {
    setFormMessage('', 'info');
  };

  const sendJson = async (endpoint, payload, method = 'POST') => {
    const options = {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    };

    return fetch(endpoint, options);
  };

  const removeQueryParameter = (key) => {
    try {
      const url = new URL(window.location.href);
      if (!url.searchParams.has(key)) {
        return;
      }
      url.searchParams.delete(key);
      const next = `${url.pathname}${url.search}${url.hash}`;
      window.history.replaceState({}, document.title, next);
    } catch (error) {
      console.error('Failed to clean query parameter', error);
    }
  };

  let currentName = getStoredName();
  let currentEmail = getStoredEmail();
  let hasEmailConsent = getStoredConsent();
  let pendingEmail = '';

  if (!hasEmailConsent) {
    currentEmail = '';
  }

  const updateContactForm = () => {
    if (emailInput) {
      const displayEmail = pendingEmail || currentEmail || '';
      emailInput.value = displayEmail;
      emailInput.classList.toggle('uk-form-success', Boolean(hasEmailConsent && currentEmail));
    }
    if (consentCheckbox) {
      consentCheckbox.checked = Boolean(hasEmailConsent && currentEmail);
      consentCheckbox.disabled = Boolean(pendingEmail);
    }
  };

  const updateNameDisplay = () => {
    const safeName = safeUserName(currentName) || currentName.trim();
    if (!nameEl || !nameEl.isConnected) {
      nameEl = document.getElementById('rankingPlayerName');
    }
    if (nameEl) {
      nameEl.classList.add('player-name-highlight');
      nameEl.textContent = safeName || 'Unbekannt';
    }
    if (nameHint) {
      if (safeName) {
        nameHint.textContent = 'Der Name stammt aus deinem letzten Quizdurchlauf. Du kannst ihn jederzeit anpassen.';
      } else {
        nameHint.textContent = 'Wir konnten keinen gespeicherten Namen finden. Bitte gib deinen Namen ein, um dein Ranking zu sehen.';
      }
    }
  };

  updateNameDisplay();
  updateContactForm();

  if (emailInput) {
    emailInput.addEventListener('input', resetFormMessage);
  }

  if (consentCheckbox) {
    consentCheckbox.addEventListener('change', resetFormMessage);
  }

  const shouldSyncFromServer = () => {
    if (!eventUid || !urlPlayerUid) {
      return false;
    }
    const sanitizedName = safeUserName(currentName) || (typeof currentName === 'string' ? currentName.trim() : '');
    if (!sanitizedName) {
      return true;
    }
    if (hasEmailConsent && !currentEmail) {
      return true;
    }
    return false;
  };

  const syncPlayerFromServer = async () => {
    if (!shouldSyncFromServer()) {
      return false;
    }

    const query = new URLSearchParams({
      event_uid: eventUid,
      player_uid: urlPlayerUid,
    });

    try {
      const response = await fetch(`/api/players?${query.toString()}`);
      if (!response.ok) {
        return false;
      }
      const payload = await response.json();
      let updated = false;

      if (payload && typeof payload.player_name === 'string') {
        const sanitized = safeUserName(payload.player_name) || payload.player_name.trim();
        if (sanitized) {
          const previous = safeUserName(currentName) || (typeof currentName === 'string' ? currentName.trim() : '');
          if (sanitized !== previous) {
            currentName = sanitized;
            setStoredName(sanitized);
            if (typeof setStored === 'function') {
              try {
                setStored('quizUser', sanitized);
              } catch (error) {
                console.error('Failed to persist quizUser name', error);
              }
            }
            updateNameDisplay();
            updated = true;
          }
        }
      }

      if (payload && typeof payload.contact_email === 'string') {
        const normalizedEmail = normalizeEmail(payload.contact_email);
        const consentGranted = Boolean(payload.consent_granted_at);
        if (normalizedEmail && consentGranted) {
          const previousEmail = normalizeEmail(currentEmail);
          if (normalizedEmail !== previousEmail || !hasEmailConsent) {
            currentEmail = normalizedEmail;
            hasEmailConsent = true;
            setStoredEmail(normalizedEmail);
            setStoredConsent(true);
            updateContactForm();
            updated = true;
          }
        }
      }

      return updated;
    } catch (error) {
      console.error('Failed to load player profile for ranking', error);
      return false;
    }
  };

  const clearStatus = () => {
    if (statusEl) {
      statusEl.hidden = true;
      statusEl.textContent = '';
    }
    if (warningEl) {
      warningEl.hidden = true;
      warningEl.textContent = '';
    }
  };

  const showStatus = (text) => {
    if (!statusEl) return;
    statusEl.hidden = false;
    statusEl.textContent = text;
  };

  const showWarning = (text) => {
    if (!warningEl) return;
    warningEl.hidden = false;
    warningEl.textContent = text;
  };

  const renderRanking = (payload) => {
    if (!cardsContainer || !topLists) return;
    if (highlightsContainer) {
      highlightsContainer.innerHTML = '';
    }
    cardsContainer.innerHTML = '';
    topLists.innerHTML = '';
    if (emptyEl) emptyEl.hidden = true;

    const storedName = safeUserName(currentName) || currentName.trim();
    if (!storedName) {
      if (emptyEl) emptyEl.hidden = false;
      showWarning('Bitte gib einen Spielernamen ein, um dein Ranking zu laden.');
      return;
    }

    const matcher = createPlayerNameMatcher(storedName);
    if (!matcher.hasMatch) {
      if (emptyEl) emptyEl.hidden = false;
      showWarning('Der eingegebene Name konnte nicht verarbeitet werden.');
      return;
    }

    const rankingInfo = computePlayerRankingDetails(
      payload.rows,
      payload.questionRows,
      payload.catalogCount,
      matcher
    );

    if (!rankingInfo) {
      if (emptyEl) emptyEl.hidden = false;
      showWarning('Es sind noch keine Ergebnisse vorhanden.');
      return;
    }

    const puzzleEnabled = isTruthyFlag(cfg.puzzleWordEnabled ?? cfg.puzzle_word_enabled);
    const hasMultipleCatalogs = Number.isFinite(payload.catalogCount) ? payload.catalogCount > 1 : false;

    const cards = [];
    const displayName = storedName || 'Unbekannt';

    const formatPlace = (info, fallback) => {
      if (!info) return fallback;
      if (info.total === 0) return fallback;
      if (typeof info.place === 'number' && Number.isFinite(info.place)) {
        return `Platz ${info.place} von ${info.total}`;
      }
      return `Aktuell nicht platziert (von ${info.total})`;
    };

    const pointsMetaParts = [];
    if (Number.isFinite(rankingInfo.points.value)) {
      pointsMetaParts.push(`Punkte: ${rankingInfo.points.value}`);
    }
    if (Number.isFinite(rankingInfo.points.avg)) {
      pointsMetaParts.push(`Ø ${formatEfficiencyPercent(rankingInfo.points.avg)}`);
    }
    const highscoreCard = createCard({
      title: displayName,
      icon: 'trophy',
      label: 'Highscore',
      headingId: 'rankingPlayerName',
      placeText: formatPlace(rankingInfo.points, 'Noch keine Punktewertung'),
      metaText: pointsMetaParts.join(' · '),
      highlight: rankingInfo.points.place === 1,
    });

    const highscoreHeading = highscoreCard.querySelector('.player-ranking-card__title');
    if (highscoreHeading) {
      highscoreHeading.classList.add('player-name-highlight');
      nameEl = highscoreHeading;
    }

    if (hasMultipleCatalogs) {
      cards.push(createCard({
        title: 'Katalogmeister',
        icon: 'clock',
        placeText: formatPlace(rankingInfo.catalog, 'Noch nicht alle Kataloge abgeschlossen'),
        metaText: Number.isFinite(rankingInfo.catalog.value)
          ? `Abschluss: ${formatListTime(rankingInfo.catalog.value)}`
          : '',
        highlight: rankingInfo.catalog.place === 1,
      }));
    }

    if (puzzleEnabled) {
      cards.push(createCard({
        title: 'Rätselwort',
        icon: 'bolt',
        placeText: formatPlace(rankingInfo.puzzle, 'Noch kein Rätsel gelöst'),
        metaText: Number.isFinite(rankingInfo.puzzle.value)
          ? `Zeit: ${formatListTime(rankingInfo.puzzle.value)}`
          : '',
        highlight: rankingInfo.puzzle.place === 1,
      }));
    }

    if (highlightsContainer) {
      highlightsContainer.appendChild(highscoreCard);
    } else {
      cardsContainer.appendChild(highscoreCard);
    }

    updateNameDisplay();

    cards.forEach((card) => cardsContainer.appendChild(card));

    if (
      cardsContainer.children.length === 0 &&
      (!highlightsContainer || highlightsContainer.children.length === 0) &&
      emptyEl
    ) {
      emptyEl.hidden = false;
    }

    const topPoints = (rankingInfo.lists.points || []).slice(0, 5);
    const topCatalog = (rankingInfo.lists.catalog || []).slice(0, 5);
    const topPuzzle = (rankingInfo.lists.puzzle || []).slice(0, 5);

    const topPointsConfig = {
      title: 'Top Punkte',
      icon: 'trophy',
      items: topPoints.map((entry) => ({
        name: entry.name,
        value: entry.points,
        avg: entry.avg,
      })),
      formatter: (entry) => {
        const parts = [];
        if (Number.isFinite(entry.value)) {
          parts.push(`${entry.value} Punkte`);
        }
        if (Number.isFinite(entry.avg)) {
          parts.push(`Ø ${formatEfficiencyPercent(entry.avg)}`);
        }
        return parts.join(' · ');
      },
    };

    const listsToRender = [];

    if (hasMultipleCatalogs) {
      listsToRender.push({
        title: 'Schnellste Teams',
        icon: 'clock',
        items: topCatalog,
        formatter: (entry) => formatListTime(entry.time),
      });
    }

    if (puzzleEnabled) {
      listsToRender.push({
        title: 'Rätsel-Topliste',
        icon: 'bolt',
        items: topPuzzle,
        formatter: (entry) => formatListTime(entry.time),
      });
    }

    const appendTopList = (config, target) => {
      if (!target) {
        return;
      }
      const normalizedItems = (config.items || []).map((item) => ({
        name: item.name,
        time: item.time,
        value: item.value,
        avg: item.avg,
      }));
      const listNode = renderTopList({
        title: config.title,
        icon: config.icon,
        items: normalizedItems,
        matcher,
        formatter: (entry) => {
          if (config.formatter) {
            return config.formatter(entry);
          }
          if (Number.isFinite(entry.value)) {
            return `${entry.value}`;
          }
          return '';
        },
      });
      target.appendChild(listNode);
    };

    if (highlightsContainer) {
      appendTopList(topPointsConfig, highlightsContainer);
    } else {
      appendTopList(topPointsConfig, topLists);
    }

    listsToRender.forEach((config) => {
      appendTopList(config, topLists);
    });

    if (updatedEl) {
      const now = new Date();
      updatedEl.textContent = `Zuletzt aktualisiert: ${formatUpdatedAt(now)}`;
    }
  };

  const refresh = () => {
    clearStatus();
    const sanitizedName = safeUserName(currentName) || currentName.trim();
    if (!sanitizedName) {
      if (cardsContainer) {
        cardsContainer.innerHTML = '';
      }
      if (highlightsContainer) {
        highlightsContainer.innerHTML = '';
      }
      if (topLists) {
        topLists.innerHTML = '';
      }
      if (updatedEl) {
        updatedEl.textContent = '';
      }
      if (emptyEl) {
        emptyEl.hidden = false;
      }
      showWarning('Bitte gib einen Spielernamen ein, um dein Ranking zu laden.');
      return;
    }
    const emailForSync = hasEmailConsent && currentEmail ? currentEmail : '';
    showStatus('Aktualisiere Ranking …');
    dataService.setEventUid(eventUid);
    dataService.setPlayerEmail(emailForSync);
    dataService.load()
      .then((payload) => {
        clearStatus();
        renderRanking(payload);
      })
      .catch(() => {
        clearStatus();
        showWarning('Beim Laden der Ergebnisse ist ein Fehler aufgetreten. Bitte versuche es erneut.');
      });
  };

  const confirmContactOptIn = async (token) => {
    if (!token) {
      return;
    }

    setFormMessage('Wir bestätigen gerade deine E-Mail-Adresse …', 'info');

    try {
      const response = await sendJson('/api/player-contact/confirm', { token });
      if (response.ok) {
        const payload = await response.json();
        const normalizedEmail = normalizeEmail(payload.contact_email);
        if (normalizedEmail) {
          currentEmail = normalizedEmail;
          pendingEmail = '';
          hasEmailConsent = true;
          setStoredEmail(normalizedEmail);
          setStoredConsent(true);
          updateContactForm();
        }
        if (payload && typeof payload.player_name === 'string') {
          const sanitized = safeUserName(payload.player_name) || payload.player_name.trim();
          if (sanitized && sanitized !== (safeUserName(currentName) || currentName.trim())) {
            currentName = sanitized;
            setStoredName(sanitized);
            updateNameDisplay();
          }
        }
        setFormMessage('Danke! Deine E-Mail-Adresse wurde bestätigt.', 'success');
        refresh();
      } else if (response.status === 410) {
        pendingEmail = '';
        updateContactForm();
        setFormMessage('Der Bestätigungslink ist abgelaufen. Bitte fordere eine neue E-Mail an.', 'error');
      } else if (response.status === 404) {
        pendingEmail = '';
        updateContactForm();
        setFormMessage('Der Bestätigungslink konnte nicht gefunden werden. Bitte fordere eine neue E-Mail an.', 'error');
      } else if (response.status === 409) {
        pendingEmail = '';
        updateContactForm();
        setFormMessage('Diese E-Mail-Adresse wurde bereits bestätigt.', 'info');
      } else {
        pendingEmail = '';
        updateContactForm();
        setFormMessage('Die Bestätigung konnte nicht abgeschlossen werden. Bitte fordere eine neue E-Mail an.', 'error');
      }
    } catch (error) {
      console.error('Failed to confirm player contact', error);
      pendingEmail = '';
      updateContactForm();
      setFormMessage('Die Bestätigung konnte nicht abgeschlossen werden. Bitte versuche es später erneut.', 'error');
    } finally {
      removeQueryParameter('contact_token');
    }
  };

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      refresh();
    });
  }

  if (changeNameBtn) {
    changeNameBtn.addEventListener('click', async () => {
      const previousName = typeof currentName === 'string' ? currentName : '';
      const newName = window.prompt('Wie lautet dein Name für das Ranking?', previousName);
      if (newName === null) {
        return;
      }

      const sanitizedName = safeUserName(newName) || (typeof newName === 'string' ? newName.trim() : '');
      const previousSanitized = safeUserName(previousName) || previousName.trim();

      if (!sanitizedName) {
        window.alert('Bitte gib einen gültigen Namen ein.');
        return;
      }

      if (sanitizedName === previousSanitized) {
        ensurePlayerUid();
        currentName = sanitizedName;
        setStoredName(sanitizedName);
        if (typeof setStored === 'function') {
          try {
            setStored('quizUser', sanitizedName);
          } catch (error) {
            console.error('Failed to persist quizUser name', error);
          }
        }
        updateNameDisplay();
        refresh();
        return;
      }

      let serverAccepted = true;
      const playerUid = ensurePlayerUid();
      if (!eventUid) {
        console.warn('Missing event UID – cannot sync player name with server.');
      }

      if (eventUid && playerUid) {
        try {
          const response = await fetch('/api/players', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              event_uid: eventUid,
              player_name: sanitizedName,
              player_uid: playerUid,
            }),
          });
          if (!response.ok) {
            if (response.status === 409) {
              window.alert('Dieser Name wird bereits verwendet. Bitte wähle einen anderen Namen.');
            } else {
              window.alert('Der Name konnte nicht gespeichert werden. Bitte versuche es später erneut.');
            }
            serverAccepted = false;
          }
        } catch (error) {
          console.error('Failed to update player name for ranking', error);
          window.alert('Der Name konnte nicht gespeichert werden. Bitte versuche es später erneut.');
          serverAccepted = false;
        }
      }

      if (!serverAccepted) {
        currentName = previousSanitized;
        setStoredName(previousSanitized);
        if (typeof setStored === 'function') {
          try {
            setStored('quizUser', previousSanitized);
          } catch (error) {
            console.error('Failed to persist quizUser name', error);
          }
        }
        updateNameDisplay();
        refresh();
        return;
      }

      currentName = sanitizedName;
      setStoredName(sanitizedName);
      if (typeof setStored === 'function') {
        try {
          setStored('quizUser', sanitizedName);
        } catch (error) {
          console.error('Failed to persist quizUser name', error);
        }
      }
      updateNameDisplay();
      refresh();
    });
  }

  if (contactForm) {
    contactForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!emailInput || !consentCheckbox) {
        return;
      }

      resetFormMessage();

      if (!eventUid) {
        setFormMessage('Die Veranstaltung konnte nicht ermittelt werden. Bitte lade die Seite neu.', 'error');
        return;
      }

      const rawEmail = typeof emailInput.value === 'string' ? emailInput.value : '';
      const trimmedEmail = rawEmail.trim();
      const consentGiven = Boolean(consentCheckbox.checked);

      if (trimmedEmail === '' && consentGiven) {
        setFormMessage('Bitte gib eine E-Mail-Adresse an, wenn du der Kontaktaufnahme zustimmst.', 'error');
        return;
      }

      if (trimmedEmail !== '' && !isValidEmail(trimmedEmail)) {
        setFormMessage('Bitte gib eine gültige E-Mail-Adresse im Format name@example.de an.', 'error');
        return;
      }

      if (trimmedEmail !== '' && !consentGiven) {
        setFormMessage('Bitte bestätige die Einwilligung, damit wir deine E-Mail-Adresse speichern dürfen.', 'error');
        return;
      }

      const playerUid = ensurePlayerUid();
      if (!playerUid) {
        setFormMessage('Dein Spieler-Profil konnte nicht angelegt werden. Bitte versuche es erneut.', 'error');
        return;
      }

      if (trimmedEmail === '') {
        const hadData = Boolean(currentEmail || hasEmailConsent || pendingEmail);
        pendingEmail = '';
        try {
          const response = await sendJson('/api/player-contact', {
            event_uid: eventUid,
            player_uid: playerUid,
          }, 'DELETE');

          if (response.status === 204) {
            currentEmail = '';
            hasEmailConsent = false;
            clearStoredEmail();
            clearStoredConsent();
            updateContactForm();
            setFormMessage(hadData ? 'Wir haben deine Kontaktdaten entfernt.' : 'Es sind keine Kontaktdaten gespeichert.', hadData ? 'success' : 'info');
            refresh();
          } else if (response.status === 404) {
            currentEmail = '';
            hasEmailConsent = false;
            clearStoredEmail();
            clearStoredConsent();
            updateContactForm();
            setFormMessage('Es sind keine Kontaktdaten gespeichert.', 'info');
          } else {
            setFormMessage('Die Kontaktdaten konnten nicht entfernt werden. Bitte versuche es später erneut.', 'error');
          }
        } catch (error) {
          console.error('Failed to remove player contact', error);
          setFormMessage('Die Kontaktdaten konnten nicht entfernt werden. Bitte versuche es später erneut.', 'error');
        }
        return;
      }

      const normalizedEmail = normalizeEmail(trimmedEmail);
      if (!normalizedEmail) {
        setFormMessage('Bitte gib eine gültige E-Mail-Adresse im Format name@example.de an.', 'error');
        return;
      }

      pendingEmail = normalizedEmail;
      hasEmailConsent = false;
      updateContactForm();
      setFormMessage('Fast geschafft! Wir haben dir eine Bestätigungs-E-Mail geschickt. Bitte bestätige sie innerhalb von 24 Stunden.', 'info');

      try {
        const response = await sendJson('/api/player-contact', {
          event_uid: eventUid,
          player_uid: playerUid,
          player_name: currentName,
          contact_email: normalizedEmail,
        });

        if (response.status === 204) {
          setFormMessage('Wir haben dir eine Bestätigungs-E-Mail geschickt. Schau bitte auch im Spam-Ordner nach.', 'success');
        } else if (response.status === 404) {
          pendingEmail = '';
          updateContactForm();
          setFormMessage('Die Veranstaltung wurde nicht gefunden. Bitte lade die Seite neu.', 'error');
        } else if (response.status === 503) {
          pendingEmail = '';
          updateContactForm();
          setFormMessage('Aktuell können keine Bestätigungs-E-Mails versendet werden. Bitte versuche es später erneut.', 'error');
        } else if (response.status === 400) {
          pendingEmail = '';
          updateContactForm();
          setFormMessage('Die E-Mail-Adresse konnte nicht verarbeitet werden. Bitte überprüfe deine Eingabe.', 'error');
        } else {
          pendingEmail = '';
          updateContactForm();
          setFormMessage('Es gab ein Problem beim Versand der Bestätigungs-E-Mail. Bitte versuche es später erneut.', 'error');
        }
      } catch (error) {
        console.error('Failed to request player contact confirmation', error);
        pendingEmail = '';
        updateContactForm();
        setFormMessage('Es gab ein Problem beim Versand der Bestätigungs-E-Mail. Bitte versuche es später erneut.', 'error');
      }
    });
  }

  if (contactToken) {
    confirmContactOptIn(contactToken);
  }

  const initialSync = syncPlayerFromServer();
  if (initialSync && typeof initialSync.then === 'function') {
    initialSync.finally(() => {
      refresh();
    });
  } else {
    refresh();
  }
});
