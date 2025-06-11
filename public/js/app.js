document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('theme-toggle');
  if (!toggle) return;
  const isDark = localStorage.getItem('darkMode') === 'true';
  if (isDark) {
    document.body.classList.add('dark-mode', 'uk-light');
    // show sun icon when dark mode is active
    toggle.setAttribute('uk-icon', 'icon: sun; ratio: 2');
  } else {
    // show moon icon when light mode is active
    toggle.setAttribute('uk-icon', 'icon: moon; ratio: 2');
  }
  UIkit.icon(toggle);
  toggle.addEventListener('click', function () {
    const dark = document.body.classList.toggle('dark-mode');
    document.body.classList.toggle('uk-light', dark);
    if (dark) {
      localStorage.setItem('darkMode', 'true');
      // after enabling dark mode show sun icon
      toggle.setAttribute('uk-icon', 'icon: sun; ratio: 2');
    } else {
      localStorage.setItem('darkMode', 'false');
      // after disabling dark mode show moon icon
      toggle.setAttribute('uk-icon', 'icon: moon; ratio: 2');
    }
    UIkit.icon(toggle);
  });
});
