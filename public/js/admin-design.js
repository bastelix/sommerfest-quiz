(function () {
  const editor = document.getElementById('design-editor');
  if (!editor) {
    return;
  }

  const parseJson = (raw, fallback) => {
    if (!raw) return fallback;
    try {
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch (error) {
      console.error('Unable to parse design token payload', error);
      return fallback;
    }
  };

  const defaults = parseJson(editor.dataset.defaultTokens, {});
  const current = parseJson(editor.dataset.currentTokens, defaults);
  const effectsDefaults = parseJson(editor.dataset.effectsDefaults, {});
  const effectsCurrent = parseJson(editor.dataset.effectsCurrent, effectsDefaults);
  const effectsProfiles = parseJson(editor.dataset.effectsMap, {});
  const marketingThemeMap = parseJson(editor.dataset.marketingThemeMap, null);
  const preview = document.getElementById('design-preview');
  const isReadOnly = editor.dataset.readOnly === '1';
  const isEffectsReadOnly = editor.dataset.effectsReadOnly === '1';
  const sliderSuggestionMap = {
    'calserver.professional': 'static',
    'quizrace.calm': 'calm',
    'quizrace.marketing': 'marketing',
  };
  const marketingSchemes = {
    aurora: {
      primary: '#0ea5e9',
      accent: '#22c55e',
      surface: '#f8fafc',
      surfaceMuted: '#e2e8f0',
      surfaceDark: '#0f172a',
      surfaceMutedDark: '#1e293b',
      background: '#eef2ff',
      backgroundDark: '#020617',
      onAccent: '#ffffff',
      textOnSurface: '#0f172a',
      textOnBackground: '#0f172a',
      textMutedOnSurface: '#475569',
      textMutedOnBackground: '#475569',
      textOnSurfaceDark: '#f8fafc',
      textOnBackgroundDark: '#f8fafc',
      textMutedOnSurfaceDark: '#cbd5e1',
      textMutedOnBackgroundDark: '#cbd5e1',
      marketingInk: '#0b1728',
      surfaceGlass: 'rgba(255, 255, 255, 0.96)',
      surfaceGlassDark: 'rgba(15, 36, 27, 0.96)',
      surfaceAccentSoft: 'rgba(255, 255, 255, 0.08)',
      borderLight: 'rgba(255, 255, 255, 0.18)',
      ringStrong: 'rgba(0, 0, 0, 0.6)',
      ringStrongDark: 'rgba(255, 255, 255, 0.72)',
      overlaySoft: 'rgba(15, 23, 42, 0.12)',
      overlayStrong: 'rgba(0, 0, 0, 0.18)',
      overlayHero: 'rgba(0, 0, 0, 0.24)',
      shadowSoft: '0 6px 24px rgba(0, 0, 0, 0.05)',
      shadowDark: '0 6px 24px rgba(0, 0, 0, 0.35)',
      shadowPanel: '0 16px 32px rgba(15, 23, 42, 0.08)',
      shadowCardBase: '0 22px 48px rgba(15, 23, 42, 0.09)',
      shadowCardSoftBase: '0 16px 36px rgba(0, 0, 0, 0.05)',
      shadowCard: 'var(--marketing-shadow-card-base)',
      shadowAccent: '0 28px 60px rgba(0, 0, 0, 0.18)',
      shadowCardSoft: 'var(--marketing-shadow-card-soft-base)',
      shadowCardHover: '0 12px 28px rgba(0, 0, 0, 0.08)',
      shadowHeroMockup: '0 20px 60px rgba(2, 6, 23, 0.45)',
      shadowPill: '0 12px 24px -22px rgba(15, 23, 42, 0.4)',
      shadowCallout: '0 16px 32px rgba(0, 0, 0, 0.18)',
      shadowStat: '0 18px 38px rgba(15, 23, 42, 0.08)',
      shadowStatAccent: 'rgba(15, 23, 42, 0.18)',
      linkContrastLight: '#0000ee',
      linkContrastDark: '#ffff00',
      topbarTextContrastLight: '#0a0a0a',
      topbarTextContrastDark: '#ffffff',
      topbarDropBgContrastLight: '#ffffff',
      topbarDropBgContrastDark: '#000000',
      topbarBtnBorderContrastLight: '#000000',
      topbarBtnBorderContrastDark: '#ffffff',
      topbarFocusRingContrastLight: 'rgba(0, 120, 255, 0.6)',
      topbarFocusRingContrastDark: 'rgba(140, 200, 255, 0.8)',
      danger500: '#ff6b6b',
      danger600: '#ff4c4c',
      white: '#ffffff',
      black: '#000000',
      blackRgb: '0 0 0',
    },
    sunset: {
      primary: '#f97316',
      accent: '#ec4899',
      surface: '#fff7ed',
      surfaceMuted: '#fed7aa',
      surfaceDark: '#0f172a',
      surfaceMutedDark: '#1e293b',
      background: '#ffedd5',
      backgroundDark: '#020617',
      onAccent: '#1f2937',
      textOnSurface: '#1f2937',
      textOnBackground: '#1f2937',
      textMutedOnSurface: '#6b7280',
      textMutedOnBackground: '#6b7280',
      textOnSurfaceDark: '#f8fafc',
      textOnBackgroundDark: '#f8fafc',
      textMutedOnSurfaceDark: '#cbd5e1',
      textMutedOnBackgroundDark: '#cbd5e1',
      marketingInk: '#0b1728',
      surfaceGlass: 'rgba(255, 255, 255, 0.96)',
      surfaceGlassDark: 'rgba(15, 36, 27, 0.96)',
      surfaceAccentSoft: 'rgba(255, 255, 255, 0.08)',
      borderLight: 'rgba(255, 255, 255, 0.18)',
      ringStrong: 'rgba(0, 0, 0, 0.6)',
      ringStrongDark: 'rgba(255, 255, 255, 0.72)',
      overlaySoft: 'rgba(15, 23, 42, 0.12)',
      overlayStrong: 'rgba(0, 0, 0, 0.18)',
      overlayHero: 'rgba(0, 0, 0, 0.24)',
      shadowSoft: '0 6px 24px rgba(0, 0, 0, 0.05)',
      shadowDark: '0 6px 24px rgba(0, 0, 0, 0.35)',
      shadowPanel: '0 16px 32px rgba(15, 23, 42, 0.08)',
      shadowCardBase: '0 22px 48px rgba(15, 23, 42, 0.09)',
      shadowCardSoftBase: '0 16px 36px rgba(0, 0, 0, 0.05)',
      shadowCard: 'var(--marketing-shadow-card-base)',
      shadowAccent: '0 28px 60px rgba(0, 0, 0, 0.18)',
      shadowCardSoft: 'var(--marketing-shadow-card-soft-base)',
      shadowCardHover: '0 12px 28px rgba(0, 0, 0, 0.08)',
      shadowHeroMockup: '0 20px 60px rgba(2, 6, 23, 0.45)',
      shadowPill: '0 12px 24px -22px rgba(15, 23, 42, 0.4)',
      shadowCallout: '0 16px 32px rgba(0, 0, 0, 0.18)',
      shadowStat: '0 18px 38px rgba(15, 23, 42, 0.08)',
      shadowStatAccent: 'rgba(15, 23, 42, 0.18)',
      linkContrastLight: '#0000ee',
      linkContrastDark: '#ffff00',
      topbarTextContrastLight: '#0a0a0a',
      topbarTextContrastDark: '#ffffff',
      topbarDropBgContrastLight: '#ffffff',
      topbarDropBgContrastDark: '#000000',
      topbarBtnBorderContrastLight: '#000000',
      topbarBtnBorderContrastDark: '#ffffff',
      topbarFocusRingContrastLight: 'rgba(0, 120, 255, 0.6)',
      topbarFocusRingContrastDark: 'rgba(140, 200, 255, 0.8)',
      danger500: '#ff6b6b',
      danger600: '#ff4c4c',
      white: '#ffffff',
      black: '#000000',
      blackRgb: '0 0 0',
    },
    midnight: {
      primary: '#6366f1',
      accent: '#14b8a6',
      surface: '#0f172a',
      surfaceMuted: '#1e293b',
      surfaceDark: '#0f172a',
      surfaceMutedDark: '#1e293b',
      background: '#020617',
      backgroundDark: '#020617',
      onAccent: '#f8fafc',
      textOnSurface: '#e2e8f0',
      textOnBackground: '#e2e8f0',
      textMutedOnSurface: '#94a3b8',
      textMutedOnBackground: '#94a3b8',
      textOnSurfaceDark: '#f8fafc',
      textOnBackgroundDark: '#f8fafc',
      textMutedOnSurfaceDark: '#cbd5e1',
      textMutedOnBackgroundDark: '#cbd5e1',
      marketingInk: '#0b1728',
      surfaceGlass: 'rgba(255, 255, 255, 0.96)',
      surfaceGlassDark: 'rgba(15, 36, 27, 0.96)',
      surfaceAccentSoft: 'rgba(255, 255, 255, 0.08)',
      borderLight: 'rgba(255, 255, 255, 0.18)',
      ringStrong: 'rgba(0, 0, 0, 0.6)',
      ringStrongDark: 'rgba(255, 255, 255, 0.72)',
      overlaySoft: 'rgba(15, 23, 42, 0.12)',
      overlayStrong: 'rgba(0, 0, 0, 0.18)',
      overlayHero: 'rgba(0, 0, 0, 0.24)',
      shadowSoft: '0 6px 24px rgba(0, 0, 0, 0.05)',
      shadowDark: '0 6px 24px rgba(0, 0, 0, 0.35)',
      shadowPanel: '0 16px 32px rgba(15, 23, 42, 0.08)',
      shadowCardBase: '0 22px 48px rgba(15, 23, 42, 0.09)',
      shadowCardSoftBase: '0 16px 36px rgba(0, 0, 0, 0.05)',
      shadowCard: 'var(--marketing-shadow-card-base)',
      shadowAccent: '0 28px 60px rgba(0, 0, 0, 0.18)',
      shadowCardSoft: 'var(--marketing-shadow-card-soft-base)',
      shadowCardHover: '0 12px 28px rgba(0, 0, 0, 0.08)',
      shadowHeroMockup: '0 20px 60px rgba(2, 6, 23, 0.45)',
      shadowPill: '0 12px 24px -22px rgba(15, 23, 42, 0.4)',
      shadowCallout: '0 16px 32px rgba(0, 0, 0, 0.18)',
      shadowStat: '0 18px 38px rgba(15, 23, 42, 0.08)',
      shadowStatAccent: 'rgba(15, 23, 42, 0.18)',
      linkContrastLight: '#0000ee',
      linkContrastDark: '#ffff00',
      topbarTextContrastLight: '#0a0a0a',
      topbarTextContrastDark: '#ffffff',
      topbarDropBgContrastLight: '#ffffff',
      topbarDropBgContrastDark: '#000000',
      topbarBtnBorderContrastLight: '#000000',
      topbarBtnBorderContrastDark: '#ffffff',
      topbarFocusRingContrastLight: 'rgba(0, 120, 255, 0.6)',
      topbarFocusRingContrastDark: 'rgba(140, 200, 255, 0.8)',
      danger500: '#ff6b6b',
      danger600: '#ff4c4c',
      white: '#ffffff',
      black: '#000000',
      blackRgb: '0 0 0',
    },
    monochrome: {
      primary: '#111111',
      accent: '#1f1f1f',
      surface: '#ffffff',
      surfaceMuted: '#f2f2f2',
      surfaceDark: '#111111',
      surfaceMutedDark: '#1c1c1c',
      background: '#f9f9f9',
      backgroundDark: '#0a0a0a',
      onAccent: '#ffffff',
      textOnSurface: '#111111',
      textOnBackground: '#111111',
      textMutedOnSurface: '#4b4b4b',
      textMutedOnBackground: '#4b4b4b',
      textOnSurfaceDark: '#f5f5f5',
      textOnBackgroundDark: '#f5f5f5',
      textMutedOnSurfaceDark: '#bdbdbd',
      textMutedOnBackgroundDark: '#bdbdbd',
      marketingInk: '#0b1728',
      surfaceGlass: 'rgba(255, 255, 255, 0.96)',
      surfaceGlassDark: 'rgba(15, 36, 27, 0.96)',
      surfaceAccentSoft: 'rgba(255, 255, 255, 0.08)',
      borderLight: 'rgba(255, 255, 255, 0.18)',
      ringStrong: 'rgba(0, 0, 0, 0.6)',
      ringStrongDark: 'rgba(255, 255, 255, 0.72)',
      overlaySoft: 'rgba(15, 23, 42, 0.12)',
      overlayStrong: 'rgba(0, 0, 0, 0.18)',
      overlayHero: 'rgba(0, 0, 0, 0.24)',
      shadowSoft: '0 6px 24px rgba(0, 0, 0, 0.05)',
      shadowDark: '0 6px 24px rgba(0, 0, 0, 0.35)',
      shadowPanel: '0 16px 32px rgba(15, 23, 42, 0.08)',
      shadowCardBase: '0 22px 48px rgba(15, 23, 42, 0.09)',
      shadowCardSoftBase: '0 16px 36px rgba(0, 0, 0, 0.05)',
      shadowCard: 'var(--marketing-shadow-card-base)',
      shadowAccent: '0 28px 60px rgba(0, 0, 0, 0.18)',
      shadowCardSoft: 'var(--marketing-shadow-card-soft-base)',
      shadowCardHover: '0 12px 28px rgba(0, 0, 0, 0.08)',
      shadowHeroMockup: '0 20px 60px rgba(2, 6, 23, 0.45)',
      shadowPill: '0 12px 24px -22px rgba(15, 23, 42, 0.4)',
      shadowCallout: '0 16px 32px rgba(0, 0, 0, 0.18)',
      shadowStat: '0 18px 38px rgba(15, 23, 42, 0.08)',
      shadowStatAccent: 'rgba(15, 23, 42, 0.18)',
      linkContrastLight: '#0000ee',
      linkContrastDark: '#ffff00',
      topbarTextContrastLight: '#0a0a0a',
      topbarTextContrastDark: '#ffffff',
      topbarDropBgContrastLight: '#ffffff',
      topbarDropBgContrastDark: '#000000',
      topbarBtnBorderContrastLight: '#000000',
      topbarBtnBorderContrastDark: '#ffffff',
      topbarFocusRingContrastLight: 'rgba(0, 120, 255, 0.6)',
      topbarFocusRingContrastDark: 'rgba(140, 200, 255, 0.8)',
      danger500: '#ff6b6b',
      danger600: '#ff4c4c',
      white: '#ffffff',
      black: '#000000',
      blackRgb: '0 0 0',
    },
  };
  const marketingTokenKeys = [
    '--marketing-primary',
    '--marketing-accent',
    '--marketing-secondary',
    '--marketing-surface',
    '--marketing-surface-muted',
    '--marketing-background',
    '--marketing-surface-dark',
    '--marketing-surface-muted-dark',
    '--marketing-background-dark',
    '--marketing-on-accent',
    '--marketing-text',
    '--marketing-text-on-surface',
    '--marketing-text-on-background',
    '--marketing-text-muted-on-surface',
    '--marketing-text-muted-on-background',
    '--marketing-text-on-surface-dark',
    '--marketing-text-on-background-dark',
    '--marketing-text-muted-on-surface-dark',
    '--marketing-text-muted-on-background-dark',
    '--marketing-ink',
    '--marketing-surface-glass',
    '--marketing-surface-glass-dark',
    '--marketing-surface-accent-soft',
    '--marketing-border-light',
    '--marketing-ring-strong',
    '--marketing-ring-strong-dark',
    '--marketing-overlay-soft',
    '--marketing-overlay-strong',
    '--marketing-overlay-hero',
    '--marketing-shadow-soft',
    '--marketing-shadow-dark',
    '--marketing-shadow-panel',
    '--marketing-shadow-card-base',
    '--marketing-shadow-card-soft-base',
    '--marketing-shadow-card',
    '--marketing-shadow-accent',
    '--marketing-shadow-card-soft',
    '--marketing-shadow-card-hover',
    '--marketing-shadow-hero-mockup',
    '--marketing-shadow-pill',
    '--marketing-shadow-callout',
    '--marketing-shadow-stat',
    '--marketing-shadow-stat-accent',
    '--marketing-link-contrast-light',
    '--marketing-link-contrast-dark',
    '--marketing-topbar-text-contrast-light',
    '--marketing-topbar-text-contrast-dark',
    '--marketing-topbar-drop-bg-contrast-light',
    '--marketing-topbar-drop-bg-contrast-dark',
    '--marketing-topbar-btn-border-contrast-light',
    '--marketing-topbar-btn-border-contrast-dark',
    '--marketing-topbar-focus-ring-contrast-light',
    '--marketing-topbar-focus-ring-contrast-dark',
    '--marketing-danger-500',
    '--marketing-danger-600',
    '--marketing-white',
    '--marketing-black',
    '--marketing-black-rgb',
  ];
  const activeTab = editor.dataset.activeTab === 'behavior' ? 'behavior' : 'appearance';
  const marketingSchemeTokens = marketingThemeMap && Object.keys(marketingThemeMap).length
    ? marketingThemeMap
    : marketingSchemes;

  const resolveTokens = () => ({
    brand: { ...(defaults.brand || {}), ...(current.brand || {}) },
    layout: { ...(defaults.layout || {}), ...(current.layout || {}) },
    typography: { ...(defaults.typography || {}), ...(current.typography || {}) },
    components: { ...(defaults.components || {}), ...(current.components || {}) },
  });

  const resolveEffects = () => {
    const merged = { ...(effectsDefaults || {}), ...(effectsCurrent || {}) };
    const effectsProfile = merged.effectsProfile || effectsDefaults.effectsProfile || 'calserver.professional';
    const suggestion = sliderSuggestionMap[effectsProfile] || effectsDefaults.sliderProfile || 'static';
    const sliderProfile = ['static', 'calm', 'marketing'].includes(merged.sliderProfile)
      ? merged.sliderProfile
      : suggestion;

    return { effectsProfile, sliderProfile };
  };

  const updateMeta = (selector, value) => {
    const target = document.querySelector(`[data-preview-meta="${selector}"]`);
    if (target) {
      target.textContent = value;
    }
  };

  const updateTokenPreviewText = (key, value) => {
    const target = document.querySelector(`[data-token-preview="${key}"]`);
    if (target) {
      target.textContent = value;
    }
  };

  const normalizeColorValue = value => (value || '').trim().toLowerCase();

  const resolveBrandTokensFromScheme = schemeKey => {
    const scheme = marketingSchemeTokens[schemeKey];
    if (!scheme) {
      return null;
    }
    const primary = scheme.primary;
    const accent = scheme.accent || scheme.primary;
    const secondary = scheme.secondary || scheme.accent || scheme.primary;
    if (!primary && !accent && !secondary) {
      return null;
    }
    return {
      primary: primary || '',
      accent: accent || primary || '',
      secondary: secondary || accent || primary || '',
    };
  };

  const brandTokensMatch = (brand, target) => {
    if (!brand || !target) {
      return false;
    }
    return ['primary', 'accent', 'secondary'].every(key => (
      normalizeColorValue(brand[key]) === normalizeColorValue(target[key])
    ));
  };

  let refreshContrastChecks = () => {};
  let brandIsAuto = false;

  const parseColor = value => {
    if (!value) return null;
    const trimmed = value.trim();
    if (trimmed === '' || trimmed.startsWith('var(')) {
      return null;
    }
    const hexMatch = trimmed.match(/^#([0-9a-f]{3,8})$/i);
    if (hexMatch) {
      const hex = hexMatch[1];
      if (hex.length === 3 || hex.length === 4) {
        const r = parseInt(hex[0] + hex[0], 16);
        const g = parseInt(hex[1] + hex[1], 16);
        const b = parseInt(hex[2] + hex[2], 16);
        return { r, g, b };
      }
      if (hex.length >= 6) {
        const r = parseInt(hex.slice(0, 2), 16);
        const g = parseInt(hex.slice(2, 4), 16);
        const b = parseInt(hex.slice(4, 6), 16);
        return { r, g, b };
      }
    }
    const rgbMatch = trimmed.match(/^rgba?\((.+)\)$/i);
    if (rgbMatch) {
      const parts = rgbMatch[1].split(/[\s,\/]+/).filter(Boolean);
      if (parts.length >= 3) {
        const toChannel = part => {
          if (part.endsWith('%')) {
            return Math.round((parseFloat(part) / 100) * 255);
          }
          return Math.round(parseFloat(part));
        };
        const r = toChannel(parts[0]);
        const g = toChannel(parts[1]);
        const b = toChannel(parts[2]);
        if ([r, g, b].every(channel => !Number.isNaN(channel))) {
          return { r, g, b };
        }
      }
    }
    return null;
  };

  const formatHex = ({ r, g, b }) => {
    const toHex = channel => Math.max(0, Math.min(255, Math.round(channel))).toString(16).padStart(2, '0');
    return `#${toHex(r)}${toHex(g)}${toHex(b)}`.toLowerCase();
  };

  const relativeLuminance = ({ r, g, b }) => {
    const srgb = [r, g, b].map(channel => {
      const normalized = channel / 255;
      return normalized <= 0.03928
        ? normalized / 12.92
        : Math.pow((normalized + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * srgb[0] + 0.7152 * srgb[1] + 0.0722 * srgb[2];
  };

  const contrastRatio = (foreground, background) => {
    const lum1 = relativeLuminance(foreground);
    const lum2 = relativeLuminance(background);
    const lighter = Math.max(lum1, lum2);
    const darker = Math.min(lum1, lum2);
    return (lighter + 0.05) / (darker + 0.05);
  };

  const resolveCssValue = (style, names) => {
    for (const name of names) {
      const value = style.getPropertyValue(name).trim();
      if (value) {
        return value;
      }
    }
    return null;
  };

  const initContrastControls = () => {
    const panel = editor.querySelector('[data-contrast-panel]');
    if (!panel || !preview) return;

    const inputMap = {};
    editor.querySelectorAll('[data-contrast-input]').forEach(input => {
      if (input instanceof HTMLInputElement && input.dataset.contrastInput) {
        inputMap[input.dataset.contrastInput] = input;
      }
    });

    const targets = {
      'text-on-surface': {
        textVars: ['--marketing-text-on-surface', '--text-on-surface', '--marketing-text'],
        backgroundVars: ['--marketing-surface', '--surface', '--bg-subtle', '--preview-surface-bg'],
        applyVars: ['--marketing-text-on-surface', '--text-on-surface'],
      },
      'text-on-background': {
        textVars: ['--marketing-text-on-background', '--text-on-background', '--marketing-text'],
        backgroundVars: ['--marketing-background', '--surface-page', '--bg-page', '--marketing-surface'],
        applyVars: ['--marketing-text-on-background', '--text-on-background'],
      },
      'text-on-primary': {
        textVars: ['--marketing-text-on-primary', '--marketing-on-accent', '--text-on-primary'],
        backgroundVars: ['--marketing-primary', '--brand-primary'],
        applyVars: ['--marketing-on-accent', '--marketing-text-on-primary', '--text-on-primary'],
      },
    };

    const applyOverridesFromInputs = () => {
      Object.entries(inputMap).forEach(([key, input]) => {
        if (!input.value || input.disabled) {
          return;
        }
        const config = targets[key];
        if (!config) return;
        config.applyVars.forEach(variable => {
          preview.style.setProperty(variable, input.value);
        });
      });
    };

    const updateRow = (row, config) => {
      const style = getComputedStyle(preview);
      const textValue = resolveCssValue(style, config.textVars);
      const backgroundValue = resolveCssValue(style, config.backgroundVars);
      const ratioElement = row.querySelector('[data-contrast-ratio]');
      const aaBadge = row.querySelector('[data-contrast-aa]');
      const aaaBadge = row.querySelector('[data-contrast-aaa]');
      const fixButton = row.querySelector('[data-contrast-fix]');

      const textColor = parseColor(textValue);
      const backgroundColor = parseColor(backgroundValue);

      if (!textColor || !backgroundColor) {
        if (ratioElement) ratioElement.textContent = '–';
        if (aaBadge) {
          aaBadge.textContent = 'AA n/a';
          aaBadge.classList.remove('design-contrast__badge--warn');
          aaBadge.classList.add('design-contrast__badge--muted');
        }
        if (aaaBadge) {
          aaaBadge.textContent = 'AAA n/a';
          aaaBadge.classList.remove('design-contrast__badge--warn');
          aaaBadge.classList.add('design-contrast__badge--muted');
        }
        row.classList.remove('design-contrast__row--warn');
        if (fixButton instanceof HTMLButtonElement) {
          fixButton.disabled = true;
        }
        return;
      }

      const ratio = contrastRatio(textColor, backgroundColor);
      if (ratioElement) {
        ratioElement.textContent = `${ratio.toFixed(2)}:1`;
      }

      const meetsAA = ratio >= 4.5;
      const meetsAAA = ratio >= 7;

      if (aaBadge) {
        aaBadge.textContent = meetsAA ? 'AA ✓' : 'AA ✕';
        aaBadge.classList.toggle('design-contrast__badge--warn', !meetsAA);
        aaBadge.classList.toggle('design-contrast__badge--muted', false);
      }
      if (aaaBadge) {
        aaaBadge.textContent = meetsAAA ? 'AAA ✓' : 'AAA ✕';
        aaaBadge.classList.toggle('design-contrast__badge--warn', !meetsAAA);
        aaaBadge.classList.toggle('design-contrast__badge--muted', false);
      }
      row.classList.toggle('design-contrast__row--warn', !meetsAA);
      if (fixButton instanceof HTMLButtonElement) {
        fixButton.disabled = meetsAA;
      }
    };

    const rows = Array.from(panel.querySelectorAll('[data-contrast-row]'));
    rows.forEach(row => {
      const key = row.dataset.contrastRow;
      const config = key ? targets[key] : null;
      if (!config) return;
      const fixButton = row.querySelector('[data-contrast-fix]');
      if (fixButton instanceof HTMLButtonElement) {
        fixButton.addEventListener('click', () => {
          const style = getComputedStyle(preview);
          const backgroundValue = resolveCssValue(style, config.backgroundVars);
          const backgroundColor = parseColor(backgroundValue);
          if (!backgroundColor) {
            return;
          }
          const black = { r: 0, g: 0, b: 0 };
          const white = { r: 255, g: 255, b: 255 };
          const blackRatio = contrastRatio(black, backgroundColor);
          const whiteRatio = contrastRatio(white, backgroundColor);
          const chosen = blackRatio >= whiteRatio ? black : white;
          const hex = formatHex(chosen);
          config.applyVars.forEach(variable => {
            preview.style.setProperty(variable, hex);
          });
          const input = inputMap[key];
          if (input instanceof HTMLInputElement) {
            input.value = hex;
            input.disabled = false;
          }
          refreshContrastChecks();
        });
      }
    });

    refreshContrastChecks = () => {
      applyOverridesFromInputs();
      rows.forEach(row => {
        const key = row.dataset.contrastRow;
        const config = key ? targets[key] : null;
        if (config) {
          updateRow(row, config);
        }
      });
    };

    refreshContrastChecks();
  };

  const applyTokensToPreview = () => {
    if (!preview) return;
    const tokens = resolveTokens();
    const brand = tokens.brand;
    const layout = tokens.layout;
    const typography = tokens.typography;
    const components = tokens.components;

    const primary = brand.primary || '#1e87f0';
    const accent = brand.accent || brand.primary || '#f97316';
    const secondary = brand.secondary || brand.accent || brand.primary || '#f97316';

    preview.style.setProperty('--brand-primary', primary);
    preview.style.setProperty('--brand-accent', accent);
    preview.style.setProperty('--brand-secondary', secondary);
    preview.style.setProperty('--accent-primary', primary);
    preview.style.setProperty('--accent-secondary', secondary);
    preview.dataset.layoutProfile = layout.profile || 'standard';
    preview.dataset.typographyPreset = typography.preset || 'modern';
    preview.dataset.cardStyle = components.cardStyle || 'rounded';
    preview.dataset.buttonStyle = components.buttonStyle || 'filled';

    updateMeta('layout', preview.dataset.layoutProfile);
    updateMeta('typography', preview.dataset.typographyPreset);
    refreshContrastChecks();
  };

  const applyMarketingSchemeToPreview = schemeKey => {
    if (!preview) return;
    const scheme = marketingSchemeTokens[schemeKey];
    if (!scheme) {
      marketingTokenKeys.forEach(token => preview.style.removeProperty(token));
      refreshContrastChecks();
      return;
    }
    const applySchemeToken = (token, value) => {
      if (value) {
        preview.style.setProperty(token, value);
      } else {
        preview.style.removeProperty(token);
      }
    };
    preview.style.setProperty('--marketing-primary', scheme.primary);
    preview.style.setProperty('--marketing-accent', scheme.accent);
    preview.style.setProperty('--marketing-secondary', scheme.accent);
    preview.style.setProperty('--marketing-surface', scheme.surface);
    preview.style.setProperty('--marketing-surface-muted', scheme.surfaceMuted || scheme.surface);
    preview.style.setProperty('--marketing-background', scheme.background);
    if (scheme.surfaceDark) {
      preview.style.setProperty('--marketing-surface-dark', scheme.surfaceDark);
    } else {
      preview.style.removeProperty('--marketing-surface-dark');
    }
    if (scheme.surfaceMutedDark) {
      preview.style.setProperty('--marketing-surface-muted-dark', scheme.surfaceMutedDark);
    } else {
      preview.style.removeProperty('--marketing-surface-muted-dark');
    }
    if (scheme.backgroundDark) {
      preview.style.setProperty('--marketing-background-dark', scheme.backgroundDark);
    } else {
      preview.style.removeProperty('--marketing-background-dark');
    }
    preview.style.setProperty('--marketing-on-accent', scheme.onAccent);
    preview.style.setProperty('--marketing-text', scheme.textOnBackground);
    preview.style.setProperty('--marketing-text-on-surface', scheme.textOnSurface);
    preview.style.setProperty('--marketing-text-on-background', scheme.textOnBackground);
    preview.style.setProperty('--marketing-text-muted-on-surface', scheme.textMutedOnSurface);
    preview.style.setProperty('--marketing-text-muted-on-background', scheme.textMutedOnBackground);
    preview.style.setProperty('--marketing-text-on-surface-dark', scheme.textOnSurfaceDark);
    preview.style.setProperty('--marketing-text-on-background-dark', scheme.textOnBackgroundDark);
    preview.style.setProperty('--marketing-text-muted-on-surface-dark', scheme.textMutedOnSurfaceDark);
    preview.style.setProperty('--marketing-text-muted-on-background-dark', scheme.textMutedOnBackgroundDark);
    applySchemeToken('--marketing-ink', scheme.marketingInk || scheme.ink);
    applySchemeToken('--marketing-surface-glass', scheme.surfaceGlass);
    applySchemeToken('--marketing-surface-glass-dark', scheme.surfaceGlassDark);
    applySchemeToken('--marketing-surface-accent-soft', scheme.surfaceAccentSoft);
    applySchemeToken('--marketing-border-light', scheme.borderLight);
    applySchemeToken('--marketing-ring-strong', scheme.ringStrong);
    applySchemeToken('--marketing-ring-strong-dark', scheme.ringStrongDark);
    applySchemeToken('--marketing-overlay-soft', scheme.overlaySoft);
    applySchemeToken('--marketing-overlay-strong', scheme.overlayStrong);
    applySchemeToken('--marketing-overlay-hero', scheme.overlayHero);
    applySchemeToken('--marketing-shadow-soft', scheme.shadowSoft);
    applySchemeToken('--marketing-shadow-dark', scheme.shadowDark);
    applySchemeToken('--marketing-shadow-panel', scheme.shadowPanel);
    applySchemeToken('--marketing-shadow-card-base', scheme.shadowCardBase);
    applySchemeToken('--marketing-shadow-card-soft-base', scheme.shadowCardSoftBase);
    applySchemeToken('--marketing-shadow-card', scheme.shadowCard);
    applySchemeToken('--marketing-shadow-accent', scheme.shadowAccent);
    applySchemeToken('--marketing-shadow-card-soft', scheme.shadowCardSoft);
    applySchemeToken('--marketing-shadow-card-hover', scheme.shadowCardHover);
    applySchemeToken('--marketing-shadow-hero-mockup', scheme.shadowHeroMockup);
    applySchemeToken('--marketing-shadow-pill', scheme.shadowPill);
    applySchemeToken('--marketing-shadow-callout', scheme.shadowCallout);
    applySchemeToken('--marketing-shadow-stat', scheme.shadowStat);
    applySchemeToken('--marketing-shadow-stat-accent', scheme.shadowStatAccent);
    applySchemeToken('--marketing-link-contrast-light', scheme.linkContrastLight);
    applySchemeToken('--marketing-link-contrast-dark', scheme.linkContrastDark);
    applySchemeToken('--marketing-topbar-text-contrast-light', scheme.topbarTextContrastLight);
    applySchemeToken('--marketing-topbar-text-contrast-dark', scheme.topbarTextContrastDark);
    applySchemeToken('--marketing-topbar-drop-bg-contrast-light', scheme.topbarDropBgContrastLight);
    applySchemeToken('--marketing-topbar-drop-bg-contrast-dark', scheme.topbarDropBgContrastDark);
    applySchemeToken('--marketing-topbar-btn-border-contrast-light', scheme.topbarBtnBorderContrastLight);
    applySchemeToken('--marketing-topbar-btn-border-contrast-dark', scheme.topbarBtnBorderContrastDark);
    applySchemeToken('--marketing-topbar-focus-ring-contrast-light', scheme.topbarFocusRingContrastLight);
    applySchemeToken('--marketing-topbar-focus-ring-contrast-dark', scheme.topbarFocusRingContrastDark);
    applySchemeToken('--marketing-danger-500', scheme.danger500);
    applySchemeToken('--marketing-danger-600', scheme.danger600);
    applySchemeToken('--marketing-white', scheme.white);
    applySchemeToken('--marketing-black', scheme.black);
    applySchemeToken('--marketing-black-rgb', scheme.blackRgb);
    refreshContrastChecks();
  };

  const applyBrandSchemeToInputs = schemeKey => {
    const brandTokens = resolveBrandTokensFromScheme(schemeKey);
    if (!brandTokens) {
      return;
    }
    if (!current.brand) {
      current.brand = {};
    }
    Object.entries(brandTokens).forEach(([key, value]) => {
      current.brand[key] = value;
      const input = editor.querySelector(`[data-token-input][data-group="brand"][data-key="${key}"]`);
      if (input instanceof HTMLInputElement) {
        input.value = value;
      }
      updateTokenPreviewText(`brand.${key}`, value);
    });
    applyTokensToPreview();
  };

  const resolveBrandAutoState = schemeKey => {
    const tokens = resolveTokens();
    const brand = tokens.brand || {};
    const schemeBrand = schemeKey ? resolveBrandTokensFromScheme(schemeKey) : null;
    if (schemeBrand && brandTokensMatch(brand, schemeBrand)) {
      return true;
    }
    return brandTokensMatch(brand, defaults.brand || {});
  };

  const applyEffectsToPreview = () => {
    if (!preview) return;
    const effects = resolveEffects();
    const suggestion = sliderSuggestionMap[effects.effectsProfile] || effectsDefaults.sliderProfile || 'static';
    preview.dataset.effectsProfile = effects.effectsProfile;
    preview.dataset.sliderProfile = effects.sliderProfile;

    const autoSlider = editor.querySelector('[data-slider-auto="true"]');
    if (autoSlider instanceof HTMLInputElement) {
      autoSlider.value = effects.sliderProfile === 'static' ? suggestion : effects.sliderProfile;
    }

    const effectsLabel = effectsProfiles[effects.effectsProfile]?.label || effects.effectsProfile;
    const sliderLabelMap = {
      static: 'Statisch',
      calm: 'Automatisch (sanft)',
      marketing: 'Automatisch (Marketing)',
    };
    updateMeta('effectsProfile', effectsLabel);
    updateMeta('sliderProfile', sliderLabelMap[effects.sliderProfile] || effects.sliderProfile);
  };

  const updateToggleState = (container, attribute, value) => {
    container.querySelectorAll(`[data-${attribute}-choice]`).forEach(button => {
      const isActive = button.dataset[`${attribute}Choice`] === value;
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      button.classList.toggle('uk-button-primary', isActive);
      button.classList.toggle('uk-button-default', !isActive);
    });
  };

  const initThemeToggle = () => {
    const toggle = document.querySelector('[data-theme-toggle]');
    if (!toggle || !preview) return;
    const applyTheme = (theme) => {
      const normalized = theme === 'dark' ? 'dark' : 'light';
      preview.dataset.theme = normalized;
      updateToggleState(toggle, 'theme', normalized);
      refreshContrastChecks();
    };

    toggle.addEventListener('click', event => {
      const button = event.target?.closest?.('[data-theme-choice]');
      if (!button || !toggle.contains(button)) return;
      applyTheme(button.dataset.themeChoice || 'light');
    });

    applyTheme(preview.dataset.theme || 'light');
  };

  const initDeviceToggle = () => {
    const toggle = document.querySelector('[data-device-toggle]');
    if (!toggle || !preview) return;
    const applyDevice = device => {
      const normalized = ['desktop', 'tablet', 'mobile'].includes(device) ? device : 'desktop';
      preview.dataset.device = normalized;
      updateToggleState(toggle, 'device', normalized);
    };

    toggle.addEventListener('click', event => {
      const button = event.target?.closest?.('[data-device-choice]');
      if (!button || !toggle.contains(button)) return;
      applyDevice(button.dataset.deviceChoice || 'desktop');
    });

    applyDevice(preview.dataset.device || 'desktop');
  };

  const initTokenInputs = () => {
    if (isReadOnly) return;
    const inputs = document.querySelectorAll('[data-token-input]');
    inputs.forEach(input => {
      input.addEventListener('input', event => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) && !(target instanceof HTMLSelectElement)) {
          return;
        }
        const group = target.dataset.group;
        const key = target.dataset.key;
        if (!group || !key) return;
        if (!current[group]) {
          current[group] = {};
        }
        current[group][key] = target.value;
        if (group === 'brand') {
          brandIsAuto = false;
        }
        updateTokenPreviewText(`${group}.${key}`, target.value);
        applyTokensToPreview();
      });
    });
  };

  const syncSliderValue = () => {
    const effects = resolveEffects();
    const suggestion = sliderSuggestionMap[effects.effectsProfile] || effectsDefaults.sliderProfile || 'static';
    const autoInput = editor.querySelector('[data-slider-auto="true"]');
    if (autoInput instanceof HTMLInputElement) {
      autoInput.value = effects.sliderProfile === 'static' ? suggestion : effects.sliderProfile;
    }
  };

  const initEffectsInputs = () => {
    if (isEffectsReadOnly) return;
    const inputs = document.querySelectorAll('[data-effects-input]');
    inputs.forEach(input => {
      input.addEventListener('change', event => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
          return;
        }
        const key = target.dataset.effectsInput;
        if (!key) return;

        if (!(key in effectsCurrent)) {
          effectsCurrent[key] = null;
        }
        effectsCurrent[key] = target.value;

        if (key === 'effectsProfile') {
          const suggested = sliderSuggestionMap[target.value] || effectsDefaults.sliderProfile || 'static';
          if (effectsCurrent.sliderProfile !== 'static') {
            effectsCurrent.sliderProfile = suggested;
          }
        }

        syncSliderValue();
        applyEffectsToPreview();
      });
    });
  };

  const initTabs = () => {
    const tabNav = document.querySelector('[data-design-tabs]');
    if (!tabNav) return;
    const panels = Array.from(document.querySelectorAll('[data-design-panel]'));
    const links = Array.from(tabNav.querySelectorAll('[data-design-tab]'));

    const setActiveTab = tab => {
      const normalized = tab === 'behavior' ? 'behavior' : 'appearance';
      links.forEach(link => {
        const isActive = link.dataset.designTab === normalized;
        const parent = link.parentElement;
        if (parent) {
          parent.classList.toggle('uk-active', isActive);
        }
      });
      panels.forEach(panel => {
        const shouldShow = panel.dataset.designPanel === normalized;
        if (shouldShow) {
          panel.removeAttribute('hidden');
        } else {
          panel.setAttribute('hidden', 'hidden');
        }
      });

      const url = new URL(window.location.href);
      url.searchParams.set('tab', normalized);
      window.history.replaceState({}, document.title, url.toString());
    };

    tabNav.addEventListener('click', event => {
      const link = event.target?.closest?.('[data-design-tab]');
      if (!link || !tabNav.contains(link)) {
        return;
      }
      event.preventDefault();
      setActiveTab(link.dataset.designTab);
    });

    setActiveTab(activeTab);
  };

  const initNamespaceSelect = () => {
    const select = document.getElementById('pageNamespaceSelect');
    if (!select) return;
    select.addEventListener('change', () => {
      const url = new URL(window.location.href);
      url.searchParams.set('namespace', select.value || '');
      window.location.href = url.toString();
    });
  };

  const initMarketingSchemeSelect = () => {
    const select = document.querySelector('[data-marketing-scheme-select]');
    if (!select) return;
    const applySelection = value => {
      applyMarketingSchemeToPreview(value);
      if (brandIsAuto && value) {
        applyBrandSchemeToInputs(value);
      }
    };

    if (!isReadOnly) {
      select.addEventListener('change', event => {
        const target = event.target;
        if (!(target instanceof HTMLSelectElement)) return;
        applySelection(target.value);
      });
    }

    const initialScheme = select.value || select.dataset.currentMarketingScheme || '';
    brandIsAuto = resolveBrandAutoState(initialScheme);
    applySelection(initialScheme);
  };

  applyTokensToPreview();
  applyEffectsToPreview();
  initThemeToggle();
  initDeviceToggle();
  initTokenInputs();
  initEffectsInputs();
  initNamespaceSelect();
  initMarketingSchemeSelect();
  initContrastControls();
  initTabs();
})();
