(function () {
  const EVENT_NAME = 'calserver:cookie-preference-changed';
  const ALLOW_ATTR = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
  const STORAGE_KEY = (globalThis.STORAGE_KEYS && globalThis.STORAGE_KEYS.CALSERVER_COOKIE_CHOICES) || 'calserverCookieChoices';
  const PROSEAL_SCRIPT_SRC = 'https://s.provenexpert.net/seals/proseal-v2.js';
  const PROSEAL_SELECTOR = '[data-calserver-proseal]';
  const MODULE_VIDEO_SELECTOR = '.calserver-module-figure__video';
  let proSealScriptLoading = false;
  let proSealScriptLoaded = false;
  const proSealQueue = [];
  const moduleVideoControls = [];
  let moduleFullscreenListenersAttached = false;

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

  function getModuleVideoLabels() {
    const language = (document.documentElement && document.documentElement.lang) || '';
    const normalized = language.toLowerCase();

    if (normalized.indexOf('en') === 0) {
      return {
        enter: 'Open video in fullscreen',
        exit: 'Exit fullscreen mode'
      };
    }

    return {
      enter: 'Video im Vollbild anzeigen',
      exit: 'Vollbildmodus verlassen'
    };
  }

  function isModuleVideoFullscreen(video) {
    if (!video || typeof document === 'undefined') {
      return false;
    }

    if (document.fullscreenElement === video) {
      return true;
    }

    if (document.webkitFullscreenElement === video) {
      return true;
    }

    if (document.msFullscreenElement === video) {
      return true;
    }

    if (typeof video.webkitDisplayingFullscreen === 'boolean' && video.webkitDisplayingFullscreen) {
      return true;
    }

    return false;
  }

  function updateModuleVideoState(entry, isFullscreen) {
    if (!entry || !entry.button) {
      return;
    }

    const button = entry.button;
    const icon = entry.icon;
    const labels = entry.labels || getModuleVideoLabels();
    const active = !!isFullscreen;
    const label = active ? labels.exit : labels.enter;

    button.dataset.state = active ? 'active' : 'idle';
    button.setAttribute('aria-label', label);
    button.title = label;

    if (icon) {
      icon.textContent = active ? '⤡' : '⤢';
    }
  }

  function syncModuleVideoStates() {
    moduleVideoControls.forEach(function (entry) {
      updateModuleVideoState(entry, isModuleVideoFullscreen(entry.video));
    });
  }

  function requestModuleVideoFullscreen(video) {
    if (!video) {
      return;
    }

    const request =
      video.requestFullscreen ||
      video.webkitRequestFullscreen ||
      video.msRequestFullscreen;

    if (typeof request === 'function') {
      const result = request.call(video);
      if (result && typeof result.catch === 'function') {
        result.catch(function () {
          /* empty */
        });
      }
      return;
    }

    if (typeof video.webkitEnterFullscreen === 'function') {
      try {
        video.webkitEnterFullscreen();
      } catch (error) {
        /* empty */
      }
    }
  }

  function exitModuleVideoFullscreen(video) {
    if (!video) {
      return;
    }

    if (document.fullscreenElement === video && typeof document.exitFullscreen === 'function') {
      const result = document.exitFullscreen();
      if (result && typeof result.catch === 'function') {
        result.catch(function () {
          /* empty */
        });
      }
      return;
    }

    if (document.webkitFullscreenElement === video && typeof document.webkitExitFullscreen === 'function') {
      document.webkitExitFullscreen();
      return;
    }

    if (document.msFullscreenElement === video && typeof document.msExitFullscreen === 'function') {
      document.msExitFullscreen();
      return;
    }

    if (typeof video.webkitExitFullscreen === 'function') {
      try {
        video.webkitExitFullscreen();
      } catch (error) {
        /* empty */
      }
    }
  }

  function registerModuleVideo(video, labels) {
    if (!video || video.dataset.calserverFullscreen === 'ready') {
      return;
    }

    const figure = video.closest('.calserver-module-figure');
    if (!figure) {
      return;
    }

    video.dataset.calserverFullscreen = 'ready';

    let button = figure.querySelector('[data-calserver-video-fullscreen]');
    if (!button) {
      button = document.createElement('button');
      button.type = 'button';
      button.className = 'calserver-module-figure__fullscreen-button';
      button.setAttribute('data-calserver-video-fullscreen', '');

      const icon = document.createElement('span');
      icon.className = 'calserver-module-figure__fullscreen-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = '⤢';

      button.appendChild(icon);
      figure.appendChild(button);
    }

    const iconElement = button.querySelector('.calserver-module-figure__fullscreen-icon');

    const entry = {
      video: video,
      button: button,
      icon: iconElement,
      labels: labels
    };

    moduleVideoControls.push(entry);
    updateModuleVideoState(entry, false);

    button.addEventListener('click', function () {
      if (isModuleVideoFullscreen(video)) {
        exitModuleVideoFullscreen(video);
      } else {
        requestModuleVideoFullscreen(video);
      }
    });

    video.addEventListener('webkitbeginfullscreen', function () {
      updateModuleVideoState(entry, true);
    });

    video.addEventListener('webkitendfullscreen', function () {
      updateModuleVideoState(entry, false);
    });
  }

  function initModuleVideoFullscreen() {
    const videos = document.querySelectorAll(MODULE_VIDEO_SELECTOR);
    if (!videos.length) {
      return;
    }

    const labels = getModuleVideoLabels();

    videos.forEach(function (video) {
      registerModuleVideo(video, labels);
    });

    if (!moduleFullscreenListenersAttached) {
      ['fullscreenchange', 'webkitfullscreenchange', 'msfullscreenchange'].forEach(function (eventName) {
        document.addEventListener(eventName, syncModuleVideoStates);
      });

      moduleFullscreenListenersAttached = true;
    }

    syncModuleVideoStates();
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

  function unwrapProSealCard(container) {
    if (!container) {
      return;
    }

    const card = container.closest('.contact-card');
    if (!card) {
      return;
    }

    const parent = card.parentElement;
    if (!parent) {
      return;
    }

    const heading = card.querySelector('.uk-text-large');
    if (heading) {
      const text = heading.textContent.trim().toLowerCase();
      if (text.includes('kundenstimmen') || text.includes('customer voices')) {
        heading.remove();
      }
    }

    parent.replaceChild(container, card);
  }

  function setupProSealContainer(container) {
    if (!container) {
      return;
    }

    unwrapProSealCard(container);

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

  function initHeroBackground() {
    const container = document.querySelector('[data-calserver-hero-bg]');
    if (!container) {
      return;
    }

    const canvas = container.querySelector('[data-calserver-hero-canvas]');
    if (!canvas) {
      return;
    }

    const context = canvas.getContext('2d');
    if (!context) {
      container.classList.add('is-static');
      return;
    }

    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const palette = [
      'rgba(66, 132, 255, 0.45)',
      'rgba(43, 214, 255, 0.35)',
      'rgba(123, 92, 255, 0.4)',
      'rgba(255, 255, 255, 0.12)'
    ];
    const blobCount = Math.max(5, Math.min(9, Math.round(window.innerWidth / 340)));
    const blobs = Array.from({ length: blobCount }, function (_, index) {
      return {
        color: palette[index % palette.length],
        anchorX: 0.12 + Math.random() * 0.76,
        anchorY: 0.08 + Math.random() * 0.82,
        ampX: 0.12 + Math.random() * 0.2,
        ampY: 0.14 + Math.random() * 0.24,
        baseRadius: 180 + Math.random() * 180,
        speed: 0.08 + Math.random() * 0.18,
        drift: 0.00012 + Math.random() * 0.0002,
        phase: Math.random() * Math.PI * 2
      };
    });

    let dpr = 1;
    let width = 0;
    let height = 0;
    let animationFrame = null;
    let needsResize = true;

    function isHighContrast() {
      if (!document.body) {
        return false;
      }

      return (
        document.body.classList.contains('high-contrast') ||
        document.body.dataset.theme === 'high-contrast'
      );
    }

    function resize() {
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      width = container.offsetWidth || container.clientWidth || window.innerWidth;
      height = container.offsetHeight || container.clientHeight || window.innerHeight;

      if (!width || !height) {
        return;
      }

      canvas.width = Math.round(width * dpr);
      canvas.height = Math.round(height * dpr);
      canvas.style.width = width + 'px';
      canvas.style.height = height + 'px';
      context.setTransform(1, 0, 0, 1, 0, 0);
      context.scale(dpr, dpr);
    }

    function clearCanvas() {
      context.setTransform(1, 0, 0, 1, 0, 0);
      context.clearRect(0, 0, canvas.width, canvas.height);
      context.scale(dpr, dpr);
    }

    function stop(makeStatic) {
      if (animationFrame !== null) {
        window.cancelAnimationFrame(animationFrame);
        animationFrame = null;
      }

      if (makeStatic) {
        clearCanvas();
      }

      container.classList.add('is-static');
      needsResize = true;
    }

    function renderFrame(timestamp) {
      animationFrame = window.requestAnimationFrame(renderFrame);

      if (needsResize) {
        resize();
        needsResize = false;
      }

      if (!width || !height) {
        return;
      }

      if (motionQuery.matches || isHighContrast()) {
        stop(true);
        return;
      }

      const time = timestamp * 0.001;

      context.clearRect(0, 0, width, height);
      context.globalCompositeOperation = 'lighter';
      context.globalAlpha = 0.85;

      blobs.forEach(function (blob, index) {
        const angle = time * blob.speed + blob.phase;
        const drift = time * blob.drift;
        const x = (blob.anchorX + Math.cos(angle) * blob.ampX + Math.sin(drift + index) * 0.02) * width;
        const y = (blob.anchorY + Math.sin(angle * 0.85) * blob.ampY + Math.cos(drift - index) * 0.02) * height;
        const radius = blob.baseRadius * (0.75 + 0.25 * Math.sin(angle + drift));

        const gradient = context.createRadialGradient(
          x,
          y,
          Math.max(radius * 0.12, 12),
          x,
          y,
          radius
        );

        gradient.addColorStop(0, blob.color);
        gradient.addColorStop(1, 'rgba(6, 12, 24, 0)');

        context.fillStyle = gradient;
        context.beginPath();
        context.arc(x, y, radius, 0, Math.PI * 2);
        context.fill();
      });

      context.globalAlpha = 1;
      context.globalCompositeOperation = 'source-over';
    }

    function start() {
      if (motionQuery.matches || isHighContrast()) {
        stop(true);
        return;
      }

      if (needsResize) {
        resize();
        needsResize = false;
      }

      if (!width || !height) {
        return;
      }

      container.classList.remove('is-static');

      if (animationFrame === null) {
        animationFrame = window.requestAnimationFrame(renderFrame);
      }
    }

    const handleResize = function () {
      needsResize = true;

      if (animationFrame === null && !motionQuery.matches && !isHighContrast()) {
        start();
      }
    };

    const handleVisibility = function () {
      if (document.hidden) {
        if (animationFrame !== null) {
          window.cancelAnimationFrame(animationFrame);
          animationFrame = null;
        }
      } else {
        start();
      }
    };

    const handleMotionChange = function (event) {
      if (event.matches) {
        stop(true);
      } else {
        needsResize = true;
        start();
      }
    };

    start();

    window.addEventListener('resize', handleResize);
    document.addEventListener('visibilitychange', handleVisibility);

    if (typeof motionQuery.addEventListener === 'function') {
      motionQuery.addEventListener('change', handleMotionChange);
    } else if (typeof motionQuery.addListener === 'function') {
      motionQuery.addListener(handleMotionChange);
    }

    if (document.body) {
      const observer = new MutationObserver(function () {
        if (isHighContrast()) {
          stop(true);
        } else {
          start();
        }
      });

      observer.observe(document.body, {
        attributes: true,
        attributeFilter: ['class', 'data-theme']
      });
    }
  }

  document.addEventListener('DOMContentLoaded', initModuleVideoFullscreen);
  document.addEventListener('DOMContentLoaded', initHeroBackground);
  document.addEventListener('DOMContentLoaded', initProSealWidgets);
})();
