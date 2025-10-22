const basePath = window.basePath || '';
const withBase = (p) => basePath + p;

const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
  window.csrfToken || '';

const toStringUid = (uid) => (typeof uid === 'string' ? uid : uid ? String(uid) : '');

const deepClone = (value, seen = new Map()) => {
  if (typeof value !== 'object' || value === null) {
    return value;
  }
  if (seen.has(value)) {
    return seen.get(value);
  }
  if (Array.isArray(value)) {
    const clone = [];
    seen.set(value, clone);
    value.forEach((item, idx) => {
      clone[idx] = deepClone(item, seen);
    });
    return clone;
  }
  const clone = {};
  seen.set(value, clone);
  Object.keys(value).forEach((key) => {
    clone[key] = deepClone(value[key], seen);
  });
  return clone;
};

export let switchPending = false;
export let lastSwitchFailed = false;

let activeEventUid = toStringUid(window.quizConfig?.event_uid || '');
let switchEpoch = 0;
const cacheResetters = new Set();
const scopedControllers = new Set();

const createAbortCleanup = (controller) => {
  if (!(controller instanceof AbortController)) {
    return () => {};
  }
  const cleanup = () => {
    scopedControllers.delete(controller);
  };
  controller.signal.addEventListener('abort', cleanup, { once: true });
  return cleanup;
};

function abortControllersBefore(epoch) {
  scopedControllers.forEach((controller) => {
    if (!(controller instanceof AbortController)) {
      scopedControllers.delete(controller);
      return;
    }
    const ctrlEpoch = controller.__switchEpoch ?? 0;
    if (ctrlEpoch < epoch && !controller.signal.aborted) {
      try {
        controller.abort();
      } catch (err) {
        console.error(err);
      }
    }
    if (controller.signal.aborted) {
      scopedControllers.delete(controller);
    }
  });
}

const notifyCacheReset = (detail) => {
  cacheResetters.forEach((fn) => {
    try {
      fn(detail);
    } catch (err) {
      console.error(err);
    }
  });
};

const dispatchSwitchEvent = (detail) => {
  const eventDetail = detail || {};
  document.dispatchEvent(new CustomEvent('event:changed', { detail: eventDetail }));
  document.dispatchEvent(new CustomEvent('current-event-changed', { detail: eventDetail }));
};

export function getActiveEventUID() {
  return activeEventUid;
}

export function getSwitchEpoch() {
  return switchEpoch;
}

export function isCurrentEpoch(epoch) {
  return epoch === switchEpoch;
}

export function registerCacheReset(fn) {
  if (typeof fn !== 'function') {
    return () => {};
  }
  cacheResetters.add(fn);
  return () => cacheResetters.delete(fn);
}

export function registerScopedAbortController(controller, epoch) {
  if (!(controller instanceof AbortController)) {
    return () => {};
  }
  controller.__switchEpoch = epoch;
  scopedControllers.add(controller);
  const cleanup = createAbortCleanup(controller);
  return () => {
    cleanup();
    scopedControllers.delete(controller);
  };
}

export function resetSwitchState() {
  switchPending = false;
  lastSwitchFailed = false;
}

export function markSwitchError() {
  switchPending = false;
  lastSwitchFailed = true;
}

export function setCurrentEvent(uid, name) {
  const targetUid = toStringUid(uid);
  const eventName = typeof name === 'string' ? name.trim() : '';
  const previousUid = activeEventUid;

  switchPending = true;
  lastSwitchFailed = false;

  const epoch = ++switchEpoch;
  abortControllersBefore(epoch);
  notifyCacheReset({ uid: targetUid, previousUid, epoch, pending: true });

  const token = getCsrfToken();
  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { 'X-CSRF-Token': token } : {})
  };

  const controller = new AbortController();
  const cleanupController = registerScopedAbortController(controller, epoch);

  const ensureEpochActive = () => isCurrentEpoch(epoch);

  const handleConfigResponse = (cfg) => {
    const safeConfig = deepClone(cfg || {});
    if (!ensureEpochActive()) {
      return safeConfig;
    }
    activeEventUid = targetUid;
    notifyCacheReset({ uid: targetUid, previousUid, epoch, pending: false, config: safeConfig });
    dispatchSwitchEvent({ uid: targetUid, name: eventName, config: safeConfig, epoch, previousUid });
    resetSwitchState();
    return safeConfig;
  };

  const fail = (error) => {
    if (error && error.name === 'AbortError' && !ensureEpochActive()) {
      throw error;
    }
    const normalizedError = error instanceof TypeError ? new Error('Server unreachable') : error;
    if (ensureEpochActive()) {
      activeEventUid = previousUid;
      markSwitchError();
    }
    throw normalizedError;
  };

  const postBody = JSON.stringify({ event_uid: targetUid });

  return fetch(withBase('/config.json'), {
    method: 'POST',
    credentials: 'same-origin',
    headers,
    body: postBody,
    signal: controller.signal
  })
    .then((resp) => {
      if (!resp.ok) {
        return resp.text().then((text) => {
          throw new Error(text || 'Fehler beim Wechseln des Events');
        });
      }
      if (!targetUid) {
        return {};
      }
      return fetch(withBase(`/events/${encodeURIComponent(targetUid)}/config.json`), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: controller.signal
      }).then((r) => {
        if (!r.ok) {
          return r.text().then((text) => {
            throw new Error(text || 'Fehler beim Laden des Events');
          });
        }
        return r
          .json()
          .catch(() => ({}));
      });
    })
    .then(handleConfigResponse)
    .catch(fail)
    .finally(() => {
      cleanupController();
    });
}
