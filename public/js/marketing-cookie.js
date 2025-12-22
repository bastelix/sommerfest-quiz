(function () {
  const config = globalThis.cookieConsentConfig || {};
  const selectors = config.selectors || {};
  const classes = config.classes || {};
  const EVENT_NAME = (typeof config.eventName === 'string' && config.eventName.trim())
    ? config.eventName.trim()
    : 'marketing:cookie-preference-changed';
  const STORAGE_KEY = (typeof config.storageKey === 'string' && config.storageKey.trim())
    ? config.storageKey.trim()
    : ((globalThis.STORAGE_KEYS && globalThis.STORAGE_KEYS.CALSERVER_COOKIE_CHOICES) || 'calserverCookieChoices');
  const enabled = config.enabled !== false;
  const state = { banner: null, trigger: null, preferences: null };

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

  function isBannerVisible() {
    return !!(state.banner && !state.banner.hasAttribute('hidden'));
  }

  function focusBanner() {
    if (!state.banner || typeof state.banner.focus !== 'function') {
      return;
    }

    try {
      state.banner.focus({ preventScroll: true });
    } catch (error) {
      state.banner.focus();
    }
  }

  function refreshTriggerState() {
    if (!state.trigger) {
      return;
    }

    const bannerVisible = isBannerVisible();

    if (!state.preferences && !bannerVisible) {
      state.trigger.setAttribute('hidden', '');
      state.trigger.setAttribute('aria-expanded', 'false');
      state.trigger.classList.remove(classes.triggerActive || 'calserver-cookie-trigger--active');
      return;
    }

    state.trigger.removeAttribute('hidden');
    state.trigger.setAttribute('aria-expanded', bannerVisible ? 'true' : 'false');
    state.trigger.classList.toggle(classes.triggerActive || 'calserver-cookie-trigger--active', bannerVisible);
  }

  function setBannerVisibility(visible, options) {
    if (!state.banner) {
      return;
    }

    if (visible) {
      state.banner.removeAttribute('hidden');
      state.banner.classList.add(classes.bannerVisible || 'calserver-cookie-banner--visible');
      if (options && options.focus === true) {
        focusBanner();
      }
    } else {
      state.banner.setAttribute('hidden', '');
      state.banner.classList.remove(classes.bannerVisible || 'calserver-cookie-banner--visible');
    }

    refreshTriggerState();
  }

  function showBanner(options) {
    setBannerVisibility(true, options);
  }

  function updateBannerVisibility(preferences) {
    state.preferences = preferences || null;
    setBannerVisibility(!state.preferences);
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
    if (!enabled) {
      return;
    }

    state.banner = document.querySelector(selectors.banner || '[data-calserver-cookie-banner]');
    if (!state.banner) {
      return;
    }

    const existing = getPreferences();
    state.trigger = document.querySelector(selectors.trigger || '[data-calserver-cookie-open]');

    if (state.trigger) {
      state.trigger.addEventListener('click', function () {
        if (!state.banner) {
          return;
        }

        if (isBannerVisible()) {
          if (state.preferences) {
            setBannerVisibility(false);
          }
          return;
        }

        showBanner({ focus: true });
      });
    }

    updateBannerVisibility(existing);

    const acceptAll = state.banner.querySelector(selectors.accept || '[data-calserver-cookie-accept]');
    if (acceptAll) {
      acceptAll.addEventListener('click', function () {
        allowMarketing();
      });
    }

    const necessaryOnly = state.banner.querySelector(selectors.necessary || '[data-calserver-cookie-necessary]');
    if (necessaryOnly) {
      necessaryOnly.addEventListener('click', function () {
        setPreferences({ marketing: false });
      });
    }

    refreshTriggerState();
  });

  globalThis.marketingCookie = {
    getPreferences: getPreferences,
    setPreferences: setPreferences,
    allowMarketing: allowMarketing
  };
})();
