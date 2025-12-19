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

const BLANK_NAME_KEY = '__blank__';

const normalizePlayerUid = (value) => {
  if (typeof value !== 'string') {
    return '';
  }
  const trimmed = value.trim();
  return trimmed === '' ? '' : trimmed;
};

const computeNameKey = (value) => {
  if (typeof value !== 'string') {
    return BLANK_NAME_KEY;
  }
  const trimmed = value.trim();
  if (trimmed === '') {
    return BLANK_NAME_KEY;
  }
  const sanitized = safeUserName(trimmed) || trimmed;
  let normalized = sanitized;
  try {
    normalized = normalized.normalize('NFKC');
  } catch (error) {
    // ignore unsupported normalization
  }
  return normalized ? normalized.toLowerCase() : BLANK_NAME_KEY;
};

const createEntryIdentity = (entry) => {
  const rawUid = entry && typeof entry === 'object'
    ? (entry.player_uid ?? entry.playerUid ?? '')
    : '';
  const playerUid = normalizePlayerUid(rawUid);
  const normalizedUid = playerUid ? playerUid.toLowerCase() : '';
  const rawName = entry && typeof entry === 'object' && typeof entry.name === 'string'
    ? entry.name
    : (typeof entry === 'string' ? entry : '');
  const displayName = safeUserName(rawName) || rawName.trim();
  const nameKey = computeNameKey(rawName);
  const key = normalizedUid ? `uid:${normalizedUid}` : `name:${nameKey}`;
  return {
    key,
    playerUid,
    normalizedUid,
    displayName: displayName || '',
    nameKey,
  };
};

