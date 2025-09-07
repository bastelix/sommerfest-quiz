/* global UIkit */
document.addEventListener('DOMContentLoaded', function () {
  const themeToggles = document.querySelectorAll('.theme-toggle');
  const accessibilityToggles = document.querySelectorAll('.accessibility-toggle');
  const configMenuToggle = document.getElementById('configMenuToggle');
  const configMenu = document.getElementById('menuDrop');
  const offcanvasToggle = document.getElementById('offcanvas-toggle');
  const offcanvas = document.getElementById('qr-offcanvas');
  const offcanvasHasItems = offcanvas && offcanvas.querySelector('li');
  const darkStylesheet = document.querySelector('link[href$="dark.css"]');
  const uikitStylesheet = document.querySelector('link[href*="uikit"]');
  const themeIcon = document.getElementById('themeIcon');
  const accessibilityIcon = document.getElementById('accessibilityIcon');
  const helpBtn = document.getElementById('helpBtn');
  const teamNameBtn = document.getElementById('teamNameBtn');

  if (offcanvasToggle && !offcanvasHasItems) {
    offcanvasToggle.hidden = true;
  }

  if (teamNameBtn) {
    const placeholder = teamNameBtn.textContent;
    const update = () => {
      const name = getStored(STORAGE_KEYS.PLAYER_NAME);
      teamNameBtn.textContent = name || placeholder;
    };
    update();
    teamNameBtn.addEventListener('click', async () => {
      await promptTeamName();
      update();
    });
  }

  const storedTheme = getStored(STORAGE_KEYS.DARK_MODE);
  let dark = storedTheme !== 'false';

  if (darkStylesheet) {
    darkStylesheet.toggleAttribute('disabled', !dark);
  }

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

  if (themeIcon) {
    themeIcon.innerHTML = dark ? sunSVG : moonSVG;
  }

  let accessible = getStored(STORAGE_KEYS.BARRIER_FREE) === 'true';
  if (accessible) {
    document.body.classList.add('high-contrast');
  }
  if (accessibilityIcon) {
    accessibilityIcon.innerHTML = accessible ? accessibilityOnSVG : accessibilityOffSVG;
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
      toggle.addEventListener('click', function (event) {
        event.preventDefault();
        dark = document.body.dataset.theme !== 'dark';
        document.body.dataset.theme = dark ? 'dark' : 'light';
        document.body.classList.toggle('dark-mode', dark);
        if (uikitStylesheet) {
          document.body.classList.toggle('uk-light', dark);
        }
        if (darkStylesheet) {
          darkStylesheet.toggleAttribute('disabled', !dark);
        }
        setStored(STORAGE_KEYS.DARK_MODE, dark ? 'true' : 'false');
        if (themeIcon) {
          themeIcon.innerHTML = dark ? sunSVG : moonSVG;
        }
        updateThemePressed();
        try { UIkit.dropdown('#menuDrop').hide(); } catch (e) {}
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
      toggle.addEventListener('click', function (event) {
        event.preventDefault();
        accessible = document.body.classList.toggle('high-contrast');
        setStored(STORAGE_KEYS.BARRIER_FREE, accessible ? 'true' : 'false');
        if (accessibilityIcon) {
          accessibilityIcon.innerHTML = accessible ? accessibilityOnSVG : accessibilityOffSVG;
        }
        updateAccessibilityPressed();
        try { UIkit.dropdown('#menuDrop').hide(); } catch (e) {}
      });
    });
  }

  if (helpBtn) {
    helpBtn.addEventListener('click', function () {
      try { UIkit.dropdown('#menuDrop').hide(); } catch (e) {}
      UIkit.offcanvas('#helpDrawer').show();
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
