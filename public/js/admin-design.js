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
  const preview = document.getElementById('design-preview');
  const isReadOnly = editor.dataset.readOnly === '1';

  const resolveTokens = () => ({
    brand: { ...(defaults.brand || {}), ...(current.brand || {}) },
    layout: { ...(defaults.layout || {}), ...(current.layout || {}) },
    typography: { ...(defaults.typography || {}), ...(current.typography || {}) },
    components: { ...(defaults.components || {}), ...(current.components || {}) },
  });

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

    preview.style.setProperty('--brand-primary', brand.primary || '#1e87f0');
    preview.style.setProperty('--brand-accent', brand.accent || brand.primary || '#f97316');
    preview.dataset.layoutProfile = layout.profile || 'standard';
    preview.dataset.typographyPreset = typography.preset || 'modern';
    preview.dataset.cardStyle = components.cardStyle || 'rounded';
    preview.dataset.buttonStyle = components.buttonStyle || 'filled';

    updateMeta('layout', preview.dataset.layoutProfile);
    updateMeta('typography', preview.dataset.typographyPreset);
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

  const initNamespaceSelect = () => {
    const select = document.getElementById('pageNamespaceSelect');
    if (!select) return;
    select.addEventListener('change', () => {
      const url = new URL(window.location.href);
      url.searchParams.set('namespace', select.value || '');
      window.location.href = url.toString();
    });
  };

  applyTokensToPreview();
  initThemeToggle();
  initDeviceToggle();
  initTokenInputs();
  initNamespaceSelect();
})();