const createPlayerMatcher = (input) => {
  if (input && typeof input.matches === 'function') {
    return input;
  }
  const source = typeof input === 'object' && input !== null ? input : { name: input };
  const baseName = typeof source.name === 'string' ? source.name : '';
  const baseMatcher = createPlayerNameMatcher(baseName);
  const normalizedUid = normalizePlayerUid(source.playerUid ?? source.player_uid).toLowerCase();
  const hasNameMatch = baseMatcher.hasMatch;
  const hasMatch = Boolean(normalizedUid) || hasNameMatch;
  const matches = (candidate) => {
    const identity = createEntryIdentity(candidate || '');
    if (normalizedUid) {
      if (identity.normalizedUid) {
        return identity.normalizedUid === normalizedUid;
      }
      if (!hasNameMatch) {
        return false;
      }
    }
    if (!hasNameMatch) {
      return false;
    }
    const candidateName = identity.displayName || (typeof candidate === 'string' ? candidate : '');
    return baseMatcher.matches(candidateName);
  };
  return {
    primary: normalizedUid || baseMatcher.primary,
    hasMatch,
    matches,
    normalizedUid,
  };
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
  const playerMatcher = createPlayerMatcher(matcher);
  if (!playerMatcher.hasMatch) {
    return null;
  }
  const safeRows = Array.isArray(rows) ? rows : [];
  const safeQuestionRows = Array.isArray(questionRows) ? questionRows : [];

  const attemptMetrics = new Map();
  safeQuestionRows.forEach((row) => {
    if (!row) return;
    const identity = createEntryIdentity(row);
    const catalogRaw = row.catalog ?? '';
    const catalog = catalogRaw !== null && catalogRaw !== undefined ? String(catalogRaw) : '';
    if (!catalog) return;
    if (!identity.playerUid && identity.nameKey === BLANK_NAME_KEY) return;
    const attempt = parseIntOr(row.attempt, 1);
    const key = `${identity.key}|${catalog}|${attempt}`;
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
    const identity = createEntryIdentity(row);
    const catalogRaw = row.catalog ?? '';
    const catalog = catalogRaw !== null && catalogRaw !== undefined ? String(catalogRaw) : '';
    if (!catalog) return;
    if (!identity.playerUid && identity.nameKey === BLANK_NAME_KEY) return;
    catalogs.add(catalog);
    const attempt = parseIntOr(row.attempt, 1);
    const key = `${identity.key}|${catalog}|${attempt}`;
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
      const prev = puzzleTimes.get(identity.key);
      if (!prev || !Number.isFinite(prev.time) || puzzleTime < prev.time) {
        puzzleTimes.set(identity.key, {
          name: identity.displayName,
          time: puzzleTime,
          playerUid: identity.playerUid,
        });
      } else {
        if (identity.displayName && !prev.name) {
          prev.name = identity.displayName;
        }
        if (!prev.playerUid && identity.playerUid) {
          prev.playerUid = identity.playerUid;
        }
      }
    }

    const timeVal = parseOptionalInt(row.time);
    if (timeVal !== null) {
      let info = catalogTimes.get(identity.key);
      if (!info) {
        info = { name: identity.displayName, playerUid: identity.playerUid, catalogs: new Map() };
        catalogTimes.set(identity.key, info);
      } else {
        if (identity.displayName && !info.name) {
          info.name = identity.displayName;
        }
        if (!info.playerUid && identity.playerUid) {
          info.playerUid = identity.playerUid;
        }
      }
      const prevTime = info.catalogs.get(catalog);
      if (prevTime === undefined || timeVal < prevTime) {
        info.catalogs.set(catalog, timeVal);
      }
    }

    let scoreMap = scorePoints.get(identity.key);
    if (!scoreMap) {
      scoreMap = { name: identity.displayName, playerUid: identity.playerUid, catalogs: new Map() };
      scorePoints.set(identity.key, scoreMap);
    } else {
      if (identity.displayName && !scoreMap.name) {
        scoreMap.name = identity.displayName;
      }
      if (!scoreMap.playerUid && identity.playerUid) {
        scoreMap.playerUid = identity.playerUid;
      }
    }
    const catalogMap = scoreMap.catalogs;
    const prevScore = catalogMap.get(catalog);
    if (!prevScore || finalPoints > prevScore.points || (finalPoints === prevScore.points && average > prevScore.avg)) {
      catalogMap.set(catalog, {
        points: finalPoints,
        effSum,
        count: questionCount,
        avg: average,
      });
    }
  });

  const totalCatalogs = catalogCount > 0 ? catalogCount : catalogs.size;

  const puzzleList = Array.from(puzzleTimes.values())
    .map((info) => ({
      name: info.name || '',
      time: info.time,
      playerUid: info.playerUid || '',
      player_uid: info.playerUid || '',
    }))
    .sort((a, b) => a.time - b.time);
  const puzzleIndex = puzzleList.findIndex((entry) => playerMatcher.matches(entry));
  const puzzlePlace = puzzleIndex >= 0 ? puzzleIndex + 1 : null;
  const puzzleValue = puzzleIndex >= 0 ? puzzleList[puzzleIndex].time : null;

  const finisherList = [];
  catalogTimes.forEach((info) => {
    if (!info) return;
    const map = info.catalogs || new Map();
    if (totalCatalogs > 0 && map.size === totalCatalogs) {
      let last = -Infinity;
      map.forEach((val) => {
        if (typeof val === 'number' && Number.isFinite(val) && val > last) {
          last = val;
        }
      });
      if (Number.isFinite(last)) {
        finisherList.push({
          name: info.name || '',
          time: last,
          playerUid: info.playerUid || '',
          player_uid: info.playerUid || '',
        });
      }
    }
  });
  finisherList.sort((a, b) => a.time - b.time);
  const catalogIndex = finisherList.findIndex((entry) => playerMatcher.matches(entry));
  const catalogPlace = catalogIndex >= 0 ? catalogIndex + 1 : null;
  const catalogValue = catalogIndex >= 0 ? finisherList[catalogIndex].time : null;

  const scoreList = [];
  scorePoints.forEach((info) => {
    if (!info) return;
    const map = info.catalogs || new Map();
    let total = 0;
    let effSumTotal = 0;
    let questionCountTotal = 0;
    map.forEach((entry) => {
      total += Number.isFinite(entry.points) ? entry.points : 0;
      effSumTotal += Number.isFinite(entry.effSum) ? entry.effSum : 0;
      questionCountTotal += Number.isFinite(entry.count) ? entry.count : 0;
    });
    const avg = questionCountTotal > 0 ? Math.max(0, Math.min(effSumTotal / questionCountTotal, 1)) : 0;
    scoreList.push({
      name: info.name || '',
      points: total,
      avg,
      playerUid: info.playerUid || '',
      player_uid: info.playerUid || '',
    });
  });
  scoreList.sort((a, b) => {
    if (b.points !== a.points) {
      return b.points - a.points;
    }
    return (b.avg ?? 0) - (a.avg ?? 0);
  });
  const pointsIndex = scoreList.findIndex((entry) => playerMatcher.matches(entry));
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

