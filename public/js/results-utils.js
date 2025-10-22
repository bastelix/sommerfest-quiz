export function formatTimestamp(ts) {
  const d = new Date(ts * 1000);
  const pad = (n) => n.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
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
