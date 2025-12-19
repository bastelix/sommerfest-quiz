(function(){
  const root = typeof window !== 'undefined' ? window : globalThis;
  const basePath = root.basePath || '';
  const DEFAULT_TTL = 600;
  const MAX_BATCH_SIZE = 10;
  const PREFETCH_FALLBACK = 5;

  const bufferTarget = (() => {
    const config = root.quizConfig;
    if (config && Object.prototype.hasOwnProperty.call(config, 'randomNameBuffer')) {
      const rawValue = config.randomNameBuffer;
      if (typeof rawValue === 'number' && Number.isFinite(rawValue)) {
        return Math.min(Math.max(Math.trunc(rawValue), 0), MAX_BATCH_SIZE);
      }
      if (typeof rawValue === 'string') {
        const trimmed = rawValue.trim();
        if (trimmed) {
          const parsed = Number(trimmed);
          if (Number.isFinite(parsed)) {
            return Math.min(Math.max(Math.trunc(parsed), 0), MAX_BATCH_SIZE);
          }
        }
      }
    }
    return PREFETCH_FALLBACK;
  })();

  /** @type {Array<{name: string, token: string, eventUid: string, expiresAt: number, fallback: boolean, total: number, remaining: number, lexiconVersion: number}>} */
  const nameQueue = [];

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
    if (typeof root.getActiveEventId === 'function') {
      const active = root.getActiveEventId();
      if (active) {
        return active;
      }
    } else if (typeof root.activeEventId === 'string' && root.activeEventId) {
      return root.activeEventId;
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

  function normalizeReservationPayload(payload, fallbackEventUid){
    if (!payload || typeof payload !== 'object') {
      return null;
    }
    const name = typeof payload.name === 'string' ? payload.name : '';
    const token = typeof payload.token === 'string' ? payload.token : '';
    if (!name || !token) {
      return null;
    }
    const expiresAt = parseExpiry(payload.expires_at);
    const total = toNumber(payload.total);
    const remaining = toNumber(payload.remaining);
    const lexVersion = toInt(payload.lexicon_version);
    const eventUid = typeof payload.event_id === 'string' && payload.event_id ? payload.event_id : fallbackEventUid;
    return {
      name,
      token,
      eventUid: typeof eventUid === 'string' ? eventUid : '',
      expiresAt,
      fallback: Boolean(payload.fallback),
      total,
      remaining,
      lexiconVersion: lexVersion ?? 1
    };
  }

  function takeFromQueue(eventUid){
    if (!Array.isArray(nameQueue) || nameQueue.length === 0) {
      return null;
    }
    let remaining = nameQueue.length;
    while (remaining > 0) {
      remaining -= 1;
      const candidate = nameQueue.shift();
      if (!candidate) {
        continue;
      }
      if (eventUid && candidate.eventUid && candidate.eventUid !== eventUid) {
        if (hasValidReservation(candidate)) {
          nameQueue.push(candidate);
        }
        continue;
      }
      if (!hasValidReservation(candidate, eventUid)) {
        continue;
      }
      return candidate;
    }
    return null;
  }

  async function fetchBatch(eventUid, desiredCount){
    const fallbackTarget = Math.max(1, Math.min(bufferTarget, MAX_BATCH_SIZE));
    const normalizedDesired = typeof desiredCount === 'number' && Number.isFinite(desiredCount)
      ? Math.trunc(desiredCount)
      : fallbackTarget;
    const target = Math.max(1, Math.min(normalizedDesired, MAX_BATCH_SIZE));
    const params = new URLSearchParams();
    params.set('count', String(target));
    if (eventUid) {
      params.set('event_uid', eventUid);
    }
    const response = await fetch(`${basePath}/api/team-names/batch?${params.toString()}`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: buildHeaders()
    });
    if (!response.ok) {
      throw new Error('team-name-batch-failed');
    }
    const payload = await response.json();
    const fallbackEventUid = typeof payload?.event_id === 'string' && payload.event_id ? payload.event_id : eventUid;
    const items = Array.isArray(payload?.reservations) ? payload.reservations : [];
    let added = 0;
    for (const item of items) {
      const normalized = normalizeReservationPayload(item, fallbackEventUid);
      if (normalized && hasValidReservation(normalized)) {
        nameQueue.push(normalized);
        added += 1;
      }
    }
    return added;
  }

  async function requestSingleReservation(options){
    const eventUid = options?.eventUid;
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
    const reservation = normalizeReservationPayload(payload, eventUid);
    if (!reservation) {
      throw new Error('team-name-reservation-invalid');
    }
    activeReservation = reservation;
    return reservation;
  }

  async function requestReservation(options){
    const eventUid = resolveEventUid(options?.eventUid);

    const cached = takeFromQueue(eventUid);
    if (cached) {
      activeReservation = cached;
      return cached;
    }

    try {
      const added = await fetchBatch(eventUid, bufferTarget);
      if (added > 0) {
        const queued = takeFromQueue(eventUid);
        if (queued) {
          activeReservation = queued;
          return queued;
        }
      }
    } catch (error) {
      // Ignore batch failures and fall back to single reservations.
    }

    return requestSingleReservation({ eventUid });
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
    if (reservation === activeReservation) {
      activeReservation = null;
    }
    await fetch(`${basePath}/api/team-names/${encodeURIComponent(reservation.token)}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: buildHeaders(),
      body: JSON.stringify({ event_uid: reservation.eventUid })
    }).catch(() => {});
    return true;
  }

  async function releaseReservationByName(options){
    const name = typeof options?.name === 'string' ? options.name.trim() : '';
    if (!name) {
      return false;
    }
    const eventUid = resolveEventUid(options?.eventUid);
    const payload = eventUid ? { event_uid: eventUid, name } : { name };
    try {
      const response = await fetch(`${basePath}/api/team-names/by-name`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: buildHeaders(),
        body: JSON.stringify(payload)
      });
      return response.ok;
    } catch (error) {
      return false;
    }
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
    releaseByName: releaseReservationByName,
    getActiveReservation
  };
})();
