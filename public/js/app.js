/* global UIkit */
(() => {
  const normalizeEventId = (value) => {
    if (typeof value === 'string') {
      return value.trim();
    }
    return value ? String(value).trim() : '';
  };

  const resolveInitialEventId = () => {
    const params = new URLSearchParams(window.location.search || '');
    const fromUrl = params.get('event') || params.get('event_uid') || '';
    if (fromUrl) {
      return normalizeEventId(fromUrl);
    }
    const cfg = window.quizConfig || {};
    return normalizeEventId(cfg.event_uid || '');
  };

  const seeded = normalizeEventId(window.activeEventId || '');
  window.activeEventId = seeded || resolveInitialEventId();
  window.getActiveEventId = () => normalizeEventId(window.activeEventId || '');
  window.setActiveEventId = (value) => {
    window.activeEventId = normalizeEventId(value);
    return window.activeEventId;
  };
})();
document.addEventListener('DOMContentLoaded', function () {
  const themeToggles = document.querySelectorAll('.theme-toggle');
  const accessibilityToggles = document.querySelectorAll('.accessibility-toggle');
  const configMenuToggle = document.getElementById('configMenuToggle');
  const configMenu = document.getElementById('menuDrop');
  const languageMenuToggle = document.getElementById('languageMenuToggle');
  const languageDrop = document.getElementById('languageDrop');
  const languageButtons = document.querySelectorAll('.lang-option');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const adminSidebar = document.getElementById('adminSidebar');
  const sidebarHasItems = adminSidebar && adminSidebar.querySelector('li');
  const offcanvasToggle = document.getElementById('offcanvas-toggle');
  const adminOffcanvasToggle = document.getElementById('adminOffcanvasToggle');
  const offcanvasToggles = [offcanvasToggle, adminOffcanvasToggle].filter(Boolean);
  const offcanvas = document.getElementById('qr-offcanvas');
  const offcanvasHasItems = offcanvas && offcanvas.querySelector('li');
  const darkStylesheet = document.querySelector('link[href$="dark.css"]');
  const uikitStylesheet = document.querySelector('link[href*="uikit"]');
  const themeIcons = document.querySelectorAll('.theme-icon');
  const accessibilityIcons = document.querySelectorAll('.accessibility-icon');
  const helpButtons = document.querySelectorAll('.help-toggle');
  const teamNameBtn = document.getElementById('teamNameBtn');
  const PAGE_EDITOR_THEME_KEY = 'pageEditorTheme';
  const pageEditorElement = document.querySelector('.page-editor');
  const pageEditorThemeToggle = document.querySelector('[data-theme-toggle]');
  const isPageEditorContext = Boolean(pageEditorElement || pageEditorThemeToggle);

  if (offcanvasToggles.length) {
    offcanvasToggles.forEach((toggle) => {
      toggle.hidden = !offcanvas;
    });
  }

  if (sidebarToggle) {
    if (!sidebarHasItems) {
      sidebarToggle.hidden = true;
    } else {
      const collapsedKey = (typeof STORAGE_KEYS !== 'undefined' && STORAGE_KEYS.ADMIN_SIDEBAR)
        ? STORAGE_KEYS.ADMIN_SIDEBAR
        : 'adminSidebarCollapsed';
      const setCollapsedState = (collapsed) => {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        sidebarToggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
      };
      const storedSidebar = (typeof getStored === 'function') ? getStored(collapsedKey) : null;
      const initialCollapsed = storedSidebar === 'true' || storedSidebar === '1';
      setCollapsedState(initialCollapsed);
      sidebarToggle.addEventListener('click', () => {
        const collapsed = !document.body.classList.contains('sidebar-collapsed');
        setCollapsedState(collapsed);
        if (typeof setStored === 'function') {
          setStored(collapsedKey, collapsed ? '1' : '0');
        }
      });
    }
  }

  if (teamNameBtn) {
    const placeholder = teamNameBtn.textContent;
    const update = () => {
      const name = (typeof STORAGE_KEYS !== 'undefined' && typeof getStored === 'function' && STORAGE_KEYS.PLAYER_NAME)
        ? getStored(STORAGE_KEYS.PLAYER_NAME)
        : null;
      teamNameBtn.textContent = name || placeholder;
    };
    update();
    teamNameBtn.addEventListener('click', async () => {
      await promptTeamName();
      update();
    });
  }

  const hasStorage = typeof STORAGE_KEYS !== 'undefined' && typeof getStored === 'function';

  const normalizeEditorTheme = (value) => {
    if (typeof value !== 'string') {
      return 'light';
    }
    const normalized = value.trim().toLowerCase();
    if (normalized === 'dark' || normalized === 'high-contrast') {
      return normalized;
    }
    return 'light';
  };

  const readPageEditorTheme = () => {
    if (!isPageEditorContext) {
      return null;
    }
    try {
      return window.localStorage?.getItem?.(PAGE_EDITOR_THEME_KEY);
    } catch (e) {
      return null;
    }
  };

  const editorThemePreference = readPageEditorTheme();
  const normalizedEditorTheme = editorThemePreference ? normalizeEditorTheme(editorThemePreference) : null;
  const hasEditorThemePreference = Boolean(isPageEditorContext && normalizedEditorTheme);
  const storedTheme = (!hasEditorThemePreference && hasStorage) ? getStored(STORAGE_KEYS.DARK_MODE) : null;
  let dark;

  const persistPageEditorTheme = (theme) => {
    if (!isPageEditorContext) {
      return;
    }
    try {
      window.localStorage?.setItem?.(PAGE_EDITOR_THEME_KEY, normalizeEditorTheme(theme));
    } catch (e) { /* empty */ }
  };

  const prefersDark = () => {
    try {
      return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches;
    } catch (e) {
      return false;
    }
  };

  if (normalizedEditorTheme) {
    dark = normalizedEditorTheme === 'dark' || normalizedEditorTheme === 'high-contrast';
  } else if (storedTheme === null || storedTheme === undefined) {
    const currentTheme = (document.body.dataset.theme || document.documentElement.dataset.theme || '').toLowerCase();
    dark = currentTheme === 'dark' || document.body.classList.contains('dark-mode');
    if (!dark) {
      dark = prefersDark();
    }
  } else {
    const normalizedTheme = String(storedTheme).toLowerCase();
    dark = normalizedTheme === 'true' || normalizedTheme === '1' || normalizedTheme === 'dark';
  }

  if (normalizedEditorTheme && typeof setStored === 'function' && typeof STORAGE_KEYS !== 'undefined' && STORAGE_KEYS.DARK_MODE) {
    setStored(STORAGE_KEYS.DARK_MODE, dark ? 'true' : 'false');
  }

  function syncDarkStylesheet () {
    if (!darkStylesheet) {
      return;
    }
    darkStylesheet.removeAttribute('disabled');
    darkStylesheet.disabled = false;
    darkStylesheet.media = 'all';
  }

  syncDarkStylesheet();

  const resolvedTheme = dark ? 'dark' : 'light';
  if (document.body.dataset.theme !== resolvedTheme) {
    document.body.dataset.theme = resolvedTheme;
  }
  if (document.documentElement.dataset.theme !== resolvedTheme) {
    document.documentElement.dataset.theme = resolvedTheme;
  }
  document.body.classList.toggle('dark-mode', dark);
  if (uikitStylesheet) {
    document.body.classList.toggle('uk-light', dark);
  }

  const sunSVG = `
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" fill="currentColor"/>
        <g stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="12" y1="1"  x2="12" y2="4"/>
          <line x1="12" y1="20" x2="12" y2="23"/>
          <line x1="1"  y1="12" x2="4"  y2="12"/>
          <line x1="20" y1="12" x2="23" y2="12"/>
          <line x1="4.22" y1="4.22" x2="6.34" y2="6.34"/>
          <line x1="17.66" y1="17.66" x2="19.78" y2="19.78"/>
          <line x1="4.22" y1="19.78" x2="6.34" y2="17.66"/>
          <line x1="17.66" y1="6.34" x2="19.78" y2="4.22"/>
        </g>
      </svg>`;
  const moonSVG = `
      <svg viewBox="0 0 24 24">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" fill="currentColor"></path>
      </svg>`;
  const accessibilityOnSVG = `
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
        <path d="M12 2a10 10 0 0 0 0 20z" fill="currentColor"/>
      </svg>`;
  const accessibilityOffSVG = `
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
        <path d="M12 2a10 10 0 0 0 0 20z" fill="currentColor" opacity="0.4"/>
      </svg>`;

  if (themeIcons.length) {
    themeIcons.forEach((icon) => {
      icon.innerHTML = dark ? sunSVG : moonSVG;
    });
  }

  const storedBarrierFree = (typeof STORAGE_KEYS !== 'undefined' && typeof getStored === 'function' && STORAGE_KEYS.BARRIER_FREE)
    ? getStored(STORAGE_KEYS.BARRIER_FREE)
    : null;
  let accessible = (normalizedEditorTheme === 'high-contrast') || storedBarrierFree === 'true' || storedBarrierFree === '1';
  if (accessible) {
    document.body.classList.add('high-contrast');
  }
  if (accessibilityIcons.length) {
    accessibilityIcons.forEach((icon) => {
      icon.innerHTML = accessible ? accessibilityOnSVG : accessibilityOffSVG;
    });
  }

  function updateThemePressed () {
    themeToggles.forEach((toggle) => {
      toggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
    });
  }

  function updateAccessibilityPressed () {
    accessibilityToggles.forEach((toggle) => {
      toggle.setAttribute('aria-pressed', accessible ? 'true' : 'false');
    });
  }

  updateThemePressed();
  updateAccessibilityPressed();

  if (themeToggles.length) {
    themeToggles.forEach((toggle) => {
      toggle.addEventListener('keydown', function (event) {
        if (event.key === ' ') {
          event.preventDefault();
          toggle.click();
        }
      });
    });
  }

  if (accessibilityToggles.length) {
    accessibilityToggles.forEach((toggle) => {
      toggle.addEventListener('keydown', function (event) {
        if (event.key === ' ') {
          event.preventDefault();
          toggle.click();
        }
      });
    });
  }

  document.addEventListener('click', function (event) {
    const themeBtn = event.target.closest('.theme-toggle');
    if (themeBtn) {
      event.preventDefault();
      dark = document.body.dataset.theme !== 'dark';
      const updatedTheme = dark ? 'dark' : 'light';
      document.body.dataset.theme = updatedTheme;
      document.documentElement.dataset.theme = updatedTheme;
      document.body.classList.toggle('dark-mode', dark);
      if (uikitStylesheet) {
        document.body.classList.toggle('uk-light', dark);
      }
      syncDarkStylesheet();
      if (typeof setStored === 'function' && typeof STORAGE_KEYS !== 'undefined' && STORAGE_KEYS.DARK_MODE) {
        setStored(STORAGE_KEYS.DARK_MODE, dark ? 'true' : 'false');
      }
      persistPageEditorTheme(updatedTheme);
      if (themeIcons.length) {
        themeIcons.forEach((icon) => {
          icon.innerHTML = dark ? sunSVG : moonSVG;
        });
      }
      updateThemePressed();
      try { UIkit.dropdown('#menuDrop').hide(); } catch (e) {}
      return;
    }

    const accessibilityBtn = event.target.closest('.accessibility-toggle');
    if (accessibilityBtn) {
      event.preventDefault();
      accessible = document.body.classList.toggle('high-contrast');
      if (typeof setStored === 'function' && typeof STORAGE_KEYS !== 'undefined' && STORAGE_KEYS.BARRIER_FREE) {
        setStored(STORAGE_KEYS.BARRIER_FREE, accessible ? 'true' : 'false');
      }
      if (accessibilityIcons.length) {
        accessibilityIcons.forEach((icon) => {
          icon.innerHTML = accessible ? accessibilityOnSVG : accessibilityOffSVG;
        });
      }
      updateAccessibilityPressed();
      try { UIkit.dropdown('#menuDrop').hide(); } catch (e) {}
    }
  });

  if (helpButtons.length) {
    helpButtons.forEach((button) => {
      button.addEventListener('click', function () {
        try { UIkit.dropdown('#menuDrop').hide(); } catch (e) {}
        UIkit.offcanvas('#helpDrawer').show();
      });
    });
  }

  if (languageButtons.length) {
    languageButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const lang = button.dataset.lang;
        const url = new URL(window.location.href);
        if (lang) {
          url.searchParams.set('lang', lang);
        } else {
          url.searchParams.delete('lang');
        }
        try { UIkit.dropdown('#languageDrop').hide(); } catch (e) {}
        try { UIkit.dropdown('#menuDrop').hide(); } catch (e) {}
        window.location.href = url.toString();
      });
    });
  }

  if (configMenuToggle && configMenu) {
    UIkit.util.on(configMenu, 'show', () => {
      configMenuToggle.setAttribute('aria-expanded', 'true');
    });
    UIkit.util.on(configMenu, 'hide', () => {
      configMenuToggle.setAttribute('aria-expanded', 'false');
    });
  }

  if (languageMenuToggle && languageDrop) {
    UIkit.util.on(languageDrop, 'show', () => {
      languageMenuToggle.setAttribute('aria-expanded', 'true');
    });
    UIkit.util.on(languageDrop, 'hide', () => {
      languageMenuToggle.setAttribute('aria-expanded', 'false');
    });
  }

  if (offcanvasToggles.length && offcanvasHasItems) {
    UIkit.util.on(offcanvas, 'show', () => {
      offcanvasToggles.forEach((toggle) => {
        toggle.setAttribute('aria-expanded', 'true');
      });
    });
    UIkit.util.on(offcanvas, 'hide', () => {
      offcanvasToggles.forEach((toggle) => {
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

});
