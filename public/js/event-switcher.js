const basePath = window.basePath || '';
const withBase = (p) => basePath + p;

const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
  window.csrfToken || '';

export function setCurrentEvent(uid, name) {
  const token = getCsrfToken();
  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { 'X-CSRF-Token': token } : {})
  };
  return fetch(withBase('/config.json'), {
    method: 'POST',
    credentials: 'same-origin',
    headers,
    body: JSON.stringify({ event_uid: uid })
  })
    .then((resp) => {
      if (!resp.ok) {
        return resp.text().then((text) => {
          throw new Error(text || 'Fehler beim Wechseln des Events');
        });
      }
      if (uid) {
        return fetch(withBase(`/events/${encodeURIComponent(uid)}/config.json`), {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin'
        })
          .then((r) => r.json())
          .catch(() => ({}));
      }
      return {};
    })
    .then((cfg) => {
      document.dispatchEvent(
        new CustomEvent('current-event-changed', { detail: { uid, name, config: cfg } })
      );
      return cfg;
    })
    .catch((err) => {
      if (err instanceof TypeError) {
        throw new Error('Server unreachable');
      }
      throw err;
    });
}
