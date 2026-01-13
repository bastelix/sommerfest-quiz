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
  ];
  const activeTab = editor.dataset.activeTab === 'behavior' ? 'behavior' : 'appearance';

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

  let refreshContrastChecks = () => {};

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
    const scheme = marketingSchemes[schemeKey];
    if (!scheme) {
      marketingTokenKeys.forEach(token => preview.style.removeProperty(token));
      refreshContrastChecks();
      return;
    }
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
    refreshContrastChecks();
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
    };

    if (!isReadOnly) {
      select.addEventListener('change', event => {
        const target = event.target;
        if (!(target instanceof HTMLSelectElement)) return;
        applySelection(target.value);
      });
    }

    applySelection(select.value || select.dataset.currentMarketingScheme || '');
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
