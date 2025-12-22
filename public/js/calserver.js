(function () {
  const consentConfig = globalThis.cookieConsentConfig || {};
  const selectors = consentConfig.selectors || {};
  const EVENT_NAME = (typeof consentConfig.eventName === 'string' && consentConfig.eventName.trim())
    ? consentConfig.eventName.trim()
    : 'calserver:cookie-preference-changed';
  const ALLOW_ATTR = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
  const STORAGE_KEY = (typeof consentConfig.storageKey === 'string' && consentConfig.storageKey.trim())
    ? consentConfig.storageKey.trim()
    : ((globalThis.STORAGE_KEYS && globalThis.STORAGE_KEYS.CALSERVER_COOKIE_CHOICES) || 'calserverCookieChoices');
  const PROSEAL_SCRIPT_SRC = 'https://s.provenexpert.net/seals/proseal-v2.js';
  const PROSEAL_SELECTOR = selectors.proSeal || '[data-calserver-proseal]';
  const MODULE_VIDEO_SELECTOR = selectors.moduleVideo || '.calserver-module-figure__video';
  const MODULE_FIGURE_SELECTOR = selectors.moduleFigure || '.calserver-module-figure';
  const MODULE_VIDEO_USER_EVENTS = ['click', 'keydown', 'pointerdown', 'touchstart'];
  let proSealScriptLoading = false;
  let proSealScriptLoaded = false;
  const proSealQueue = [];
  const moduleVideoControls = [];
  let moduleVideoLegacyFallbackAttached = false;
  let moduleFullscreenListenersAttached = false;
  const consentEnabled = consentConfig.enabled !== false;

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
    if (!consentEnabled) {
      return true;
    }

    const preferences = readPreferences();
    return !!(preferences && preferences.marketing);
  }

  function ensureMarketingConsent() {
    if (!consentEnabled) {
      return {
        necessary: true,
        marketing: true,
        updatedAt: new Date().toISOString()
      };
    }

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

    if (!container.dataset.placeholder) {
      container.dataset.placeholder = container.innerHTML;
    }

    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.title = container.getAttribute('data-video-title') || '';
    iframe.loading = 'lazy';
    iframe.setAttribute('allow', ALLOW_ATTR);
    iframe.setAttribute('allowfullscreen', 'true');

    iframe.addEventListener('error', function () {
      if (!container.isConnected || !container.dataset.placeholder) {
        return;
      }

      container.innerHTML = container.dataset.placeholder;
      container.dataset.state = 'error';
      container.classList.remove('is-loaded');
    });

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

  function getModuleVideoFigure(video) {
    if (!video || typeof video.closest !== 'function') {
      return null;
    }

    return video.closest(MODULE_FIGURE_SELECTOR);
  }

  function getModuleVideoPanel(video) {
    const figure = getModuleVideoFigure(video);
    if (!figure) {
      return null;
    }

    const panel = typeof figure.closest === 'function' ? figure.closest('li') : null;
    return panel || figure;
  }

  function isModuleVideoActive(video) {
    const panel = getModuleVideoPanel(video);
    if (!panel || !panel.classList) {
      return false;
    }

    if (panel.classList.contains('uk-active')) {
      return true;
    }

    if (typeof panel.matches === 'function') {
      try {
        return panel.matches('.uk-active');
      } catch (error) {
        return false;
      }
    }

    return false;
  }

  function loadModuleVideo(video, figure) {
    if (!video || video.dataset.calserverVideoState === 'loaded') {
      return false;
    }

    const resolvedFigure = figure || getModuleVideoFigure(video);
    const poster = video.getAttribute('data-poster');
    if (poster) {
      video.poster = poster;
    }

    const sources = video.querySelectorAll('source[data-src]');
    if (sources && sources.length) {
      sources.forEach(function (source) {
        const value = source.getAttribute('data-src');
        if (value) {
          source.setAttribute('src', value);
        }
      });
    }

    const src = video.getAttribute('data-src');
    if (src) {
      video.src = src;
    }

    try {
      if (typeof video.load === 'function') {
        video.load();
      }
    } catch (error) {
      /* empty */
    }

    video.dataset.calserverVideoState = 'loaded';

    if (resolvedFigure && resolvedFigure.classList) {
      resolvedFigure.classList.add('calserver-module-figure--video-loaded');
    }

    return true;
  }

  function setupModuleVideoLegacyFallback(videos) {
    if (typeof document === 'undefined' || moduleVideoLegacyFallbackAttached) {
      return;
    }

    const list = Array.prototype.slice.call(videos || []);
    if (!list.length) {
      return;
    }

    moduleVideoLegacyFallbackAttached = true;

    let triggered = false;

    const handleInteraction = function () {
      if (triggered) {
        return;
      }

      triggered = true;

      MODULE_VIDEO_USER_EVENTS.forEach(function (eventName) {
        document.removeEventListener(eventName, handleInteraction, false);
      });

      list.forEach(function (video) {
        if (!video) {
          return;
        }

        const figure = getModuleVideoFigure(video);
        const loaded = loadModuleVideo(video, figure);
        if (loaded && isModuleVideoActive(video)) {
          attemptModuleVideoAutoplay(video, figure);
        }
      });
    };

    MODULE_VIDEO_USER_EVENTS.forEach(function (eventName) {
      document.addEventListener(eventName, handleInteraction, false);
    });
  }

  function setupModuleVideoLazyLoading(videos) {
    const list = Array.prototype.slice.call(videos || []);
    if (!list.length) {
      return;
    }

    const supportsObserver =
      typeof window !== 'undefined' && typeof window.IntersectionObserver === 'function';

    if (!supportsObserver) {
      setupModuleVideoLegacyFallback(list);
      return;
    }

    const observer = new window.IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry) {
          return;
        }

        const isVisible =
          (typeof entry.isIntersecting === 'boolean' && entry.isIntersecting) ||
          entry.intersectionRatio > 0;

        if (!isVisible) {
          return;
        }

        const video = entry.target;
        if (!video) {
          return;
        }

        const figure = getModuleVideoFigure(video);
        const loaded = loadModuleVideo(video, figure);

        if (loaded && isModuleVideoActive(video)) {
          attemptModuleVideoAutoplay(video, figure);
        }

        obs.unobserve(video);
      });
    }, { rootMargin: '0px 0px 200px 0px', threshold: 0 });

    list.forEach(function (video) {
      if (!video || video.dataset.calserverVideoState === 'loaded') {
        return;
      }

      observer.observe(video);
    });
  }

  function disableModuleVideoAutoplay(video, figure) {
    if (!video || video.dataset.calserverAutoplay === 'manual') {
      return;
    }

    try {
      video.pause();
    } catch (error) {
      /* empty */
    }

    video.dataset.calserverAutoplay = 'manual';
    video.autoplay = false;
    video.removeAttribute('autoplay');

    video.muted = false;
    video.removeAttribute('muted');

    video.controls = true;
    video.setAttribute('controls', '');
    video.setAttribute('controlslist', 'nodownload');

    if (video.classList) {
      video.classList.add('calserver-module-figure__video--manual');
    }

    if (figure && figure.classList) {
      figure.classList.add('calserver-module-figure--manual');
    }
  }

  function attemptModuleVideoAutoplay(video, figure) {
    if (!video || typeof video.play !== 'function') {
      return;
    }

    let playResult = null;

    try {
      playResult = video.play();
    } catch (error) {
      disableModuleVideoAutoplay(video, figure);
      return;
    }

    if (playResult && typeof playResult.catch === 'function') {
      playResult.catch(function () {
        disableModuleVideoAutoplay(video, figure);
      });
    }
  }

  function pauseOtherModuleVideos(activeVideo) {
    moduleVideoControls.forEach(function (entry) {
      const video = entry.video;
      if (!video || video === activeVideo) {
        return;
      }

      try {
        video.pause();
      } catch (error) {
        /* empty */
      }
    });
  }

  function playModuleVideo(video, figure) {
    if (!video) {
      return;
    }

    const resolvedFigure = figure || getModuleVideoFigure(video);

    loadModuleVideo(video, resolvedFigure);

    pauseOtherModuleVideos(video);

    try {
      if (video.readyState > 0) {
        video.currentTime = 0;
      }
    } catch (error) {
      /* empty */
    }

    attemptModuleVideoAutoplay(video, resolvedFigure);
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

    const figure = getModuleVideoFigure(video);
    loadModuleVideo(video, figure);

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

    const videoList = Array.prototype.slice.call(videos);

    videoList.forEach(function (video) {
      registerModuleVideo(video, labels);
    });

    setupModuleVideoLazyLoading(videoList);

    if (!moduleFullscreenListenersAttached) {
      ['fullscreenchange', 'webkitfullscreenchange', 'msfullscreenchange'].forEach(function (eventName) {
        document.addEventListener(eventName, syncModuleVideoStates);
      });

      moduleFullscreenListenersAttached = true;
    }

    syncModuleVideoStates();
  }

  function extractPanelFromEvent(event, switcher) {
    if (!event) {
      return null;
    }

    const detail = event.detail;
    if (detail) {
      if (Array.isArray(detail)) {
        const candidate = detail[0];
        if (candidate && typeof candidate.querySelector === 'function') {
          return candidate;
        }
      } else if (detail.item && typeof detail.item.querySelector === 'function') {
        return detail.item;
      } else if (detail[0] && typeof detail[0].querySelector === 'function') {
        return detail[0];
      }
    }

    if (switcher) {
      const active = switcher.querySelector('li.uk-active');
      if (active) {
        return active;
      }
    }

    return null;
  }

  function focusModuleVideo(panel) {
    if (!panel || typeof panel.querySelector !== 'function') {
      return;
    }

    const figure =
      (typeof panel.matches === 'function' && panel.matches(MODULE_FIGURE_SELECTOR))
        ? panel
        : panel.querySelector(MODULE_FIGURE_SELECTOR);

    if (!figure) {
      return;
    }

    const video = figure.querySelector(MODULE_VIDEO_SELECTOR);
    if (!video) {
      return;
    }

    playModuleVideo(video, figure);
  }

  function initModuleSwitcherAutoplay() {
    const switcher = document.getElementById('calserver-modules-switcher');
    if (!switcher) {
      return;
    }

    const handlePanelChange = function (event) {
      const panel = extractPanelFromEvent(event, switcher);
      if (panel) {
        focusModuleVideo(panel);
      }
    };

    switcher.addEventListener('shown', handlePanelChange);
    switcher.addEventListener('show', handlePanelChange);

    const nav = document.querySelector('.calserver-modules-nav');
    if (nav) {
      nav.addEventListener('click', function (event) {
        const link = event.target.closest('a[href^="#"]');
        if (!link) {
          return;
        }

        const targetId = (link.getAttribute('href') || '').replace(/^#/, '');
        if (!targetId) {
          return;
        }

        const schedule = (typeof window.requestAnimationFrame === 'function')
          ? window.requestAnimationFrame.bind(window)
          : function (callback) {
              return window.setTimeout(callback, 16);
            };

        schedule(function () {
          const figure = document.getElementById(targetId);
          if (!figure) {
            return;
          }

          const panel = figure.closest('li') || figure;
          focusModuleVideo(panel);
        });
      });
    }
  }

  function getModuleDownloadLabel() {
    if (typeof document === 'undefined') {
      return 'Download module video (MP4)';
    }

    const html = document.documentElement;
    if (!html) {
      return 'Download module video (MP4)';
    }

    const lang = (html.getAttribute('lang') || '').toLowerCase();
    if (lang.startsWith('de')) {
      return 'Modul-Video herunterladen (MP4)';
    }

    return 'Download module video (MP4)';
  }

  function initModuleDownloadLinks() {
    if (typeof document === 'undefined') {
      return;
    }

    const figures = document.querySelectorAll(MODULE_FIGURE_SELECTOR);
    if (!figures.length) {
      return;
    }

    const label = getModuleDownloadLabel();

    figures.forEach(function (figure) {
      if (figure.querySelector('[data-calserver-module-download]')) {
        return;
      }

      const video = figure.querySelector(MODULE_VIDEO_SELECTOR);
      if (!video) {
        return;
      }

      let href = '';

      const sourceWithSrc = video.querySelector('source[src]');
      if (sourceWithSrc) {
        href = sourceWithSrc.getAttribute('src') || '';
      }

      if (!href) {
        href = video.getAttribute('data-src') || '';
      }

      if (!href) {
        const sourceWithData = video.querySelector('source[data-src]');
        if (sourceWithData) {
          href = sourceWithData.getAttribute('data-src') || '';
        }
      }

      if (!href || href.toLowerCase().indexOf('.mp4') === -1) {
        return;
      }

      const figcaption = figure.querySelector('figcaption');
      if (!figcaption) {
        return;
      }

      const paragraph = document.createElement('p');
      paragraph.className = 'calserver-module-figure__download';

      const link = document.createElement('a');
      link.className = 'uk-link-text calserver-module-figure__download-link';
      link.href = href;
      link.target = '_blank';
      link.rel = 'noopener';
      link.setAttribute('download', '');
      link.setAttribute('data-calserver-module-download', '');
      link.textContent = label;

      paragraph.appendChild(link);
      figcaption.appendChild(paragraph);
    });
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

  function hideUsecasePdfLinks() {
    if (typeof document === 'undefined') {
      return;
    }

    const links = document.querySelectorAll('.usecase-card a');
    if (!links.length) {
      return;
    }

    Array.prototype.forEach.call(links, function (link) {
      if (!link.querySelector('[data-uk-icon="icon: file-pdf"]')) {
        return;
      }

      link.hidden = true;
      link.setAttribute('aria-hidden', 'true');
      link.setAttribute('tabindex', '-1');
    });
  }

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
  document.addEventListener('DOMContentLoaded', initModuleSwitcherAutoplay);
  document.addEventListener('DOMContentLoaded', initModuleDownloadLinks);
  document.addEventListener('DOMContentLoaded', initHeroBackground);
  document.addEventListener('DOMContentLoaded', initProSealWidgets);
  document.addEventListener('DOMContentLoaded', hideUsecasePdfLinks);
})();
