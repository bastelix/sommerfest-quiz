import { EFFECT_TYPES } from './policy.js';

/**
 * Page Transition effect module.
 *
 * Provides three layers of progressive enhancement for smooth page navigation:
 *  1. View Transitions API – handled purely via CSS (page-transition.css)
 *  2. JS fallback fade – for browsers without View Transitions API
 *  3. Top progress bar – visual loading feedback during navigation
 *
 * Integrates with the namespace-scoped effects policy system.
 */

const PRESETS = {
  smooth: {
    exitDuration: 180,
    enterDuration: 250,
    progressDelay: 120,
    useTransform: true
  },
  minimal: {
    exitDuration: 120,
    enterDuration: 150,
    progressDelay: 100,
    useTransform: false
  },
  default: {
    exitDuration: 180,
    enterDuration: 250,
    progressDelay: 120,
    useTransform: true
  }
};

function shouldSkipLink(anchor, event) {
  if (!anchor || !anchor.href) return true;

  // Modifier keys → new tab intent
  if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return true;
  if (event.button !== 0) return true;

  // Explicit opt-out
  if (anchor.hasAttribute('data-no-transition')) return true;

  // Target blank or download
  if (anchor.target === '_blank' || anchor.hasAttribute('download')) return true;

  // Non-navigable hrefs
  const href = anchor.getAttribute('href') || '';
  if (!href || href.startsWith('#') || href.startsWith('mailto:') ||
      href.startsWith('tel:') || href.startsWith('javascript:')) return true;

  // Different origin
  try {
    const url = new URL(anchor.href, window.location.origin);
    if (url.origin !== window.location.origin) return true;
    // Same page anchor
    if (url.pathname === window.location.pathname && url.hash) return true;
  } catch (e) {
    return true;
  }

  // Inside UIkit modal or offcanvas (let UIkit handle those)
  if (anchor.closest('.uk-modal, .uk-offcanvas, [uk-modal], [uk-offcanvas]')) return true;

  return false;
}

function createProgressBar() {
  const bar = document.createElement('div');
  bar.className = 'page-progress-bar';
  bar.setAttribute('role', 'progressbar');
  bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);
  return bar;
}

function animateProgressBar(bar, reduceMotion) {
  if (!bar) return { finish: () => {}, cancel: () => {} };

  let cancelled = false;
  let timeouts = [];

  const schedule = (fn, delay) => {
    const id = setTimeout(() => { if (!cancelled) fn(); }, delay);
    timeouts.push(id);
    return id;
  };

  // Phase 1: appear and grow to 30%
  bar.style.width = '0%';
  bar.classList.add('active');

  if (reduceMotion) {
    bar.style.width = '30%';
  } else {
    requestAnimationFrame(() => {
      if (cancelled) return;
      bar.classList.add('growing');
      bar.style.width = '30%';

      // Phase 2: slowly crawl to 85%
      schedule(() => {
        bar.classList.remove('growing');
        bar.classList.add('crawling');
        bar.style.width = '85%';
      }, 350);
    });
  }

  return {
    finish: () => {
      if (cancelled) return;
      cancelled = true;
      timeouts.forEach(clearTimeout);
      bar.classList.remove('growing', 'crawling');
      bar.classList.add('finishing');
      bar.style.width = '100%';

      setTimeout(() => {
        bar.classList.remove('active', 'finishing');
        bar.style.width = '0%';
      }, reduceMotion ? 50 : 380);
    },
    cancel: () => {
      cancelled = true;
      timeouts.forEach(clearTimeout);
      bar.classList.remove('active', 'growing', 'crawling', 'finishing');
      bar.style.width = '0%';
    }
  };
}

export function initPageTransition(root, profile, options = {}) {
  if (!root || !profile?.[EFFECT_TYPES.PAGE_TRANSITION]?.enabled) {
    return null;
  }

  const presetName = profile[EFFECT_TYPES.PAGE_TRANSITION].preset || 'default';
  const preset = PRESETS[presetName] || PRESETS.default;
  const reduceMotion = options.reduceMotion === true;
  const hasViewTransitions = typeof document.startViewTransition === 'function';

  // Apply preset data attribute for CSS targeting
  if (presetName === 'minimal') {
    document.documentElement.setAttribute('data-transition-preset', 'minimal');
  }

  // Create progress bar
  const progressBar = createProgressBar();

  // Page enter animation (runs on every page load)
  const content = document.querySelector('.content');
  if (content && !hasViewTransitions && !reduceMotion) {
    content.classList.add('page-entering');
    const onEnd = () => {
      content.classList.remove('page-entering');
      content.removeEventListener('animationend', onEnd);
    };
    content.addEventListener('animationend', onEnd);
    // Fallback removal
    setTimeout(() => content.classList.remove('page-entering'), preset.enterDuration + 100);
  }

  // Finish any progress bar from previous page navigation (bfcache / pageshow)
  let activeProgress = null;

  const onPageShow = () => {
    if (activeProgress) {
      activeProgress.finish();
      activeProgress = null;
    }
  };
  window.addEventListener('pageshow', onPageShow);

  // Link click handler for exit animation + progress bar
  const onClick = (event) => {
    const anchor = event.target.closest('a[href]');
    if (shouldSkipLink(anchor, event)) return;

    // Start progress bar after a short delay (avoids flash on fast navigations)
    const progressTimer = setTimeout(() => {
      activeProgress = animateProgressBar(progressBar, reduceMotion);
    }, preset.progressDelay);

    // If View Transitions API is available, let the browser handle the visual transition.
    // We only need the progress bar for loading feedback.
    if (hasViewTransitions || reduceMotion) return;

    // JS fallback: fade out content, then navigate
    event.preventDefault();
    const href = anchor.href;

    if (content) {
      content.classList.add('page-leaving');
      setTimeout(() => {
        window.location.href = href;
      }, preset.exitDuration);
    } else {
      window.location.href = href;
    }
  };

  document.addEventListener('click', onClick, { capture: true });

  // Handle browser back/forward (bfcache): reset content state
  const onPopState = () => {
    if (content) {
      content.classList.remove('page-leaving', 'page-entering');
      content.style.opacity = '';
      content.style.transform = '';
    }
  };
  window.addEventListener('popstate', onPopState);

  // Teardown function
  return () => {
    document.removeEventListener('click', onClick, { capture: true });
    window.removeEventListener('pageshow', onPageShow);
    window.removeEventListener('popstate', onPopState);
    if (activeProgress) activeProgress.cancel();
    if (progressBar && progressBar.parentNode) {
      progressBar.parentNode.removeChild(progressBar);
    }
  };
}
