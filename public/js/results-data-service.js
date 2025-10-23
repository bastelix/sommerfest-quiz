import { formatTimestamp, formatDuration } from './results-utils.js';

const parseNumeric = (value) => {
  if (value === null || value === undefined) return null;
  if (typeof value === 'string' && value.trim() === '') return null;
  const num = Number(value);
  return Number.isFinite(num) ? num : null;
};

export class ResultsDataService {
  constructor(options = {}) {
    this.basePath = options.basePath || '';
    this.eventUid = options.eventUid || '';
    this.shareToken = options.shareToken || '';
    this.variant = options.variant === 'sponsor' ? 'sponsor' : 'public';
    this.catalogMap = null;
    this.catalogCount = 0;
    this.catalogList = [];
  }

  withBase(path) {
    return `${this.basePath}${path}`;
  }

  setEventUid(uid) {
    this.eventUid = uid || '';
    this.catalogMap = null;
    this.catalogCount = 0;
    this.catalogList = [];
  }

  setShareToken(token) {
    this.shareToken = token || '';
    this.catalogMap = null;
    this.catalogCount = 0;
    this.catalogList = [];
  }

  setVariant(variant) {
    this.variant = variant === 'sponsor' ? 'sponsor' : 'public';
    this.catalogMap = null;
    this.catalogCount = 0;
    this.catalogList = [];
  }

  buildQuery() {
    const params = new URLSearchParams();
    if (this.eventUid) {
      params.set('event_uid', this.eventUid);
    }
    if (this.shareToken) {
      params.set('share_token', this.shareToken);
      params.set('variant', this.variant);
    }
    const query = params.toString();
    return query ? `?${query}` : '';
  }

  fetchCatalogMap() {
    if (this.catalogMap) {
      if (!Array.isArray(this.catalogList)) {
        this.catalogList = [];
      }
      return Promise.resolve(this.catalogMap);
    }
    const params = new URLSearchParams();
    if (this.eventUid) {
      params.set('event', this.eventUid);
    }
    if (this.shareToken) {
      params.set('share_token', this.shareToken);
      params.set('variant', this.variant);
    }
    const query = params.toString();
    const url = this.withBase(`/kataloge/catalogs.json${query ? `?${query}` : ''}`);
    return fetch(url, {
      headers: { Accept: 'application/json' }
    })
      .then((r) => {
        if (!r.ok) {
          throw new Error('catalogs');
        }
        return r.json();
      })
      .then((payload) => {
        const list = Array.isArray(payload)
          ? payload
          : Array.isArray(payload?.items)
            ? payload.items
            : [];
        const map = {};
        const normalized = [];
        if (Array.isArray(list)) {
          this.catalogCount = list.length;
          list.forEach((catalog) => {
            const name = catalog?.name || '';
            const uid = catalog?.uid ? String(catalog.uid) : '';
            const slug = catalog?.slug ? String(catalog.slug) : '';
            const sortRaw = catalog?.sortOrder ?? catalog?.sort_order;
            const sortOrder = sortRaw !== undefined && sortRaw !== null && sortRaw !== ''
              ? String(sortRaw)
              : '';
            const displayName = String(name || '').trim() || slug || sortOrder || uid;
            if (uid) map[uid] = displayName;
            if (sortOrder) map[sortOrder] = displayName;
            if (slug) map[slug] = displayName;
            normalized.push({
              uid,
              slug,
              sortOrder,
              sort_order: sortOrder,
              name: displayName,
            });
          });
        } else {
          this.catalogCount = 0;
        }
        this.catalogMap = map;
        this.catalogList = normalized;
        return map;
      })
      .catch(() => {
        this.catalogMap = {};
        this.catalogCount = 0;
        this.catalogList = [];
        return {};
      });
  }