const createCard = ({
  title,
  icon,
  placeText,
  metaText,
  highlight = false,
  label = '',
  headingId = '',
  actions = null,
}) => {
  const col = document.createElement('div');
  const card = document.createElement('div');
  card.className = 'uk-card uk-card-default uk-card-body player-ranking-card';
  if (highlight) {
    card.classList.add('player-ranking-card--highlight');
  }
  const header = document.createElement('div');
  header.className = 'player-ranking-card__header';
  const headingGroup = document.createElement('div');
  headingGroup.className = 'player-ranking-card__heading-group';
  const heading = document.createElement('h3');
  heading.className = 'player-ranking-card__title';
  if (headingId) {
    heading.id = headingId;
  }
  heading.textContent = title;
  if (icon) {
    const iconWrap = document.createElement('span');
    iconWrap.className = 'player-ranking-card__icon';
    iconWrap.setAttribute('uk-icon', `icon: ${icon}; ratio: 1.4`);
    headingGroup.appendChild(iconWrap);
  }
  headingGroup.appendChild(heading);
  header.appendChild(headingGroup);
  if (actions) {
    const isFragment = typeof DocumentFragment !== 'undefined' && actions instanceof DocumentFragment;
    if (actions instanceof HTMLElement) {
      actions.classList.add('player-ranking-card__actions');
      actions.hidden = false;
      header.appendChild(actions);
    } else if (isFragment) {
      const wrapper = document.createElement('div');
      wrapper.className = 'player-ranking-card__actions';
      wrapper.appendChild(actions);
      header.appendChild(wrapper);
    }
  }
  card.appendChild(header);
  if (label) {
    const labelEl = document.createElement('p');
    labelEl.className = 'player-ranking-card__label';
    labelEl.textContent = label;
    card.appendChild(labelEl);
  }
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
      if (matcher && matcher.matches(entry)) {
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
  const eventUid = window.getActiveEventId ? window.getActiveEventId() : '';
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
  const nameActions = document.getElementById('rankingNameActions');
  const nameModalEl = document.getElementById('rankingNameModal');
  const nameForm = document.getElementById('rankingNameForm');
  const nameInput = document.getElementById('rankingNameInput');
  const refreshBtn = document.getElementById('rankingRefreshBtn');
  const changeNameBtn = document.getElementById('rankingChangeNameBtn');
  const contactForm = document.getElementById('rankingContactForm');
  const emailInput = document.getElementById('rankingEmail');
  const consentCheckbox = document.getElementById('rankingConsent');
  const formMessage = document.getElementById('rankingFormMessage');

  let nameModalInstance = null;

  const getNameModal = () => {
    if (!nameModalEl) {
      return null;
    }
    if (nameModalInstance) {
      return nameModalInstance;
    }
    if (typeof UIkit === 'undefined' || typeof UIkit.modal !== 'function') {
      return null;
    }
    nameModalInstance = UIkit.modal(nameModalEl);
    return nameModalInstance;
  };

  const focusNameInput = () => {
    if (!nameInput) {
      return;
    }
    requestAnimationFrame(() => {
      nameInput.focus();
      nameInput.select();
    });
  };

  if (nameModalEl && nameInput) {
    nameModalEl.addEventListener('shown', () => {
      focusNameInput();
    });
  }

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

  const persistNameLocally = (value) => {
    const normalized = typeof value === 'string' ? value : '';
    currentName = normalized;
    setStoredName(normalized);
    if (typeof setStored === 'function') {
      try {
        setStored('quizUser', normalized);
      } catch (error) {
        console.error('Failed to persist quizUser name', error);
      }
    }
  };

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
    const rawName = typeof currentName === 'string' ? currentName : '';
    const safeName = safeUserName(rawName) || rawName.trim();
    if (!nameEl || !nameEl.isConnected) {
      nameEl = document.getElementById('rankingPlayerName');
    }
    if (nameEl) {
      nameEl.classList.add('player-name-highlight');
      nameEl.textContent = safeName || 'Unbekannt';
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
            persistNameLocally(sanitized);
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

  const ensureNameActionsDefaultPosition = () => {
    if (!nameActions || !cardsContainer) return;
    const parent = cardsContainer.parentElement;
    if (!parent) return;
    if (nameActions.parentElement === parent && nameActions.previousElementSibling === cardsContainer) {
      return;
    }
    nameActions.classList.remove('player-ranking-card__actions');
    nameActions.hidden = true;
    cardsContainer.insertAdjacentElement('afterend', nameActions);
  };

  const renderRanking = (payload) => {
    if (!cardsContainer || !topLists) return;
    ensureNameActionsDefaultPosition();
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

    const storedUid = getStoredPlayerUid();
    const matcher = createPlayerMatcher({ name: storedName, playerUid: storedUid });
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
      actions: nameActions || null,
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
        playerUid: entry.playerUid || entry.player_uid || '',
        player_uid: entry.playerUid || entry.player_uid || '',
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
        items: topCatalog.map((entry) => ({
          ...entry,
          playerUid: entry.playerUid || entry.player_uid || '',
          player_uid: entry.playerUid || entry.player_uid || '',
        })),
        formatter: (entry) => formatListTime(entry.time),
      });
    }

    if (puzzleEnabled) {
      listsToRender.push({
        title: 'Rätsel-Topliste',
        icon: 'bolt',
        items: topPuzzle.map((entry) => ({
          ...entry,
          playerUid: entry.playerUid || entry.player_uid || '',
          player_uid: entry.playerUid || entry.player_uid || '',
        })),
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
        playerUid: item.playerUid || item.player_uid || '',
        player_uid: item.playerUid || item.player_uid || '',
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
            persistNameLocally(sanitized);
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

  const processNameChange = async (rawValue) => {
    const previousName = typeof currentName === 'string' ? currentName : '';
    const sanitizedName = safeUserName(rawValue) || (typeof rawValue === 'string' ? rawValue.trim() : '');
    const previousSanitized = safeUserName(previousName) || previousName.trim();

    if (!sanitizedName) {
      window.alert('Bitte gib einen gültigen Namen ein.');
      return false;
    }

    if (sanitizedName === previousSanitized) {
      ensurePlayerUid();
      persistNameLocally(sanitizedName);
      updateNameDisplay();
      refresh();
      return true;
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
      persistNameLocally(previousSanitized);
      updateNameDisplay();
      refresh();
      return false;
    }

    persistNameLocally(sanitizedName);
    updateNameDisplay();
    refresh();
    return true;
  };

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      refresh();
    });
  }

  if (changeNameBtn) {
    changeNameBtn.addEventListener('click', () => {
      const modal = getNameModal();
      const safeCurrentName = safeUserName(currentName) || (typeof currentName === 'string' ? currentName.trim() : '');
      if (modal && nameInput) {
        nameInput.value = safeCurrentName;
        modal.show();
        focusNameInput();
        return;
      }

      const fallbackName = window.prompt('Wie lautet dein Name für das Ranking?', safeCurrentName);
      if (fallbackName === null) {
        return;
      }
      processNameChange(fallbackName);
    });
  }

  if (nameForm && nameInput) {
    nameForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const success = await processNameChange(nameInput.value);
      if (success) {
        const modal = getNameModal();
        if (modal) {
          modal.hide();
        }
      }
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
