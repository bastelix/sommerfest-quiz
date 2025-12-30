import { EFFECT_TYPES } from './policy.js';

const BEHAVIOR_PRESETS = {
  momentum: { autoAdvance: 6500, motion: true },
  steady: { autoAdvance: 9500, motion: true },
  manual: { autoAdvance: null, motion: false },
  default: { autoAdvance: null, motion: false }
};

function resolveBehavior(presetName, disableMotion) {
  const preset = BEHAVIOR_PRESETS[presetName] || BEHAVIOR_PRESETS.default;
  if (disableMotion) {
    return { ...preset, autoAdvance: null, motion: false };
  }
  return preset;
}

function normalizeSlides(container) {
  const slides = Array.from(container.querySelectorAll('[data-slide], .slide, li'));
  return slides.length ? slides : null;
}

function bindNavigation(container, goPrev, goNext) {
  const cleanups = [];
  container.querySelectorAll('[data-slider-prev]').forEach(button => {
    if (typeof button.addEventListener === 'function') {
      const handler = event => {
        event.preventDefault();
        goPrev();
      };
      button.addEventListener('click', handler);
      cleanups.push(() => button.removeEventListener('click', handler));
    }
  });
  container.querySelectorAll('[data-slider-next]').forEach(button => {
    if (typeof button.addEventListener === 'function') {
      const handler = event => {
        event.preventDefault();
        goNext();
      };
      button.addEventListener('click', handler);
      cleanups.push(() => button.removeEventListener('click', handler));
    }
  });
  return cleanups;
}

export function initSlider(root, profile, options = {}) {
  if (!root || !profile?.[EFFECT_TYPES.SLIDER]?.enabled) {
    return null;
  }

  const presetName = profile[EFFECT_TYPES.SLIDER].preset || 'default';
  const reduceMotion = options.reduceMotion === true;
  const mode = options.mode || 'frontend';
  const disableMotion = reduceMotion || mode !== 'frontend';
  const behavior = resolveBehavior(presetName, disableMotion);

  const sliders = Array.from(root.querySelectorAll('[data-component="slider"]'));
  if (!sliders.length) {
    return null;
  }

  const cleanups = [];

  sliders.forEach(container => {
    const slides = normalizeSlides(container);
    if (!slides || slides.length <= 1) {
      return;
    }

    container.setAttribute('role', container.getAttribute('role') || 'region');
    container.setAttribute('aria-roledescription', 'carousel');

    let activeIndex = 0;

    const updateStates = () => {
      slides.forEach((slide, index) => {
        slide.setAttribute('aria-hidden', index === activeIndex ? 'false' : 'true');
        slide.tabIndex = index === activeIndex ? 0 : -1;
      });
    };

    const goTo = (nextIndex, behaviorKey) => {
      const targetIndex = (nextIndex + slides.length) % slides.length;
      activeIndex = targetIndex;
      updateStates();
      const target = slides[targetIndex];
      if (target && typeof target.scrollIntoView === 'function') {
        const motionAllowed = behavior.motion && behaviorKey !== 'snapOnly';
        target.scrollIntoView({
          behavior: motionAllowed ? 'smooth' : 'auto',
          block: 'nearest',
          inline: 'center'
        });
      }
    };

    const goNext = () => goTo(activeIndex + 1);
    const goPrev = () => goTo(activeIndex - 1);

    cleanups.push(...bindNavigation(container, goPrev, goNext));

    if (behavior.autoAdvance) {
      const timer = setInterval(goNext, behavior.autoAdvance);
      cleanups.push(() => clearInterval(timer));
    }

    updateStates();
  });

  return () => cleanups.forEach(fn => fn());
}
