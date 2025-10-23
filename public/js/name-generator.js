(function(){
  const root = typeof window !== 'undefined' ? window : globalThis;
  const basePath = root.basePath || '';
  const DEFAULT_TTL = 600;

  /** @type {{name: string, token: string, eventUid: string, expiresAt: number, fallback: boolean, total: number, remaining: number, lexiconVersion: number}|null} */
  let activeReservation = null;

  function getCsrfToken(){
    if (typeof document !== 'undefined') {
      const meta = document.querySelector('meta[name="csrf-token"]');
      if (meta && typeof meta.getAttribute === 'function') {
        const token = meta.getAttribute('content');
        if (typeof token === 'string' && token) {
          return token;
        }
      }
    }
    if (root.csrfToken) {
      return root.csrfToken;
    }
    return '';
  }

  function normalize(value){
    return typeof value === 'string' ? value.trim().toLowerCase() : '';
  }

  function resolveEventUid(preferred){
    if (preferred && typeof preferred === 'string') {
      return preferred;
    }
    const cfg = root.quizConfig || {};
    if (typeof cfg.event_uid === 'string' && cfg.event_uid) {
      return cfg.event_uid;
    }
    const locationObj = typeof window !== 'undefined' ? window.location : (typeof location !== 'undefined' ? location : null);
    if (locationObj && typeof locationObj.search === 'string' && locationObj.search) {
      try {
        const params = new URLSearchParams(locationObj.search);
        const byEvent = params.get('event') || params.get('event_uid');
        if (byEvent) {
          return byEvent;
        }
      } catch (e) {
        return '';
      }
    }
    return '';
  }

  function buildHeaders(){
    const headers = { 'Content-Type': 'application/json' };
    const token = getCsrfToken();
    if (token) {
      headers['X-CSRF-Token'] = token;
    }
    return headers;
  }

  function toNumber(value){
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function toInt(value){
    const parsed = Number.parseInt(value, 10);
    return Number.isNaN(parsed) ? null : parsed;
  }

  function parseExpiry(value){
    if (typeof value === 'string' && value) {
      const ts = Date.parse(value);
      if (!Number.isNaN(ts)) {
        return ts;
      }
    }
    return Date.now() + DEFAULT_TTL * 1000;
  }

  function hasValidReservation(reservation, eventUid){
    if (!reservation || !reservation.token) {
      return false;
    }
    if (eventUid && reservation.eventUid && reservation.eventUid !== eventUid) {
      return false;
    }
    return reservation.expiresAt > Date.now() + 1000;
  }

  async function requestReservation(options){
    const eventUid = resolveEventUid(options?.eventUid);
    const response = await fetch(`${basePath}/api/team-names`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: buildHeaders(),
      body: JSON.stringify(eventUid ? { event_uid: eventUid } : {})
    });
    if (!response.ok) {
      throw new Error('team-name-reservation-failed');
    }
    const payload = await response.json();
    const expiresAt = parseExpiry(payload.expires_at);
    const total = toNumber(payload.total);
    const remaining = toNumber(payload.remaining);
    const lexVersion = toInt(payload.lexicon_version);
    activeReservation = {
      name: typeof payload.name === 'string' ? payload.name : '',
      token: typeof payload.token === 'string' ? payload.token : '',
      eventUid: typeof payload.event_id === 'string' && payload.event_id ? payload.event_id : eventUid,
      expiresAt,
      fallback: Boolean(payload.fallback),
      total,
      remaining,
      lexiconVersion: lexVersion ?? 1
    };
    return activeReservation;
  }

  async function ensureReservation(options){
    const eventUid = resolveEventUid(options?.eventUid);
    if (hasValidReservation(activeReservation, eventUid)) {
      return activeReservation;
    }
    return requestReservation({ eventUid });
  }

  async function getSuggestion(options){
    const reservation = await ensureReservation(options || {});
    return reservation.name;
  }

  async function confirmReservation(name, options){
    const reservation = options?.reservation || activeReservation;
    if (!reservation || !reservation.token) {
      return false;
    }
    const normalizedInput = normalize(name);
    if (!normalizedInput) {
      await releaseReservation({ reservation });
      return false;
    }
    if (normalizedInput !== normalize(reservation.name)) {
      await releaseReservation({ reservation });
      return false;
    }
    const response = await fetch(`${basePath}/api/team-names/${encodeURIComponent(reservation.token)}/confirm`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: buildHeaders(),
      body: JSON.stringify({ event_uid: reservation.eventUid, name: reservation.name })
    });
    if (!response.ok) {
      await releaseReservation({ reservation });
      return false;
    }
    if (reservation === activeReservation) {
      activeReservation = null;
    }
    return true;
  }

  async function releaseReservation(options){
    const reservation = options?.reservation || activeReservation;
    if (!reservation || !reservation.token) {
      return false;
    }
    await fetch(`${basePath}/api/team-names/${encodeURIComponent(reservation.token)}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: buildHeaders(),
      body: JSON.stringify({ event_uid: reservation.eventUid })
    }).catch(() => {});
    if (reservation === activeReservation) {
      activeReservation = null;
    }
    return true;
  }

  function getActiveReservation(){
    return activeReservation;
  }

  root.TeamNameClient = {
    reserve: ensureReservation,
    refresh: requestReservation,
    getSuggestion,
    confirm: confirmReservation,
    release: releaseReservation,
    getActiveReservation
  };
})();
