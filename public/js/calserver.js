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

  document.addEventListener('DOMContentLoaded', function () {
    const containers = document.querySelectorAll('[data-calserver-video]');
    if (!containers.length) {
      return;
    }

    if (readConsent()) {
      containers.forEach(injectIframe);
      return;
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

        storeConsent();
        injectIframe(container);
      });
    });
  });
})();
