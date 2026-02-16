import { EFFECT_TYPES } from './policy.js';

const PRESETS = {
  lifted: {
    idle: { opacity: 0.92, transform: 'translateY(8px)' },
    active: { opacity: 1, transform: 'translateY(0)' }
  },
  gentle: {
    idle: { opacity: 0.95, transform: 'translateY(4px)' },
    active: { opacity: 1, transform: 'translateY(0)' }
  },
  polished: {
    idle: { opacity: 0.97, transform: 'translateY(6px)' },
    active: { opacity: 1, transform: 'translateY(0)' }
  },
  default: {
    idle: { opacity: 1, transform: 'translateY(0)' },
    active: { opacity: 1, transform: 'translateY(0)' }
  }
};

const defaultTransition = 'transform 320ms ease, opacity 320ms ease';

function softenPreset(preset) {
  if (!preset) return PRESETS.default;
  const idle = preset.idle || PRESETS.default.idle;
  const activeOpacity = preset.active?.opacity || 1;
  const translateMatch = (idle.transform || '').match(/translateY\((-?\d+(?:\.\d+)?)px\)/i);
  const baseTranslate = translateMatch ? Number.parseFloat(translateMatch[1]) || 0 : 0;
  const softened = Math.max(1, Math.abs(baseTranslate) / 2);
  return {
    idle: { opacity: Math.min(1, (idle.opacity + activeOpacity) / 2), transform: `translateY(${softened}px)` },
    active: preset.active || PRESETS.default.active
  };
}

function applyState(element, state) {
  if (!element || !state) return;
  element.style.opacity = state.opacity;
  element.style.transform = state.transform;
  if (!element.style.transition) {
    element.style.transition = defaultTransition;
  }
}

function isInViewport(element) {
  if (typeof element.getBoundingClientRect !== 'function') {
    return false;
  }
  const rect = element.getBoundingClientRect();
  const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
  return rect.top < viewportHeight && rect.bottom > 0;
}

function revealElement(entry, presetName) {
  const element = entry.target;
  const preset = PRESETS[presetName] || PRESETS.default;
  applyState(element, preset.active);
}

export function initReveal(root, profile, options = {}) {
  if (!root || !profile?.[EFFECT_TYPES.REVEAL]?.enabled) {
    return null;
  }
  const presetName = profile[EFFECT_TYPES.REVEAL].preset || 'default';
  const reduceMotion = options.reduceMotion === true;
  const mode = options.mode || 'frontend';

  const elements = Array.from(root.querySelectorAll('[data-effect]')).filter(el => {
    const raw = el.dataset?.effect || '';
    return raw.split(/\s+/).some(token => token.trim().toLowerCase() === EFFECT_TYPES.REVEAL);
  });

  if (!elements.length) {
    return null;
  }

  const rawPreset = PRESETS[presetName] || PRESETS.default;
  const preset = mode === 'preview' ? softenPreset(rawPreset) : rawPreset;

  if (reduceMotion || mode === 'design-preview') {
    elements.forEach(el => applyState(el, preset.active));
    return null;
  }

  if (typeof IntersectionObserver === 'undefined') {
    elements.forEach(el => applyState(el, preset.active));
    return null;
  }

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting || entry.intersectionRatio > 0) {
        revealElement(entry, presetName);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  elements.forEach(el => {
    if (isInViewport(el)) {
      applyState(el, preset.active);
    } else {
      applyState(el, preset.idle);
      observer.observe(el);
    }
  });

  return () => observer.disconnect();
}
