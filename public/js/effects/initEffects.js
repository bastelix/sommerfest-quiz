import { EFFECT_TYPES, resolveEffectsProfile, reduceProfileIntensity } from './policy.js';
import { initReveal } from './reveal.js';
import { initHeroIntro } from './heroIntro.js';
import { initSlider } from './slider.js';
import { initHoverMicroInteractions } from './hoverMicroInteractions.js';

const MODES = {
  FRONTEND: 'frontend',
  PREVIEW: 'preview',
  DESIGN_PREVIEW: 'design-preview',
  EDIT: 'edit'
};

/**
 * Behavior governance entry point.
 *
 * Resolves a namespace-scoped effects profile and applies only whitelisted
 * handlers to DOM nodes marked via data attributes. No timing or per-block
 * configuration is read from page data; everything flows from namespace
 * policy and runtime context (mode, device, accessibility preferences).
 */
export function initEffects(root = document, context = {}) {
  const scope = root || document;
  const namespace = normalizeNamespace(context.namespace);
  const mode = normalizeMode(context.mode);
  const device = normalizeDevice(context.device);
  const prefersReducedMotion = context.prefersReducedMotion === true || detectReducedMotion();

  const policy = resolveEffectsProfile(namespace);
  if (!policy) {
    return { profileName: null, destroy: () => {} };
  }

  const disableAllMotion = prefersReducedMotion || mode === MODES.DESIGN_PREVIEW || mode === MODES.EDIT;
  const profile = disableAllMotion
    ? null
    : mode === MODES.PREVIEW
      ? reduceProfileIntensity(policy.profile)
      : policy.profile;

  const options = {
    reduceMotion: prefersReducedMotion || mode === MODES.DESIGN_PREVIEW || mode === MODES.EDIT,
    mode,
    device
  };

  const teardowns = [];

  if (profile?.[EFFECT_TYPES.REVEAL]?.enabled) {
    const stop = initReveal(scope, profile, options);
    if (typeof stop === 'function') teardowns.push(stop);
  }
  if (profile?.[EFFECT_TYPES.HERO_INTRO]?.enabled) {
    const stop = initHeroIntro(scope, profile, options);
    if (typeof stop === 'function') teardowns.push(stop);
  }
  if (profile?.[EFFECT_TYPES.SLIDER]?.enabled) {
    const stop = initSlider(scope, profile, options);
    if (typeof stop === 'function') teardowns.push(stop);
  }
  if (profile?.[EFFECT_TYPES.HOVER]?.enabled) {
    const stop = initHoverMicroInteractions(scope, profile, options);
    if (typeof stop === 'function') teardowns.push(stop);
  }

  return {
    profileName: policy.profileName,
    destroy: () => teardowns.forEach(stop => stop())
  };
}

function normalizeNamespace(namespace) {
  if (namespace) return String(namespace).trim().toLowerCase();
  const fromDocument = (typeof document !== 'undefined' && document.documentElement?.dataset?.namespace)
    ? document.documentElement.dataset.namespace
    : null;
  return fromDocument ? String(fromDocument).trim().toLowerCase() : 'default';
}

function normalizeMode(mode) {
  const normalized = String(mode || '').toLowerCase();
  if (normalized === MODES.PREVIEW) return MODES.PREVIEW;
  if (normalized === MODES.DESIGN_PREVIEW || normalized === 'design') return MODES.DESIGN_PREVIEW;
  if (normalized === MODES.EDIT) return MODES.EDIT;
  return MODES.FRONTEND;
}

function normalizeDevice(device) {
  const normalized = String(device || '').toLowerCase();
  if (normalized === 'mobile' || normalized === 'desktop') {
    return normalized;
  }
  if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    try {
      return window.matchMedia('(max-width: 959px)').matches ? 'mobile' : 'desktop';
    } catch (e) {
      return 'desktop';
    }
  }
  return 'desktop';
}

function detectReducedMotion() {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return false;
  }
  try {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  } catch (e) {
    return false;
  }
}
