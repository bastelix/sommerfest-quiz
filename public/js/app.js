document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('theme-toggle');
  if (!toggle) return;
  const isDark = localStorage.getItem('darkMode') === 'true';
  if (isDark) {
    document.body.classList.add('dark-mode', 'uk-light');
    toggle.setAttribute('uk-icon', 'icon: moon; ratio: 2');
  } else {
    toggle.setAttribute('uk-icon', 'icon: sun; ratio: 2');
  }
  UIkit.icon(toggle);
  toggle.addEventListener('click', function () {
    const dark = document.body.classList.toggle('dark-mode');
    document.body.classList.toggle('uk-light', dark);
    if (dark) {
      localStorage.setItem('darkMode', 'true');
      toggle.setAttribute('uk-icon', 'icon: moon; ratio: 2');
    } else {
      localStorage.setItem('darkMode', 'false');
      toggle.setAttribute('uk-icon', 'icon: sun; ratio: 2');
    }
    UIkit.icon(toggle);
  });
});