  load() {
    if (!this.eventUid) {
      return Promise.resolve({
        rows: [],
        questionRows: [],
        catalogCount: this.catalogCount,
        catalogMap: this.catalogMap || {},
        catalogList: Array.isArray(this.catalogList) ? [...this.catalogList] : [],
      });
    }
    const query = this.buildQuery();
    return Promise.all([
      this.fetchCatalogMap(),
      fetch(this.withBase(`/results.json${query}`), { headers: { Accept: 'application/json' } }).then((r) => {
        if (!r.ok) {
          throw new Error('results');
        }
        return r.json();
      }),
      fetch(this.withBase(`/question-results.json${query}`), { headers: { Accept: 'application/json' } }).then((r) => {
        if (!r.ok) {
          throw new Error('question-results');
        }
        return r.json();
      }),
    ]).then(([catalogMap, rows, questionRows]) => {
      const attemptSummaries = new Map();
      if (Array.isArray(rows)) {
        rows.forEach((row) => {
          const originalCatalog = row.catalog;
          row.catalogKey = originalCatalog;
          if (catalogMap[originalCatalog]) {
            row.catalogName = catalogMap[originalCatalog];
            row.catalog = catalogMap[originalCatalog];
          }
          const parsedTime = parseNumeric(row.time);
          row.time = parsedTime !== null ? Math.trunc(parsedTime) : null;
          const startedCandidate = row.startedAt ?? row.started_at;
          const parsedStarted = parseNumeric(startedCandidate);
          row.startedAt = parsedStarted !== null ? Math.trunc(parsedStarted) : null;
          row.started_at = row.startedAt;
          const durationCandidate = row.durationSec ?? row.duration_sec;
          const parsedDuration = parseNumeric(durationCandidate);
          if (parsedDuration !== null && parsedDuration >= 0) {
            row.durationSec = Math.trunc(parsedDuration);
          } else {
            row.durationSec = null;
          }
          row.duration_sec = row.durationSec;
        });
        rows.sort((a, b) => b.time - a.time);
      }
      if (Array.isArray(questionRows)) {
        questionRows.forEach((row) => {
          const originalCatalog = row.catalog;
          row.catalogKey = originalCatalog;
          if (catalogMap[originalCatalog]) {
            row.catalogName = catalogMap[originalCatalog];
            row.catalog = catalogMap[originalCatalog];
          }
          const team = row.name || '';
          const catalog = (row.catalogKey ?? row.catalog) || '';
          if (!team || !catalog) {
            return;
          }
          const attempt = Number.isFinite(row.attempt) ? Number(row.attempt) : parseInt(row.attempt, 10) || 1;
          const key = `${team}|${catalog}|${attempt}`;
          const finalPointsCandidate = row.final_points ?? row.finalPoints;
          const finalPoints = parseNumeric(finalPointsCandidate) ?? parseNumeric(row.points) ?? 0;
          const efficiencyCandidate = parseNumeric(row.efficiency);
          const efficiency = efficiencyCandidate !== null
            ? Math.max(0, Math.min(efficiencyCandidate, 1))
            : ((parseNumeric(row.correct) ?? 0) > 0 ? 1 : 0);
          const summary = attemptSummaries.get(key) || { points: 0, effSum: 0, count: 0 };
          summary.points += Number.isFinite(finalPoints) ? finalPoints : 0;
          summary.effSum += Math.max(0, Math.min(efficiency, 1));
          summary.count += 1;
          attemptSummaries.set(key, summary);
        });
      }
      if (Array.isArray(rows)) {
        rows.forEach((row) => {
          const team = row.name || '';
          const catalog = (row.catalogKey ?? row.catalog) || '';
          if (!team || !catalog) {
            row.averageEfficiency = null;
            row.avg_efficiency = null;
            return;
          }
          const attempt = Number.isFinite(row.attempt) ? Number(row.attempt) : parseInt(row.attempt, 10) || 1;
          const key = `${team}|${catalog}|${attempt}`;
          const summary = attemptSummaries.get(key);
          if (summary && summary.count > 0) {
            const totalPoints = Math.round(summary.points);
            row.finalPoints = totalPoints;
            row.final_points = totalPoints;
            const avg = summary.effSum / summary.count;
            const clamped = Math.max(0, Math.min(avg, 1));
            row.averageEfficiency = clamped;
            row.avg_efficiency = clamped;
          } else {
            const fallbackFinal = parseNumeric(row.final_points ?? row.finalPoints)
              ?? parseNumeric(row.points)
              ?? parseNumeric(row.correct)
              ?? 0;
            const safeFallback = Math.round(fallbackFinal);
            row.finalPoints = safeFallback;
            row.final_points = safeFallback;
            const totalQuestions = parseNumeric(row.total);
            const correctAnswers = parseNumeric(row.correct);
            if (totalQuestions !== null && totalQuestions > 0 && correctAnswers !== null) {
              const ratio = correctAnswers / totalQuestions;
              const clamped = Math.max(0, Math.min(ratio, 1));
              row.averageEfficiency = clamped;
              row.avg_efficiency = clamped;
            } else {
              row.averageEfficiency = null;
              row.avg_efficiency = null;
            }
          }
        });
        rows.forEach((row) => {
          if (Object.prototype.hasOwnProperty.call(row, 'catalogKey')) {
            delete row.catalogKey;
          }
        });
      }
      if (Array.isArray(questionRows)) {
        questionRows.forEach((row) => {
          if (Object.prototype.hasOwnProperty.call(row, 'catalogKey')) {
            delete row.catalogKey;
          }
        });
      }
      return {
        rows: Array.isArray(rows) ? rows : [],
        questionRows: Array.isArray(questionRows) ? questionRows : [],
        catalogCount: this.catalogCount || Object.keys(catalogMap).length,
        catalogMap,
        catalogList: Array.isArray(this.catalogList) ? [...this.catalogList] : [],
      };
    });
  }
}

