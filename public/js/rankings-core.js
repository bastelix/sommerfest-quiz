export function formatTimestamp(ts) {
  const date = new Date(Number(ts) * 1000);
  if (Number.isNaN(date.getTime())) {
    return '';
  }
  const pad = (value) => value.toString().padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function computeRankings(rows, catalogCount = null) {
  const catalogs = new Set();
  const puzzleTimes = new Map();
  const catalogTimes = new Map();
  const scores = new Map();

  rows.forEach((row) => {
    const team = String(row.name || '');
    const catalog = String(row.catalog || '');
    const time = Number(row.time || 0);
    const correct = Number(row.correct || 0);
    catalogs.add(catalog);

    const puzzleTime = row.puzzleTime === null || row.puzzleTime === undefined
      ? null
      : Number(row.puzzleTime);
    if (puzzleTime !== null) {
      const prev = puzzleTimes.get(team);
      if (prev === undefined || puzzleTime < prev) {
        puzzleTimes.set(team, puzzleTime);
      }
    }

    if (!catalogTimes.has(team)) {
      catalogTimes.set(team, new Map());
    }
    const teamCatalogTimes = catalogTimes.get(team);
    const previousTime = teamCatalogTimes.get(catalog);
    if (previousTime === undefined || time < previousTime) {
      teamCatalogTimes.set(catalog, time);
    }

    if (!scores.has(team)) {
      scores.set(team, new Map());
    }
    const teamScores = scores.get(team);
    const prevScore = teamScores.get(catalog);
    if (prevScore === undefined || correct > prevScore) {
      teamScores.set(catalog, correct);
    }
  });

  const totalCatalogs = catalogCount ?? catalogs.size;

  const puzzleList = Array.from(puzzleTimes.entries())
    .map(([team, time]) => ({ team, time }))
    .sort((a, b) => a.time - b.time)
    .slice(0, 3)
    .map((entry, idx) => ({
      name: entry.team,
      value: formatTimestamp(entry.time),
      raw: entry.time,
      place: idx + 1,
    }));

  const finishers = [];
  catalogTimes.forEach((teamMap, team) => {
    if (teamMap.size === totalCatalogs && totalCatalogs > 1) {
      const last = Math.max(...teamMap.values());
      finishers.push({ team, time: last });
    }
  });
  finishers.sort((a, b) => a.time - b.time);
  const catalogList = finishers.slice(0, 3).map((entry, idx) => ({
    name: entry.team,
    value: formatTimestamp(entry.time),
    raw: entry.time,
    place: idx + 1,
  }));

  const totalScores = Array.from(scores.entries()).map(([team, teamScores]) => {
    const total = Array.from(teamScores.values()).reduce((sum, value) => sum + Number(value || 0), 0);
    return { team, score: total };
  });
  totalScores.sort((a, b) => b.score - a.score);
  const pointsList = totalScores.slice(0, 3).map((entry, idx) => ({
    name: entry.team,
    value: String(entry.score),
    raw: entry.score,
    place: idx + 1,
  }));

  return { puzzleList, catalogList, pointsList };
}

export function buildScoreboard(rows) {
  const teamMap = new Map();

  rows.forEach((row) => {
    const team = String(row.name || '');
    const catalog = String(row.catalog || '');
    const correct = Number(row.correct || 0);
    const total = Number(row.total || 0);
    const time = Number(row.time || 0);
    const puzzleTime = row.puzzleTime === null || row.puzzleTime === undefined
      ? null
      : Number(row.puzzleTime);

    if (!teamMap.has(team)) {
      teamMap.set(team, {
        name: team,
        attempts: 0,
        catalogs: new Map(),
        lastUpdate: 0,
        bestPuzzle: null,
      });
    }

    const entry = teamMap.get(team);
    entry.attempts += 1;
    const previous = entry.catalogs.get(catalog);
    if (!previous || correct > previous.correct) {
      entry.catalogs.set(catalog, { correct, total });
    }
    if (time > entry.lastUpdate) {
      entry.lastUpdate = time;
    }
    if (puzzleTime !== null) {
      if (entry.bestPuzzle === null || puzzleTime < entry.bestPuzzle) {
        entry.bestPuzzle = puzzleTime;
      }
    }
  });

  const scoreboard = [];
  teamMap.forEach((entry) => {
    const totalPoints = Array.from(entry.catalogs.values()).reduce((sum, value) => sum + Number(value.correct || 0), 0);
    const catalogsSolved = Array.from(entry.catalogs.values()).filter((value) => value.total > 0 && value.correct >= value.total).length;
    scoreboard.push({
      name: entry.name,
      points: totalPoints,
      attempts: entry.attempts,
      catalogsPlayed: entry.catalogs.size,
      catalogsSolved,
      lastUpdate: entry.lastUpdate,
      bestPuzzleTime: entry.bestPuzzle,
    });
  });

  scoreboard.sort((a, b) => {
    if (b.points !== a.points) {
      return b.points - a.points;
    }
    if (a.bestPuzzleTime !== null && b.bestPuzzleTime !== null && a.bestPuzzleTime !== b.bestPuzzleTime) {
      return a.bestPuzzleTime - b.bestPuzzleTime;
    }
    return a.lastUpdate - b.lastUpdate;
  });

  return scoreboard;
}
