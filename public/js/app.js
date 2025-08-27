/* global UIkit */
document.addEventListener('DOMContentLoaded', function () {
  const themeToggle = document.getElementById('theme-toggle');
  const contrastToggle = document.getElementById('contrast-toggle');
  const darkStylesheet = document.querySelector('link[href$="dark.css"]');

  const storedTheme = localStorage.getItem('darkMode');
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const isDark = storedTheme === 'true' || (storedTheme === null && prefersDark);

  if (darkStylesheet) {
    darkStylesheet.disabled = !isDark;
  }

  if (isDark) {
    document.body.classList.add('dark-mode', 'uk-light');
    document.documentElement.classList.add('dark-mode');
  }

  if (themeToggle) {
    themeToggle.addEventListener('click', function (event) {
      event.preventDefault();
      const dark = document.body.classList.toggle('dark-mode');
      document.documentElement.classList.toggle('dark-mode', dark);
      document.body.classList.toggle('uk-light', dark);
      if (darkStylesheet) {
        darkStylesheet.disabled = !dark;
      }
      localStorage.setItem('darkMode', dark ? 'true' : 'false');
    });
  }

  if (contrastToggle) {
    const isHigh = localStorage.getItem('highContrast') === 'true';
    if (isHigh) {
      document.body.classList.add('high-contrast');
    }
    contrastToggle.addEventListener('click', function (event) {
      event.preventDefault();
      const hc = document.body.classList.toggle('high-contrast');
      localStorage.setItem('highContrast', hc ? 'true' : 'false');
    });
  }

  const topbar = document.querySelector('.topbar');
  const navPlaceholder = document.querySelector('.nav-placeholder');

  function updateNavPlaceholder() {
    let height = topbar.offsetHeight;
    const headerBar = document.querySelector('.event-header-bar');
    if (headerBar) {
      height += headerBar.offsetHeight;
    }
    navPlaceholder.style.height = height + 'px';
  }

  if (topbar && navPlaceholder) {
    updateNavPlaceholder();
  }

  window.addEventListener('resize', () => {
    if (topbar && navPlaceholder) {
      updateNavPlaceholder();
    }
  });
});
