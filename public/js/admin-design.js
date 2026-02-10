import { MARKETING_SCHEMES } from './components/marketing-schemes.js';

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
  const LAYOUT_PROFILES = ['standard', 'wide', 'narrow'];
  const TYPOGRAPHY_PRESETS = ['modern', 'classic', 'tech'];
  const CARD_STYLES = ['rounded', 'square', 'pill'];
  const BUTTON_STYLES = ['filled', 'outline', 'ghost'];
  const resolveFallbackToken = (element, token, fallback) => {
    if (!element) {
      return fallback;
    }
    const value = getComputedStyle(element).getPropertyValue(token).trim();
    return value || fallback;
  };
  const previewDefaults = preview
    ? {
      brandPrimary: resolveFallbackToken(preview, '--brand-primary', '#1e87f0'),
      brandAccent: resolveFallbackToken(preview, '--brand-accent', '#f97316'),
      brandSecondary: resolveFallbackToken(preview, '--brand-secondary', '#f97316'),
      layoutProfile: resolveFallbackToken(preview, '--layout-profile', 'standard'),
      typographyPreset: resolveFallbackToken(preview, '--typography-preset', 'modern'),
      cardStyle: resolveFallbackToken(preview, '--components-card-style', 'rounded'),
      buttonStyle: resolveFallbackToken(preview, '--components-button-style', 'filled'),
    }
    : {
      brandPrimary: '#1e87f0',
      brandAccent: '#f97316',
      brandSecondary: '#f97316',
      layoutProfile: 'standard',
      typographyPreset: 'modern',
      cardStyle: 'rounded',
      buttonStyle: 'filled',
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
    '--marketing-border',
    '--marketing-border-muted',
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
    '--marketing-text-emphasis',
    '--marketing-text-inverse',
    '--marketing-font-stack',
    '--marketing-heading-font-stack',
    '--marketing-heading-weight',
    '--marketing-heading-letter-spacing',
    '--marketing-heading-line-height',
    '--marketing-card-radius',
    '--marketing-button-primary-bg',
    '--marketing-button-primary-text',
    '--marketing-button-primary-border-color',
    '--marketing-button-primary-hover-bg',
    '--marketing-button-primary-focus-bg',
    '--marketing-button-primary-active-bg',
    '--marketing-button-secondary-bg',
    '--marketing-button-secondary-text',
    '--marketing-button-secondary-border-color',
    '--marketing-button-secondary-hover-bg',
    '--marketing-link',
    '--marketing-link-hover',
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
    '--marketing-success',
    '--marketing-warning',
    '--marketing-danger',
    '--marketing-danger-500',
    '--marketing-danger-600',
    '--marketing-white',
    '--marketing-black',
    '--marketing-black-rgb',
  ];
  const activeTab = editor.dataset.activeTab === 'behavior' ? 'behavior' : 'appearance';
  const marketingSchemeTokens = marketingThemeMap && Object.keys(marketingThemeMap).length
    ? marketingThemeMap
    : MARKETING_SCHEMES;

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

  const normalizeTokenValue = (value, allowedValues, fallback) => {
    if (typeof value !== 'string') {
      return fallback;
    }
    const normalized = value.replace(/['"]/g, '').trim().toLowerCase();
    return allowedValues.includes(normalized) ? normalized : fallback;
  };

  const normalizeColorValue = value => (value || '').trim().toLowerCase();

  const syncComponentTokens = () => {
    if (!preview) {
      return;
    }
    const styles = getComputedStyle(preview);
    const layoutProfile = normalizeTokenValue(
      styles.getPropertyValue('--layout-profile'),
      LAYOUT_PROFILES,
      previewDefaults.layoutProfile,
    );
    const typographyPreset = normalizeTokenValue(
      styles.getPropertyValue('--typography-preset'),
      TYPOGRAPHY_PRESETS,
      previewDefaults.typographyPreset,
    );
    const cardStyle = normalizeTokenValue(
      styles.getPropertyValue('--components-card-style'),
      CARD_STYLES,
      previewDefaults.cardStyle,
    );
    const buttonStyle = normalizeTokenValue(
      styles.getPropertyValue('--components-button-style'),
      BUTTON_STYLES,
      previewDefaults.buttonStyle,
    );

    preview.dataset.layoutProfile = layoutProfile;
    preview.dataset.typographyPreset = typographyPreset;
    preview.dataset.cardStyle = cardStyle;
    preview.dataset.buttonStyle = buttonStyle;

    const root = document.documentElement;
    root.dataset.typographyPreset = typographyPreset;
    root.dataset.cardStyle = cardStyle;
    root.dataset.buttonStyle = buttonStyle;

    updateMeta('layout', layoutProfile);
    updateMeta('typography', typographyPreset);
  };

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

  /**
   * Compute the optimal text color (black or white) for maximum WCAG
   * contrast against the given background color.
   *
   * This mirrors the server-side ColorContrastService logic so that the
   * live preview always shows accurate contrast tokens without waiting
   * for a server round-trip.
   */
  const computeOptimalTextColor = backgroundColor => {
    if (!backgroundColor) return null;
    const bg = parseColor(backgroundColor);
    if (!bg) return null;
    const black = { r: 0, g: 0, b: 0 };
    const white = { r: 255, g: 255, b: 255 };
    return contrastRatio(black, bg) >= contrastRatio(white, bg)
      ? '#000000'
      : '#ffffff';
  };

  /**
   * Apply auto-computed contrast tokens to the preview element.
   *
   * Whenever brand colors change this function recalculates the optimal
   * foreground colors for --contrast-text-on-primary, --contrast-text-on-secondary,
   * and --contrast-text-on-accent and sets them as inline CSS properties.
   * This ensures that every section, button, and card in the preview
   * immediately reflects the correct readable text color.
   */
  const applyContrastTokensToPreview = () => {
    if (!preview) return;
    const tokens = resolveTokens();
    const brand = tokens.brand || {};
    const primary = brand.primary || previewDefaults.brandPrimary;
    const accent = brand.accent || brand.primary || previewDefaults.brandAccent;
    const secondary = brand.secondary || brand.accent || brand.primary || previewDefaults.brandSecondary;

    const textOnPrimary = computeOptimalTextColor(primary);
    const textOnSecondary = computeOptimalTextColor(secondary);
    const textOnAccent = computeOptimalTextColor(accent);

    if (textOnPrimary) {
      preview.style.setProperty('--contrast-text-on-primary', textOnPrimary);
      preview.style.setProperty('--text-on-primary', textOnPrimary);
    }
    if (textOnSecondary) {
      preview.style.setProperty('--contrast-text-on-secondary', textOnSecondary);
      preview.style.setProperty('--text-on-secondary', textOnSecondary);
    }
    if (textOnAccent) {
      preview.style.setProperty('--contrast-text-on-accent', textOnAccent);
      preview.style.setProperty('--text-on-accent', textOnAccent);
    }
  };

  const parseColorWithAlpha = value => {
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
        return { r, g, b, a: 1 };
      }
      if (hex.length >= 6) {
        const r = parseInt(hex.slice(0, 2), 16);
        const g = parseInt(hex.slice(2, 4), 16);
        const b = parseInt(hex.slice(4, 6), 16);
        const a = hex.length >= 8 ? parseInt(hex.slice(6, 8), 16) / 255 : 1;
        return { r, g, b, a };
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
        const alpha = parts[3]
          ? (parts[3].endsWith('%') ? parseFloat(parts[3]) / 100 : parseFloat(parts[3]))
          : 1;
        if ([r, g, b].every(channel => !Number.isNaN(channel)) && !Number.isNaN(alpha)) {
          return { r, g, b, a: alpha };
        }
      }
    }
    return null;
  };

  const parseColor = value => {
    const parsed = parseColorWithAlpha(value);
    if (!parsed) {
      return null;
    }
    return { r: parsed.r, g: parsed.g, b: parsed.b };
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

  const resolveElementValue = (selector, property) => {
    if (!preview || !selector) {
      return null;
    }
    const element = preview.querySelector(selector);
    if (!element) {
      return null;
    }
    const value = getComputedStyle(element).getPropertyValue(property).trim();
    return value || null;
  };

  const isTransparentValue = value => {
    if (!value) return false;
    const trimmed = value.trim().toLowerCase();
    if (trimmed === 'transparent') {
      return true;
    }
    const rgbaMatch = trimmed.match(/^rgba\((.+)\)$/);
    if (!rgbaMatch) {
      return false;
    }
    const parts = rgbaMatch[1].split(/[\s,\/]+/).filter(Boolean);
    if (parts.length < 4) {
      return false;
    }
    const alpha = parts[3].endsWith('%')
      ? parseFloat(parts[3]) / 100
      : parseFloat(parts[3]);
    return !Number.isNaN(alpha) && alpha === 0;
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
      'text-on-card': {
        textVars: ['--marketing-text-on-surface', '--text-on-surface', '--marketing-text'],
        backgroundVars: ['--preview-card-bg', '--surface-card', '--bg-section'],
        applyVars: ['--marketing-text-on-surface', '--text-on-surface'],
        textSelector: '.design-preview__card',
        backgroundSelector: '.design-preview__card',
        inputKey: 'text-on-surface',
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
      'text-on-secondary': {
        textVars: ['--marketing-text-on-primary', '--marketing-on-accent', '--text-on-primary'],
        backgroundVars: ['--marketing-button-secondary-bg', '--brand-secondary', '--marketing-secondary', '--brand-accent'],
        applyVars: ['--marketing-on-accent', '--marketing-text-on-primary', '--text-on-primary'],
        textSelector: '.design-preview__button-secondary',
        backgroundSelector: '.design-preview__button-secondary',
        inputKey: 'text-on-primary',
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

    const resolveTargetValue = (config, mode) => {
      const selector = mode === 'text' ? config.textSelector : config.backgroundSelector;
      const property = mode === 'text' ? 'color' : 'background-color';
      const elementValue = resolveElementValue(selector, property);
      if (elementValue && !isTransparentValue(elementValue)) {
        return elementValue;
      }
      const style = getComputedStyle(preview);
      const names = mode === 'text' ? config.textVars : config.backgroundVars;
      return resolveCssValue(style, names);
    };

    const resolveSurfaceBackground = () => {
      const surfaceElement = preview.querySelector('.design-preview__surface') || preview;
      const value = getComputedStyle(surfaceElement).getPropertyValue('background-color').trim();
      return value || null;
    };

    const blendWithSurface = background => {
      if (!background || background.a >= 1) {
        return background ? { r: background.r, g: background.g, b: background.b } : null;
      }
      const surfaceValue = resolveSurfaceBackground();
      const surface = parseColor(surfaceValue);
      if (!surface) {
        return { r: background.r, g: background.g, b: background.b };
      }
      const alpha = Math.max(0, Math.min(1, background.a));
      return {
        r: Math.round(background.r * alpha + surface.r * (1 - alpha)),
        g: Math.round(background.g * alpha + surface.g * (1 - alpha)),
        b: Math.round(background.b * alpha + surface.b * (1 - alpha)),
      };
    };

    const themes = ['light', 'dark'];

    const updateSlot = (row, theme, result) => {
      const ratioElement = row.querySelector(`[data-contrast-ratio="${theme}"]`);
      const aaBadge = row.querySelector(`[data-contrast-aa="${theme}"]`);
      const aaaBadge = row.querySelector(`[data-contrast-aaa="${theme}"]`);

      if (!ratioElement && !aaBadge && !aaaBadge) {
        return;
      }

      if (!result) {
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
        return;
      }

      if (ratioElement) {
        ratioElement.textContent = `${result.ratio.toFixed(2)}:1`;
      }

      if (aaBadge) {
        aaBadge.textContent = result.meetsAA ? 'AA ✓' : 'AA ✕';
        aaBadge.classList.toggle('design-contrast__badge--warn', !result.meetsAA);
        aaBadge.classList.toggle('design-contrast__badge--muted', false);
      }
      if (aaaBadge) {
        aaaBadge.textContent = result.meetsAAA ? 'AAA ✓' : 'AAA ✕';
        aaaBadge.classList.toggle('design-contrast__badge--warn', !result.meetsAAA);
        aaaBadge.classList.toggle('design-contrast__badge--muted', false);
      }
    };

    const computeContrastForTheme = config => {
      const textValue = resolveTargetValue(config, 'text');
      const backgroundValue = resolveTargetValue(config, 'background');
      const textColor = parseColor(textValue);
      const backgroundColor = blendWithSurface(parseColorWithAlpha(backgroundValue));

      if (!textColor || !backgroundColor) {
        return null;
      }

      const ratio = contrastRatio(textColor, backgroundColor);
      return {
        ratio,
        meetsAA: ratio >= 4.5,
        meetsAAA: ratio >= 7,
      };
    };

    const rows = Array.from(panel.querySelectorAll('[data-contrast-row]'));
    rows.forEach(row => {
      const key = row.dataset.contrastRow;
      const config = key ? targets[key] : null;
      if (!config) return;
      const fixButton = row.querySelector('[data-contrast-fix]');
      if (fixButton instanceof HTMLButtonElement) {
        fixButton.addEventListener('click', () => {
          const backgroundValue = resolveTargetValue(config, 'background');
          const backgroundColor = blendWithSurface(parseColorWithAlpha(backgroundValue));
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
          const input = inputMap[config.inputKey || key];
          if (input instanceof HTMLInputElement) {
            input.value = hex;
            input.disabled = false;
          }
          refreshContrastChecks();
        });
      }
    });

    let autoFixRunning = false;

    refreshContrastChecks = () => {
      applyOverridesFromInputs();
      if (!preview) {
        return;
      }

      let needsRecheck = false;
      const originalTheme = preview.dataset.theme;
      const resultsByRow = new Map();

      themes.forEach(theme => {
        preview.dataset.theme = theme;
        rows.forEach(row => {
          const key = row.dataset.contrastRow;
          const config = key ? targets[key] : null;
          if (!config) {
            return;
          }
          const result = computeContrastForTheme(config);
          updateSlot(row, theme, result);
          if (!resultsByRow.has(row)) {
            resultsByRow.set(row, {});
          }
          resultsByRow.get(row)[theme] = result;
        });
      });

      if (originalTheme) {
        preview.dataset.theme = originalTheme;
      } else {
        delete preview.dataset.theme;
      }

      rows.forEach(row => {
        const stored = resultsByRow.get(row) || {};
        const fixButton = row.querySelector('[data-contrast-fix]');
        const results = themes.map(theme => stored[theme]).filter(Boolean);
        const hasResults = results.length > 0;
        const anyFailed = hasResults && results.some(result => !result.meetsAA);
        const meetsAll = hasResults && results.length === themes.length && results.every(result => result.meetsAA);
        row.classList.toggle('design-contrast__row--warn', anyFailed);
        if (fixButton instanceof HTMLButtonElement) {
          fixButton.disabled = !hasResults || meetsAll;
        }

        // Auto-fix: when a contrast row fails AA for the current theme,
        // automatically apply the optimal foreground color so the user
        // never has to manually fix basic readability issues.
        if (anyFailed && !autoFixRunning) {
          const rowKey = row.dataset.contrastRow;
          const rowConfig = rowKey ? targets[rowKey] : null;
          if (rowConfig) {
            const currentThemeResult = stored[originalTheme || 'light'];
            if (currentThemeResult && !currentThemeResult.meetsAA) {
              preview.dataset.theme = originalTheme || 'light';
              const bgValue = resolveTargetValue(rowConfig, 'background');
              const bgColor = blendWithSurface(parseColorWithAlpha(bgValue));
              if (bgColor) {
                const optimal = computeOptimalTextColor(formatHex(bgColor));
                if (optimal) {
                  rowConfig.applyVars.forEach(variable => {
                    preview.style.setProperty(variable, optimal);
                  });
                  const rowInput = inputMap[rowConfig.inputKey || rowKey];
                  if (rowInput instanceof HTMLInputElement) {
                    rowInput.value = optimal;
                    rowInput.disabled = false;
                  }
                  needsRecheck = true;
                }
              }
            }
          }
        }
      });

      // Re-run checks once if any auto-fixes were applied, to update badges.
      if (needsRecheck) {
        autoFixRunning = true;
        if (originalTheme) {
          preview.dataset.theme = originalTheme;
        } else {
          delete preview.dataset.theme;
        }
        refreshContrastChecks();
        autoFixRunning = false;
        return;
      }
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

    const primary = brand.primary || previewDefaults.brandPrimary;
    const accent = brand.accent || brand.primary || previewDefaults.brandAccent;
    const secondary = brand.secondary || brand.accent || brand.primary || previewDefaults.brandSecondary;

    preview.style.setProperty('--brand-primary', primary);
    preview.style.setProperty('--brand-accent', accent);
    preview.style.setProperty('--brand-secondary', secondary);
    preview.style.setProperty('--accent-primary', primary);
    preview.style.setProperty('--accent-secondary', secondary);
    preview.style.setProperty('--section-default-accent', accent);
    preview.style.setProperty('--bg-page', 'var(--marketing-background, var(--marketing-surface))');
    preview.style.setProperty('--bg-section', 'var(--marketing-surface)');
    preview.style.setProperty('--bg-card', 'var(--marketing-surface-glass)');
    preview.style.setProperty('--bg-accent', primary);
    preview.style.setProperty('--layout-profile', layout.profile || previewDefaults.layoutProfile);
    preview.style.setProperty('--typography-preset', typography.preset || previewDefaults.typographyPreset);
    preview.style.setProperty('--components-card-style', components.cardStyle || previewDefaults.cardStyle);
    preview.style.setProperty('--components-button-style', components.buttonStyle || previewDefaults.buttonStyle);

    applyContrastTokensToPreview();
    syncComponentTokens();
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
    preview.style.setProperty('--marketing-secondary', scheme.secondary || scheme.accent);
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
    const dynamicOnAccent = computeOptimalTextColor(scheme.primary) || scheme.onAccent;
    preview.style.setProperty('--marketing-on-accent', dynamicOnAccent);
    preview.style.setProperty('--contrast-text-on-primary', dynamicOnAccent);
    preview.style.setProperty('--text-on-primary', dynamicOnAccent);
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
    applySchemeToken('--marketing-border', scheme.border);
    applySchemeToken('--marketing-border-muted', scheme.borderMuted);
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
    applySchemeToken('--marketing-font-stack', scheme.fontStack);
    applySchemeToken('--marketing-heading-font-stack', scheme.headingFontStack);
    applySchemeToken('--marketing-heading-weight', scheme.headingWeight);
    applySchemeToken('--marketing-heading-letter-spacing', scheme.headingLetterSpacing);
    applySchemeToken('--marketing-heading-line-height', scheme.headingLineHeight);
    applySchemeToken('--marketing-card-radius', scheme.cardRadius);
    applySchemeToken('--marketing-button-primary-bg', scheme.buttonPrimaryBg);
    applySchemeToken('--marketing-button-primary-text', scheme.buttonPrimaryText);
    applySchemeToken('--marketing-button-primary-border-color', scheme.buttonPrimaryBorderColor);
    applySchemeToken('--marketing-button-primary-hover-bg', scheme.buttonPrimaryHoverBg);
    applySchemeToken('--marketing-button-primary-focus-bg', scheme.buttonPrimaryFocusBg);
    applySchemeToken('--marketing-button-primary-active-bg', scheme.buttonPrimaryActiveBg);
    applySchemeToken('--marketing-button-secondary-bg', scheme.buttonSecondaryBg);
    applySchemeToken('--marketing-button-secondary-text', scheme.buttonSecondaryText);
    applySchemeToken('--marketing-button-secondary-border-color', scheme.buttonSecondaryBorderColor);
    applySchemeToken('--marketing-button-secondary-hover-bg', scheme.buttonSecondaryHoverBg);
    applySchemeToken('--marketing-link', scheme.link);
    applySchemeToken('--marketing-link-hover', scheme.linkHover);
    applySchemeToken('--marketing-link-contrast-light', scheme.linkContrastLight);
    applySchemeToken('--marketing-link-contrast-dark', scheme.linkContrastDark);
    applySchemeToken('--marketing-text-emphasis', scheme.textEmphasis);
    applySchemeToken('--marketing-text-inverse', scheme.textInverse);
    applySchemeToken('--marketing-topbar-text-contrast-light', scheme.topbarTextContrastLight);
    applySchemeToken('--marketing-topbar-text-contrast-dark', scheme.topbarTextContrastDark);
    applySchemeToken('--marketing-topbar-drop-bg-contrast-light', scheme.topbarDropBgContrastLight);
    applySchemeToken('--marketing-topbar-drop-bg-contrast-dark', scheme.topbarDropBgContrastDark);
    applySchemeToken('--marketing-topbar-btn-border-contrast-light', scheme.topbarBtnBorderContrastLight);
    applySchemeToken('--marketing-topbar-btn-border-contrast-dark', scheme.topbarBtnBorderContrastDark);
    applySchemeToken('--marketing-topbar-focus-ring-contrast-light', scheme.topbarFocusRingContrastLight);
    applySchemeToken('--marketing-topbar-focus-ring-contrast-dark', scheme.topbarFocusRingContrastDark);
    applySchemeToken('--marketing-success', scheme.success);
    applySchemeToken('--marketing-warning', scheme.warning);
    applySchemeToken('--marketing-danger', scheme.danger);
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
      if (value === 'aurora') {
        brandIsAuto = true;
        applyBrandSchemeToInputs(value);
        return;
      }
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
