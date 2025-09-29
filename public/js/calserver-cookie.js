(function () {
  const EVENT_NAME = 'calserver:cookie-preference-changed';
  const STORAGE_KEY = (globalThis.STORAGE_KEYS && globalThis.STORAGE_KEYS.CALSERVER_COOKIE_CHOICES) || 'calserverCookieChoices';
  const state = { banner: null };

  function normalize(preferences) {
    const normalized = {
      necessary: true,
      marketing: !!(preferences && preferences.marketing),
      updatedAt: (preferences && preferences.updatedAt) || new Date().toISOString()
    };
    return normalized;
  }

  function readRawValue() {
    let raw = null;

    try {
      if (typeof getStored === 'function') {
        raw = getStored(STORAGE_KEY);
      }
    } catch (error) {
      raw = null;
    }

    if (raw === null) {
      try {
        if (typeof localStorage !== 'undefined') {
          raw = localStorage.getItem(STORAGE_KEY);
        }
      } catch (error) {
        raw = null;
      }
    }

    return raw;
  }

  function parsePreferences(raw) {
    if (!raw) {
      return null;
    }

    try {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object') {
        return normalize(parsed);
      }
    } catch (error) {
      return null;
    }

    return null;
  }

  function getPreferences() {
    return parsePreferences(readRawValue());
  }

  function writeValue(serialized) {
    let stored = false;

    try {
      if (typeof setStored === 'function') {
        setStored(STORAGE_KEY, serialized);
        stored = true;
      }
    } catch (error) {
      stored = false;
    }

    if (!stored) {
      try {
        if (typeof localStorage !== 'undefined') {
          localStorage.setItem(STORAGE_KEY, serialized);
        }
      } catch (error) {
        /* empty */
      }
    }
  }

  function persistPreferences(preferences) {
    if (!preferences) {
      try {
        if (typeof clearStored === 'function') {
          clearStored(STORAGE_KEY);
        }
      } catch (error) {
        /* empty */
      }

      try {
        if (typeof localStorage !== 'undefined') {
          localStorage.removeItem(STORAGE_KEY);
        }
      } catch (error) {
        /* empty */
      }

      return null;
    }

    const normalized = normalize(preferences);
    writeValue(JSON.stringify(normalized));
    return normalized;
  }

  function createEvent(detail) {
    if (typeof CustomEvent === 'function') {
      return new CustomEvent(EVENT_NAME, { detail: detail });
    }

    if (typeof document !== 'undefined' && typeof document.createEvent === 'function') {
      const event = document.createEvent('CustomEvent');
      event.initCustomEvent(EVENT_NAME, false, false, detail);
      return event;
    }

    return null;
  }

  function emitChange(preferences) {
    if (typeof document === 'undefined') {
      return;
    }

    const detail = {
      preferences: preferences,
      marketing: !!(preferences && preferences.marketing)
    };
    const event = createEvent(detail);
    if (event) {
      document.dispatchEvent(event);
    }
  }

  function updateBannerVisibility(preferences) {
    if (!state.banner) {
      return;
    }

    if (preferences) {
      state.banner.setAttribute('hidden', '');
      state.banner.classList.remove('calserver-cookie-banner--visible');
    } else {
      state.banner.removeAttribute('hidden');
      state.banner.classList.add('calserver-cookie-banner--visible');
    }
  }

  function setPreferences(preferences, options) {
    const stored = persistPreferences(preferences);
    updateBannerVisibility(stored);

    if (!options || options.emit !== false) {
      emitChange(stored);
    }

    return stored;
  }

  function allowMarketing(options) {
    return setPreferences({ marketing: true }, options);
  }

  document.addEventListener('DOMContentLoaded', function () {
    state.banner = document.querySelector('[data-calserver-cookie-banner]');
    if (!state.banner) {
      return;
    }

    const existing = getPreferences();
    updateBannerVisibility(existing);

    const acceptAll = state.banner.querySelector('[data-calserver-cookie-accept]');
    if (acceptAll) {
      acceptAll.addEventListener('click', function () {
        allowMarketing();
      });
    }

    const necessaryOnly = state.banner.querySelector('[data-calserver-cookie-necessary]');
    if (necessaryOnly) {
      necessaryOnly.addEventListener('click', function () {
        setPreferences({ marketing: false });
      });
    }
  });

  globalThis.calserverCookie = {
    getPreferences: getPreferences,
    setPreferences: setPreferences,
    allowMarketing: allowMarketing
  };
})();
