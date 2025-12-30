import { EFFECT_TYPES } from './policy.js';

const PRESETS = {
  punchy: {
    initial: { opacity: 0.85, transform: 'scale(0.985)' },
    final: { opacity: 1, transform: 'scale(1)' }
  },
  static: {
    initial: { opacity: 1, transform: 'scale(1)' },
    final: { opacity: 1, transform: 'scale(1)' }
  },
  default: {
    initial: { opacity: 1, transform: 'scale(1)' },
    final: { opacity: 1, transform: 'scale(1)' }
  }
};

export function initHeroIntro(root, profile, options = {}) {
  if (!root || !profile?.[EFFECT_TYPES.HERO_INTRO]?.enabled) {
    return null;
  }
  const presetName = profile[EFFECT_TYPES.HERO_INTRO].preset || 'default';
  const preset = PRESETS[presetName] || PRESETS.default;
  const reduceMotion = options.reduceMotion === true;
  const mode = options.mode || 'frontend';

  const hero = Array.from(root.querySelectorAll('[data-effect]')).find(el => {
    const raw = el.dataset?.effect || '';
    return raw.split(/\s+/).some(token => token.trim().toLowerCase() === EFFECT_TYPES.HERO_INTRO.toLowerCase());
  });
  if (!hero) {
    return null;
  }

  if (reduceMotion || mode !== 'frontend') {
    hero.style.opacity = preset.final.opacity;
    hero.style.transform = preset.final.transform;
    return null;
  }

  hero.style.transition = hero.style.transition || 'transform 420ms ease, opacity 420ms ease';
  hero.style.opacity = preset.initial.opacity;
  hero.style.transform = preset.initial.transform;

  requestAnimationFrame(() => {
    hero.style.opacity = preset.final.opacity;
    hero.style.transform = preset.final.transform;
  });

  return null;
}
