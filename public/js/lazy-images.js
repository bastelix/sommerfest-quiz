const DEFAULT_ROOT_MARGIN = '200px 0px';
let observer = null;

function ensureAttributes(img) {
  if (!img) return;
  img.loading = 'lazy';
  img.decoding = 'async';
  img.fetchPriority = 'low';
}

function getObserver() {
  if (typeof IntersectionObserver === 'undefined') {
    return null;
  }
  if (!observer) {
    observer = new IntersectionObserver(handleIntersection, {
      rootMargin: DEFAULT_ROOT_MARGIN,
      threshold: 0.01
    });
  }
  return observer;
}

function handleIntersection(entries) {
  entries.forEach(entry => {
    if (entry.isIntersecting || entry.intersectionRatio > 0) {
      loadImage(entry.target);
    }
  });
}

function clearSource(img) {
  if (!img) return;
  if (typeof img.removeAttribute === 'function') {
    img.removeAttribute('src');
  } else {
    img.src = '';
  }
}

function loadImage(img) {
  if (!img || !img.dataset) return;
  const src = img.dataset.src;
  if (!src) return;
  const obs = observer;
  if (obs) {
    obs.unobserve(img);
  }
  if (img.src !== src) {
    img.src = src;
  } else if (!img.src) {
    img.src = src;
  }
  img.dataset.lazyLoaded = 'true';
}

export function applyLazyImage(img, src, options = {}) {
  if (!img) return;
  ensureAttributes(img);
  const obs = getObserver();
  const { forceLoad = false } = options;
  if (!src) {
    if (obs) {
      obs.unobserve(img);
    }
    if (img.dataset) {
      delete img.dataset.src;
      delete img.dataset.lazyLoaded;
    }
    clearSource(img);
    return;
  }
  if (img.dataset) {
    img.dataset.src = src;
    img.dataset.lazyLoaded = 'false';
  }
  if (forceLoad || !obs) {
    loadImage(img);
    return;
  }
  clearSource(img);
  obs.observe(img);
}

export function forceLoadLazyImage(img) {
  loadImage(img);
}

export function resetLazyObserver() {
  if (observer) {
    observer.disconnect();
    observer = null;
  }
}
