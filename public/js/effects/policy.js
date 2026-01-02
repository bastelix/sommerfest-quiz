import { resolveNamespaceDesign } from '../components/namespace-design.js';

// Centralized effects governance. Effects are resolved per-namespace and
// activated only through declarative markers without touching page data.
// Supported effect keys are whitelisted via EFFECT_TYPES; profiles outside
// this list will be ignored by the behavior layer.
export const EFFECT_TYPES = {
  REVEAL: 'reveal',
  HERO_INTRO: 'heroIntro',
  SLIDER: 'slider',
  HOVER: 'hoverMicroInteractions'
};

export const EFFECTS_PROFILES = {
  'quizrace.marketing': {
    [EFFECT_TYPES.REVEAL]: { enabled: true, preset: 'lifted' },
    [EFFECT_TYPES.HERO_INTRO]: { enabled: true, preset: 'punchy' },
    [EFFECT_TYPES.SLIDER]: { enabled: true, preset: 'momentum' },
    [EFFECT_TYPES.HOVER]: { enabled: true, preset: 'playful' }
  },
  'quizrace.calm': {
    [EFFECT_TYPES.REVEAL]: { enabled: true, preset: 'gentle' },
    [EFFECT_TYPES.HERO_INTRO]: { enabled: false, preset: 'static' },
    [EFFECT_TYPES.SLIDER]: { enabled: true, preset: 'steady' },
    [EFFECT_TYPES.HOVER]: { enabled: true, preset: 'soft' }
  },
  'calserver.professional': {
    [EFFECT_TYPES.REVEAL]: { enabled: true, preset: 'polished' },
    [EFFECT_TYPES.HERO_INTRO]: { enabled: false, preset: 'static' },
    [EFFECT_TYPES.SLIDER]: { enabled: false, preset: 'manual' },
    [EFFECT_TYPES.HOVER]: { enabled: true, preset: 'minimal' }
  }
};

export const EFFECTS_BY_NAMESPACE = {
  quizrace: 'quizrace.marketing',
  calserver: 'calserver.professional'
};

function normalizeNamespace(namespace) {
  if (!namespace) return 'default';
  return String(namespace).trim().toLowerCase();
}

export function resolveProfileName(namespace) {
  const normalized = normalizeNamespace(namespace);
  const designProfile = resolveDesignProfile(normalized);
  if (designProfile) {
    return designProfile;
  }
  return EFFECTS_BY_NAMESPACE[normalized] || null;
}

export function resolveEffectsProfile(namespace) {
  const profileName = resolveProfileName(namespace);
  if (profileName && EFFECTS_PROFILES[profileName]) {
    return { profileName, profile: EFFECTS_PROFILES[profileName] };
  }
  return null;
}

function resolveDesignProfile(namespace) {
  const design = resolveNamespaceDesign(namespace);
  const profile = design?.effects?.effectsProfile || design?.effectsProfile;
  if (!profile || typeof profile !== 'string') {
    return null;
  }

  const normalized = profile.trim();

  return normalized !== '' ? normalized : null;
}

export function reduceProfileIntensity(profile) {
  if (!profile) return profile;
  const clone = {};
  Object.entries(profile).forEach(([effect, config]) => {
    const enabled = !!config?.enabled;
    clone[effect] = {
      enabled,
      preset: config?.preset || 'default',
      previewSafe: enabled
    };
  });
  return clone;
}