export function computeRankings(rows, questionRows, catalogCount = 0) {
  const formatEfficiency = (value) => {
    if (value === null || value === undefined) return '–';
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '–';
    const clamped = Math.max(0, Math.min(numeric, 1));
    const percent = Math.round(clamped * 1000) / 10;
    const str = Number.isFinite(percent) ? percent.toString() : '0';
    return `${str.replace('.', ',')} %`;
  };

  const parseSolvedValue = (value) => {
    if (typeof value === 'boolean') {
      return value ? 1 : 0;
    }
    if (typeof value === 'number') {
      if (!Number.isFinite(value)) return 0;
      const rounded = Math.round(value);
      return rounded > 0 ? rounded : 0;
    }
    if (typeof value === 'string') {
      const trimmed = value.trim().toLowerCase();
      if (trimmed === '') return 0;
      if (trimmed === 'true' || trimmed === 'yes' || trimmed === 'y') {
        return 1;
      }
      const numeric = Number(trimmed);
      if (Number.isFinite(numeric)) {
        const rounded = Math.round(numeric);
        return rounded > 0 ? rounded : 0;
      }
    }
    return 0;
  };

  const puzzleTimes = new Map();
  const scorePoints = new Map();
  const attemptMetrics = new Map();

  questionRows.forEach((entry) => {
    const team = entry.name || '';
    const catalog = entry.catalog || '';
    if (!team || !catalog) return;
    const attempt = Number.isFinite(entry.attempt) ? Number(entry.attempt) : parseInt(entry.attempt, 10) || 1;
    const key = `${team}|${catalog}|${attempt}`;
    const finalPoints = Number.isFinite(entry.final_points)
      ? Number(entry.final_points)
      : Number.isFinite(entry.finalPoints)
        ? Number(entry.finalPoints)
        : Number.isFinite(entry.points) ? Number(entry.points) : 0;
    const rawCorrectFlag = entry.is_correct ?? entry.isCorrect ?? entry.correct ?? entry.isCorrectAnswer;
    const normalizedCorrect = parseSolvedValue(rawCorrectFlag) > 0 ? 1 : 0;
    const efficiency = Number.isFinite(entry.efficiency)
      ? Number(entry.efficiency)
      : normalizedCorrect;
    const summary = attemptMetrics.get(key) || { points: 0, effSum: 0, count: 0 };
    summary.points += Number.isFinite(finalPoints) ? finalPoints : 0;
    summary.effSum += Math.max(0, Math.min(efficiency, 1));
    summary.count += 1;
    const correctFlag = entry.is_correct ?? entry.isCorrect ?? entry.correct ?? entry.isCorrectAnswer;
    summary.correct = (summary.correct || 0) + parseSolvedValue(correctFlag);
    attemptMetrics.set(key, summary);
  });

  rows.forEach((row) => {
    const team = row.name || '';
    const catalog = row.catalog || '';
    if (!team || !catalog) return;
    const puzzleCandidate = parseNumeric(row.puzzleTime);
    if (puzzleCandidate !== null) {
      const prev = puzzleTimes.get(team);
      if (!Number.isFinite(prev) || puzzleCandidate < prev) {
        puzzleTimes.set(team, puzzleCandidate);
      }
    }

    const durationCandidate = parseNumeric(row.durationSec ?? row.duration_sec);
    const duration = (durationCandidate !== null && durationCandidate >= 0)
      ? Math.round(Math.max(0, durationCandidate))
      : null;

    const finishCandidate = parseNumeric(row.time);
    const finish = Number.isFinite(finishCandidate) ? Math.round(finishCandidate) : null;

    const attempt = Number.isFinite(row.attempt) ? Number(row.attempt) : parseInt(row.attempt, 10) || 1;
    const key = `${team}|${catalog}|${attempt}`;
    const summary = attemptMetrics.get(key);
    let finalPoints;
    let effSum;
    let questionCount;
    let solved = 0;
    if (summary && summary.count > 0) {
      finalPoints = summary.points;
      effSum = summary.effSum;
      questionCount = summary.count;
      solved = Number.isFinite(summary.correct) ? Math.max(0, Math.round(summary.correct)) : 0;
    } else {
      const fallbackPoints = Number.isFinite(row.points) ? Number(row.points) : Number(row.correct) || 0;
      finalPoints = fallbackPoints;
      const totalQuestions = Number.isFinite(row.total) ? Number(row.total) : parseInt(row.total, 10) || 0;
      questionCount = totalQuestions > 0 ? totalQuestions : 0;
      const correctCount = parseSolvedValue(row.correct);
      const avgFallback = questionCount > 0 ? Math.max(0, Math.min(correctCount / questionCount, 1)) : 0;
      effSum = avgFallback * questionCount;
      solved = Math.max(0, Math.round(correctCount));
    }
    const average = questionCount > 0 ? Math.max(0, Math.min(effSum / questionCount, 1)) : 0;

    let sMap = scorePoints.get(team);
    if (!sMap) {
      sMap = new Map();
      scorePoints.set(team, sMap);
    }
    const prev = sMap.get(catalog);
    const safeDuration = Number.isFinite(duration) ? duration : null;
    const safeFinish = Number.isFinite(finish) ? finish : null;
    let shouldReplace = false;
    if (!prev) {
      shouldReplace = true;
    } else {
      const prevSolved = Number.isFinite(prev.solved) ? prev.solved : 0;
      const prevPoints = Number.isFinite(prev.points) ? prev.points : 0;
      const prevDuration = Number.isFinite(prev.duration) ? prev.duration : null;
      const prevAvg = Number.isFinite(prev.avg) ? prev.avg : 0;
      const prevFinish = Number.isFinite(prev.finish) ? prev.finish : Infinity;

      if (solved > prevSolved) {
        shouldReplace = true;
      } else if (solved === prevSolved) {
        if (finalPoints > prevPoints) {
          shouldReplace = true;
        } else if (finalPoints === prevPoints) {
          let durationCmp = 0;
          const hasDuration = safeDuration !== null;
          const prevHasDuration = prevDuration !== null;
          if (hasDuration && prevHasDuration) {
            durationCmp = safeDuration - prevDuration;
          } else if (hasDuration) {
            durationCmp = -1;
          } else if (prevHasDuration) {
            durationCmp = 1;
          }

          if (durationCmp < 0) {
            shouldReplace = true;
          } else if (durationCmp === 0) {
            if (average > prevAvg) {
              shouldReplace = true;
            } else if (average === prevAvg) {
              const finishValue = safeFinish ?? Infinity;
              if (finishValue < prevFinish) {
                shouldReplace = true;
              }
            }
          }
        }
      }
    }

    if (shouldReplace) {
      sMap.set(catalog, {
        points: finalPoints,
        effSum,
        count: questionCount,
        avg: average,
        solved,
        duration: safeDuration,
        finish: safeFinish,
      });
    }
  });

  const puzzleArr = [];
  puzzleTimes.forEach((time, name) => {
    puzzleArr.push({ name, value: formatTimestamp(time), raw: time });
  });
  puzzleArr.sort((a, b) => a.raw - b.raw);
  const puzzleList = puzzleArr.slice(0, 3);

  const rankingCandidates = [];

  const totalScores = [];
  const accuracyScores = [];
  scorePoints.forEach((map, name) => {
    let total = 0;
    let effSumTotal = 0;
    let questionCountTotal = 0;
    let solvedTotal = 0;
    let durationTotal = 0;
    let durationCount = 0;
    let latestFinish = null;
    map.forEach((entry) => {
      total += Number.isFinite(entry.points) ? entry.points : 0;
      effSumTotal += Number.isFinite(entry.effSum) ? entry.effSum : 0;
      questionCountTotal += Number.isFinite(entry.count) ? entry.count : 0;
      solvedTotal += Number.isFinite(entry.solved) ? entry.solved : 0;
      if (Number.isFinite(entry.duration)) {
        durationTotal += entry.duration;
        durationCount += 1;
      }
      if (Number.isFinite(entry.finish)) {
        if (latestFinish === null || entry.finish > latestFinish) {
          latestFinish = entry.finish;
        }
      }
    });
    const avgEfficiency = questionCountTotal > 0
      ? Math.max(0, Math.min(effSumTotal / questionCountTotal, 1))
      : 0;
    const display = `${total} Punkte (Ø ${(avgEfficiency * 100).toFixed(0)}%)`;
    totalScores.push({ name, value: display, raw: total, avg: avgEfficiency });
    if (questionCountTotal > 0) {
      accuracyScores.push({
        name,
        raw: avgEfficiency,
        questions: questionCountTotal,
        score: total,
        value: `Ø ${formatEfficiency(avgEfficiency)}`,
      });
    }
    rankingCandidates.push({
      name,
      solved: solvedTotal,
      points: total,
      duration: durationCount > 0 ? durationTotal : null,
      finish: latestFinish,
    });
  });

  rankingCandidates.sort((a, b) => {
    if ((b.solved ?? 0) !== (a.solved ?? 0)) {
      return (b.solved ?? 0) - (a.solved ?? 0);
    }
    if ((b.points ?? 0) !== (a.points ?? 0)) {
      return (b.points ?? 0) - (a.points ?? 0);
    }
    const aHasDuration = Number.isFinite(a.duration);
    const bHasDuration = Number.isFinite(b.duration);
    if (aHasDuration && bHasDuration) {
      if (a.duration !== b.duration) {
        return a.duration - b.duration;
      }
    } else if (aHasDuration) {
      return -1;
    } else if (bHasDuration) {
      return 1;
    }
    const aFinish = Number.isFinite(a.finish) ? a.finish : Infinity;
    const bFinish = Number.isFinite(b.finish) ? b.finish : Infinity;
    if (aFinish !== bFinish) {
      return aFinish - bFinish;
    }
    return a.name.localeCompare(b.name);
  });

  const catalogList = rankingCandidates.slice(0, 3).map((item) => ({
    name: item.name,
    value: `${item.solved} gelöst – ${item.points} Punkte`,
    raw: item.solved,
    solved: item.solved,
    points: item.points,
    duration: Number.isFinite(item.duration) ? item.duration : null,
    finished: Number.isFinite(item.finish) ? item.finish : null,
  }));
  totalScores.sort((a, b) => {
    if (b.raw !== a.raw) return b.raw - a.raw;
    return (b.avg ?? 0) - (a.avg ?? 0);
  });
  const pointsList = totalScores.slice(0, 3);

  accuracyScores.sort((a, b) => {
    if (b.raw !== a.raw) return b.raw - a.raw;
    if (b.questions !== a.questions) return b.questions - a.questions;
    if (b.score !== a.score) return b.score - a.score;
    return a.name.localeCompare(b.name);
  });
  const accuracyList = accuracyScores.slice(0, 3).map((entry) => ({
    name: entry.name,
    value: entry.value,
    raw: entry.raw,
  }));

  return { puzzleList, catalogList, pointsList, accuracyList };
}
