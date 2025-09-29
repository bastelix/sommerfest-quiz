(function () {
  const EVENT_NAME = 'calserver:cookie-preference-changed';
  const ALLOW_ATTR = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
  const STORAGE_KEY = (globalThis.STORAGE_KEYS && globalThis.STORAGE_KEYS.CALSERVER_COOKIE_CHOICES) || 'calserverCookieChoices';
  const PROSEAL_SCRIPT_SRC = 'https://s.provenexpert.net/seals/proseal-v2.js';
  const PROSEAL_SELECTOR = '[data-calserver-proseal]';
  let proSealScriptLoading = false;
  let proSealScriptLoaded = false;
  const proSealQueue = [];

  function readPreferences() {
    if (globalThis.calserverCookie && typeof globalThis.calserverCookie.getPreferences === 'function') {
      return globalThis.calserverCookie.getPreferences();
    }

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

    if (!raw) {
      return null;
    }

    try {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object') {
        return {
          necessary: true,
          marketing: !!parsed.marketing
        };
      }
    } catch (error) {
      return null;
    }

    return null;
  }

  function marketingAllowed() {
    const preferences = readPreferences();
    return !!(preferences && preferences.marketing);
  }

  function ensureMarketingConsent() {
    if (globalThis.calserverCookie && typeof globalThis.calserverCookie.allowMarketing === 'function') {
      return globalThis.calserverCookie.allowMarketing();
    }

    const preferences = {
      necessary: true,
      marketing: true,
      updatedAt: new Date().toISOString()
    };

    const serialized = JSON.stringify(preferences);

    try {
      if (typeof setStored === 'function') {
        setStored(STORAGE_KEY, serialized);
      }
    } catch (error) {
      /* empty */
    }

    try {
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem(STORAGE_KEY, serialized);
      }
    } catch (error) {
      /* empty */
    }

    if (typeof document !== 'undefined') {
      const detail = { preferences: preferences, marketing: true };
      let event = null;

      if (typeof CustomEvent === 'function') {
        event = new CustomEvent(EVENT_NAME, { detail: detail });
      } else if (typeof document.createEvent === 'function') {
        event = document.createEvent('CustomEvent');
        event.initCustomEvent(EVENT_NAME, false, false, detail);
      }

      if (event) {
        document.dispatchEvent(event);
      }
    }

    return preferences;
  }

  function buildSrc(element) {
    const videoId = element.getAttribute('data-video-id');
    if (!videoId) {
      return null;
    }

    const params = element.getAttribute('data-video-params') || '';
    const normalizedParams = params ? (params.startsWith('?') ? params : `?${params}`) : '';
    return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(videoId)}${normalizedParams}`;
  }

  function injectIframe(container) {
    if (!container || container.dataset.state === 'loaded') {
      return;
    }

    const src = buildSrc(container);
    if (!src) {
      return;
    }

    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.title = container.getAttribute('data-video-title') || '';
    iframe.loading = 'lazy';
    iframe.setAttribute('allow', ALLOW_ATTR);
    iframe.setAttribute('allowfullscreen', 'true');

    container.innerHTML = '';
    container.appendChild(iframe);
    container.dataset.state = 'loaded';
    container.classList.add('is-loaded');
  }

  function ensureProSealScript(callback) {
    if (proSealScriptLoaded) {
      if (typeof callback === 'function') {
        callback();
      }
      return;
    }

    if (typeof callback === 'function') {
      proSealQueue.push(callback);
    }

    if (proSealScriptLoading) {
      return;
    }

    proSealScriptLoading = true;
    const script = document.createElement('script');
    script.src = PROSEAL_SCRIPT_SRC;
    script.async = true;
    script.onload = function () {
      proSealScriptLoaded = true;
      proSealScriptLoading = false;
      const queued = proSealQueue.splice(0, proSealQueue.length);
      queued.forEach(function (fn) {
        if (typeof fn === 'function') {
          try {
            fn();
          } catch (error) {
            /* empty */
          }
        }
      });
    };
    script.onerror = function () {
      proSealScriptLoading = false;
      proSealQueue.splice(0, proSealQueue.length);
    };

    document.head.appendChild(script);
  }

  function setupProSealContainer(container) {
    if (!container) {
      return;
    }

    const target = container.querySelector('[data-proseal-target]');
    if (!target) {
      return;
    }

    if (!target.id) {
      target.id = 'proSealWidget-' + Math.random().toString(36).slice(2, 10);
    }

    const placeholder = container.querySelector('[data-calserver-proseal-placeholder]');
    const consentButton = container.querySelector('[data-calserver-proseal-consent]');
    const errorMessage = container.querySelector('[data-calserver-proseal-error]');
    const widgetId = container.getAttribute('data-widget-id');
    const widgetLanguage = container.getAttribute('data-widget-language') || 'de-DE';
    let loading = false;
    let initialized = false;

    const setState = function (state) {
      container.dataset.state = state;
      if (errorMessage) {
        if (state === 'error') {
          errorMessage.removeAttribute('hidden');
        } else {
          errorMessage.setAttribute('hidden', '');
        }
      }
    };

    if (!widgetId) {
      setState('error');
      return;
    }

    const loadWidget = function () {
      if (initialized || loading) {
        return;
      }

      loading = true;
      setState('loading');

      ensureProSealScript(function () {
        loading = false;

        if (!container.isConnected) {
          setState('idle');
          return;
        }

        const provenExpert = globalThis.provenExpert;
        if (!provenExpert || typeof provenExpert.proSeal !== 'function') {
          setState('error');
          return;
        }

        try {
          provenExpert.proSeal({
            widgetId: widgetId,
            language: widgetLanguage,
            usePageLanguage: false,
            bannerColor: '#097E92',
            textColor: '#FFFFFF',
            showBackPage: true,
            showReviews: true,
            hideDate: true,
            hideName: false,
            googleStars: true,
            displayReviewerLastName: false,
            embeddedSelector: '#' + target.id
          });
          target.removeAttribute('hidden');
          initialized = true;
          setState('loaded');
          document.removeEventListener(EVENT_NAME, handlePreference);
        } catch (error) {
          setState('error');
        }
      });
    };

    const handlePreference = function (event) {
      if (event && event.detail && event.detail.marketing) {
        loadWidget();
      }
    };

    document.addEventListener(EVENT_NAME, handlePreference);

    if (consentButton) {
      consentButton.addEventListener('click', function () {
        if (initialized) {
          return;
        }

        ensureMarketingConsent();
        loadWidget();
      });
    }

    if (marketingAllowed()) {
      loadWidget();
    } else {
      setState('idle');
      if (placeholder) {
        placeholder.removeAttribute('hidden');
      }
    }
  }

  function initProSealWidgets() {
    if (typeof document === 'undefined') {
      return;
    }

    const containers = document.querySelectorAll(PROSEAL_SELECTOR);
    if (!containers.length) {
      return;
    }

    containers.forEach(setupProSealContainer);
  }

  document.addEventListener('DOMContentLoaded', function () {
    const containers = document.querySelectorAll('[data-calserver-video]');
    if (!containers.length) {
      return;
    }

    const loadAll = function () {
      containers.forEach(injectIframe);
    };

    if (marketingAllowed()) {
      loadAll();
    } else {
      const handlePreference = function (event) {
        if (event && event.detail && event.detail.marketing) {
          document.removeEventListener(EVENT_NAME, handlePreference);
          loadAll();
        }
      };

      document.addEventListener(EVENT_NAME, handlePreference);
    }

    containers.forEach(function (container) {
      const consentButton = container.querySelector('[data-calserver-video-consent]');
      if (!consentButton) {
        return;
      }

      consentButton.addEventListener('click', function () {
        if (container.dataset.state === 'loaded') {
          return;
        }

        ensureMarketingConsent();
        loadAll();
      });
    });
  });

  document.addEventListener('DOMContentLoaded', initProSealWidgets);
})();
