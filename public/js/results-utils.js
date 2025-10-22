export function formatTimestamp(ts) {
  if (ts === null || ts === undefined) {
    return '–';
  }
  const numeric = Number(ts);
  if (!Number.isFinite(numeric) || numeric <= 0) {
    return '–';
  }
  const ms = numeric * 1000;
  if (!Number.isFinite(ms) || ms <= 0) {
    return '–';
  }
  const d = new Date(ms);
  if (Number.isNaN(d.getTime())) {
    return '–';
  }
  const pad = (n) => n.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function formatDuration(seconds) {
  if (seconds === null || seconds === undefined) {
    return '–';
  }
  const numeric = Number(seconds);
  if (!Number.isFinite(numeric) || Number.isNaN(numeric)) {
    return '–';
  }
  const total = Math.max(0, Math.round(numeric));
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const secs = total % 60;
  const pad = (value) => value.toString().padStart(2, '0');
  if (hours > 0) {
    return `${hours}:${pad(minutes)}:${pad(secs)}`;
  }
  return `${minutes}:${pad(secs)}`;
}

export function formatPointsCell(points, maxPoints) {
  const pts = Number.isFinite(points) ? points : Number.parseInt(points, 10);
  const normalizedPts = Number.isFinite(pts) ? pts : 0;
  const max = Number.isFinite(maxPoints) ? maxPoints : Number.parseInt(maxPoints, 10);
  if (Number.isFinite(max) && max > 0) {
    return `${normalizedPts}/${max}`;
  }
  return String(normalizedPts);
}

export function formatEfficiencyPercent(value) {
  if (value === null || value === undefined) {
    return '–';
  }
  if (typeof value === 'string' && value.trim() === '') {
    return '–';
  }
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return '–';
  }
  const clamped = Math.max(0, Math.min(numeric, 1));
  const percent = Math.round(clamped * 1000) / 10;
  const str = Number.isFinite(percent) ? percent.toString() : '0';
  return `${str.replace('.', ',')} %`;
}

export function insertSoftHyphens(text) {
  return text ? text.replace(/\/-/g, '\u00AD') : '';
}

export function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
