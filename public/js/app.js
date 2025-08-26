/* global UIkit */
document.addEventListener('DOMContentLoaded', function () {
  const themeToggle = document.getElementById('theme-toggle');
  const contrastToggle = document.getElementById('contrast-toggle');

  if (themeToggle) {
    const isDark = localStorage.getItem('darkMode') === 'true';
    if (isDark) {
      document.body.classList.add('dark-mode', 'uk-light');
      document.documentElement.classList.add('dark-mode');
      // show sun icon when dark mode is active
      themeToggle.setAttribute('uk-icon', 'icon: sun; ratio: 2');
    } else {
      // show moon icon when light mode is active
      themeToggle.setAttribute('uk-icon', 'icon: moon; ratio: 2');
    }
    UIkit.icon(themeToggle);
    themeToggle.addEventListener('click', function () {
      const dark = document.body.classList.toggle('dark-mode');
      document.documentElement.classList.toggle('dark-mode', dark);
      document.body.classList.toggle('uk-light', dark);
      if (dark) {
        localStorage.setItem('darkMode', 'true');
        themeToggle.setAttribute('uk-icon', 'icon: sun; ratio: 2');
      } else {
        localStorage.setItem('darkMode', 'false');
        themeToggle.setAttribute('uk-icon', 'icon: moon; ratio: 2');
      }
      UIkit.icon(themeToggle);
    });
  }

  if (contrastToggle) {
    const isHigh = localStorage.getItem('highContrast') === 'true';
    if (isHigh) {
      document.body.classList.add('high-contrast');
    }
    UIkit.icon(contrastToggle);
    contrastToggle.addEventListener('click', function () {
      const hc = document.body.classList.toggle('high-contrast');
      localStorage.setItem('highContrast', hc ? 'true' : 'false');
    });
  }

  const topbar = document.querySelector('.topbar');
  const navPlaceholder = document.querySelector('.nav-placeholder');

  function updateNavPlaceholder() {
    if (navPlaceholder && topbar) {
      let height = topbar.offsetHeight;
      const headerBar = document.querySelector('.event-header-bar');
      if (headerBar) {
        height += headerBar.offsetHeight;
      }
      navPlaceholder.style.height = height + 'px';
    }
  }

  updateNavPlaceholder();
  window.addEventListener('resize', updateNavPlaceholder);
});
