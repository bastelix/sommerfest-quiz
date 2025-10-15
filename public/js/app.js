/* global UIkit */
document.addEventListener('DOMContentLoaded', function () {
  const themeToggles = document.querySelectorAll('.theme-toggle');
  const accessibilityToggles = document.querySelectorAll('.accessibility-toggle');
  const configMenuToggle = document.getElementById('configMenuToggle');
  const configMenu = document.getElementById('menuDrop');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const adminSidebar = document.getElementById('adminSidebar');
  const sidebarHasItems = adminSidebar && adminSidebar.querySelector('li');
  const offcanvasToggle = document.getElementById('offcanvas-toggle');
  const offcanvas = document.getElementById('qr-offcanvas');
  const offcanvasHasItems = offcanvas && offcanvas.querySelector('li');
  const darkStylesheet = document.querySelector('link[href$="dark.css"]');
  const defaultDarkMedia = darkStylesheet ? (darkStylesheet.getAttribute('media') || 'all') : 'all';
  const uikitStylesheet = document.querySelector('link[href*="uikit"]');
  const themeIcons = document.querySelectorAll('.theme-icon');
  const accessibilityIcons = document.querySelectorAll('.accessibility-icon');
  const helpButtons = document.querySelectorAll('.help-toggle');
  const teamNameBtn = document.getElementById('teamNameBtn');

  if (offcanvasToggle && !offcanvasHasItems) {
    offcanvasToggle.hidden = true;
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
  const storedTheme = hasStorage ? getStored(STORAGE_KEYS.DARK_MODE) : null;
  let dark;

  const prefersDark = () => {
    try {
      return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches;
    } catch (e) {
      return false;
    }
  };

  if (storedTheme === null || storedTheme === undefined) {
    dark = document.body.dataset.theme === 'dark' || document.body.classList.contains('dark-mode');
    if (!dark) {
      dark = prefersDark();
    }
  } else {
    const normalizedTheme = String(storedTheme).toLowerCase();
    dark = normalizedTheme === 'true' || normalizedTheme === '1' || normalizedTheme === 'dark';
  }

  function syncDarkStylesheet () {
    if (!darkStylesheet) {
      return;
    }
    if (typeof darkStylesheet.toggleAttribute === 'function') {
      darkStylesheet.toggleAttribute('disabled', !dark);
    } else if (!dark) {
      darkStylesheet.setAttribute('disabled', 'disabled');
    } else {
      darkStylesheet.removeAttribute('disabled');
    }
    darkStylesheet.disabled = !dark;
    darkStylesheet.media = dark ? 'all' : defaultDarkMedia;
  }

  syncDarkStylesheet();

  document.body.dataset.theme = dark ? 'dark' : 'light';
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
  let accessible = storedBarrierFree === 'true' || storedBarrierFree === '1';
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
      document.body.dataset.theme = dark ? 'dark' : 'light';
      document.body.classList.toggle('dark-mode', dark);
      if (uikitStylesheet) {
        document.body.classList.toggle('uk-light', dark);
      }
      syncDarkStylesheet();
      if (typeof setStored === 'function' && typeof STORAGE_KEYS !== 'undefined' && STORAGE_KEYS.DARK_MODE) {
        setStored(STORAGE_KEYS.DARK_MODE, dark ? 'true' : 'false');
      }
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

  if (configMenuToggle && configMenu) {
    UIkit.util.on(configMenu, 'show', () => {
      configMenuToggle.setAttribute('aria-expanded', 'true');
    });
    UIkit.util.on(configMenu, 'hide', () => {
      configMenuToggle.setAttribute('aria-expanded', 'false');
    });
  }

  if (offcanvasToggle && offcanvasHasItems) {
    UIkit.util.on(offcanvas, 'show', () => {
      offcanvasToggle.setAttribute('aria-expanded', 'true');
    });
    UIkit.util.on(offcanvas, 'hide', () => {
      offcanvasToggle.setAttribute('aria-expanded', 'false');
    });
  }

});
