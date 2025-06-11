document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('theme-toggle');
  if (!toggle) return;
  const isDark = localStorage.getItem('darkMode') === 'true';
  if (isDark) {
    document.body.classList.add('dark-mode', 'uk-light');
    toggle.checked = true;
  }
  toggle.addEventListener('change', function () {
    if (this.checked) {
      document.body.classList.add('dark-mode', 'uk-light');
      localStorage.setItem('darkMode', 'true');
    } else {
      document.body.classList.remove('dark-mode', 'uk-light');
      localStorage.setItem('darkMode', 'false');
    }
  });
});
