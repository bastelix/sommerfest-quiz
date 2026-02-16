import { EFFECT_TYPES } from './policy.js';

const PRESETS = {
  playful: { scale: 1.02, shadow: '0 6px 20px rgba(0, 0, 0, 0.08)' },
  soft: { scale: 1.01, shadow: '0 4px 12px rgba(0, 0, 0, 0.06)' },
  minimal: { scale: 1.005, shadow: '0 2px 8px rgba(0, 0, 0, 0.04)' },
  default: { scale: 1, shadow: 'none' }
};

function applyHoverState(element, preset, active) {
  if (!element) return;
  const target = preset || PRESETS.default;
  element.style.transition = element.style.transition || 'transform 140ms ease, box-shadow 140ms ease';
  element.style.transform = active ? `scale(${target.scale})` : 'scale(1)';
  element.style.boxShadow = active ? target.shadow : 'none';
}

export function initHoverMicroInteractions(root, profile, options = {}) {
  if (!root || !profile?.[EFFECT_TYPES.HOVER]?.enabled) {
    return null;
  }

  const presetName = profile[EFFECT_TYPES.HOVER].preset || 'default';
  const preset = PRESETS[presetName] || PRESETS.default;
  const reduceMotion = options.reduceMotion === true;

  if (reduceMotion) {
    return null;
  }

  let elements = Array.from(root.querySelectorAll('[data-effect]')).filter(el => {
    const raw = el.dataset?.effect || '';
    return raw.split(/\s+/).some(token => token.trim().toLowerCase() === EFFECT_TYPES.HOVER);
  });

  // Auto-detect cards when no explicit data-effect hover markers exist.
  if (!elements.length) {
    elements = Array.from(root.querySelectorAll('.uk-card'));
  }

  if (!elements.length) {
    return null;
  }

  const cleanups = [];

  elements.forEach(element => {
    const handleEnter = () => applyHoverState(element, preset, true);
    const handleLeave = () => applyHoverState(element, preset, false);
    element.addEventListener('pointerenter', handleEnter);
    element.addEventListener('pointerleave', handleLeave);
    element.addEventListener('focus', handleEnter);
    element.addEventListener('blur', handleLeave);
    cleanups.push(() => {
      element.removeEventListener('pointerenter', handleEnter);
      element.removeEventListener('pointerleave', handleLeave);
      element.removeEventListener('focus', handleEnter);
      element.removeEventListener('blur', handleLeave);
    });
  });

  return () => cleanups.forEach(fn => fn());
}
