(function () {
  const CONSENT_KEY = 'calserverVideoConsent';
  const ALLOW_ATTR = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';

  function readConsent() {
    try {
      if (typeof getStored === 'function') {
        return getStored(CONSENT_KEY) === '1';
      }
    } catch (error) {
      /* empty */
    }

    try {
      if (typeof localStorage !== 'undefined') {
        return localStorage.getItem(CONSENT_KEY) === '1';
      }
    } catch (error) {
      /* empty */
    }

    return false;
  }

  function storeConsent() {
    try {
      if (typeof setStored === 'function') {
        setStored(CONSENT_KEY, '1');
        return;
      }
    } catch (error) {
      /* empty */
    }

    try {
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem(CONSENT_KEY, '1');
      }
    } catch (error) {
      /* empty */
    }
  }

  function buildSrc(element, autoplay) {
    const videoId = element.getAttribute('data-video-id');
    if (!videoId) {
      return null;
    }

    const params = element.getAttribute('data-video-params') || '';
    const normalizedParams = params.trim().replace(/^\?/, '');

    if (typeof URLSearchParams === 'undefined') {
      let query = normalizedParams;
      if (autoplay) {
        if (!/(^|&)autoplay=/.test(query)) {
          query += (query ? '&' : '') + 'autoplay=1';
        }
        if (!/(^|&)playsinline=/.test(query)) {
          query += (query ? '&' : '') + 'playsinline=1';
        }
      }

      return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(videoId)}${query ? `?${query}` : ''}`;
    }

    const searchParams = new URLSearchParams(normalizedParams);

    if (autoplay) {
      searchParams.set('autoplay', '1');
      if (!searchParams.has('playsinline')) {
        searchParams.set('playsinline', '1');
      }
    }

    const query = searchParams.toString();
    return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(videoId)}${query ? `?${query}` : ''}`;
  }

  function loadVideo(container, options) {
    if (!container || container.dataset.state === 'loaded') {
      return;
    }

    const slot = container.querySelector('[data-calserver-video-slot]');
    if (!slot) {
      return;
    }

    const autoplay = Boolean(options && options.autoplay);
    const remember = Boolean(options && options.remember);
    const src = buildSrc(container, autoplay);
    if (!src) {
      return;
    }

    if (remember) {
      storeConsent();
    }

    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.title = container.getAttribute('data-video-title') || '';
    iframe.loading = 'lazy';
    iframe.setAttribute('allow', ALLOW_ATTR);
    iframe.setAttribute('allowfullscreen', 'true');
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';

    slot.innerHTML = '';
    slot.appendChild(iframe);
    slot.hidden = false;
    if (typeof slot.removeAttribute === 'function') {
      slot.removeAttribute('hidden');
    }

    const poster = container.querySelector('[data-calserver-video-trigger]');
    if (poster) {
      poster.hidden = true;
    }

    container.dataset.state = 'loaded';
    container.classList.add('is-loaded');
  }

  document.addEventListener('DOMContentLoaded', function () {
    const containers = document.querySelectorAll('[data-calserver-video]');
    if (!containers.length) {
      return;
    }

    if (readConsent()) {
      containers.forEach(function (container) {
        loadVideo(container, { autoplay: false, remember: false });
      });
      return;
    }

    containers.forEach(function (container) {
      const posterTrigger = container.querySelector('[data-calserver-video-trigger]');
      if (posterTrigger) {
        posterTrigger.addEventListener('click', function () {
          loadVideo(container, { autoplay: true, remember: false });
        });
      }

      const loadOnceButton = container.querySelector('[data-calserver-video-load="once"]');
      if (loadOnceButton) {
        loadOnceButton.addEventListener('click', function () {
          loadVideo(container, { autoplay: true, remember: false });
        });
      }

      const loadAlwaysButton = container.querySelector('[data-calserver-video-load="always"]');
      if (loadAlwaysButton) {
        loadAlwaysButton.addEventListener('click', function () {
          loadVideo(container, { autoplay: true, remember: true });
        });
      }
    });
  });
})();
