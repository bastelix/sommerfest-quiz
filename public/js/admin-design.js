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
      background: '#eef2ff',
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
      background: '#ffedd5',
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
      background: '#020617',
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
  };
  const marketingTokenKeys = [
    '--marketing-primary',
    '--marketing-accent',
    '--marketing-secondary',
    '--marketing-surface',
    '--marketing-background',
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
  };

  const applyMarketingSchemeToPreview = schemeKey => {
    if (!preview) return;
    const scheme = marketingSchemes[schemeKey];
    if (!scheme) {
      marketingTokenKeys.forEach(token => preview.style.removeProperty(token));
      return;
    }
    preview.style.setProperty('--marketing-primary', scheme.primary);
    preview.style.setProperty('--marketing-accent', scheme.accent);
    preview.style.setProperty('--marketing-secondary', scheme.accent);
    preview.style.setProperty('--marketing-surface', scheme.surface);
    preview.style.setProperty('--marketing-background', scheme.background);
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
  initTabs();
})();
