// Rotierendes Wort – respektiert prefers-reduced-motion
(() => {
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReduced) return;

  const el = document.getElementById('rotating-word');
  if (!el) return;

  const words = ['unvergesslich', 'spannend', 'interaktiv', 'einzigartig', 'unterhaltsam'];
  const changeInterval = 5500, fadeDuration = 1000;
  let i = 0;

  const triggerUnderline = () => {
    el.classList.remove('underline-animate'); void el.offsetWidth; el.classList.add('underline-animate');
  };

  triggerUnderline();
  setInterval(() => {
    el.style.opacity = 0;
    setTimeout(() => {
      i = (i + 1) % words.length;
      el.textContent = words[i];
      el.style.opacity = 1;
      triggerUnderline();
    }, fadeDuration);
  }, changeInterval);
})();

// Kontaktformular: Honeypot + aria-live Modal
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contact-form');
  const modal = UIkit.modal('#contact-modal');
  const msg = document.getElementById('contact-modal-message');
  if (!form || !modal || !msg) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    // Honeypot: Abbruch bei Bot
    const hp = form.querySelector('input[name="company"]');
    if (hp && hp.value.trim() !== '') return;

    const data = new URLSearchParams(new FormData(form));
    fetch(`${window.basePath}/landing/contact`, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      credentials: 'same-origin',
      body: data
    }).then(res => {
      if (res.ok) {
        msg.textContent = 'Vielen Dank für Ihre Nachricht!';
        form.reset();
      } else {
        msg.textContent = 'Fehler beim Versenden. Bitte versuchen Sie es erneut.';
      }
      modal.show();
    }).catch(() => {
      msg.textContent = 'Fehler beim Versenden. Bitte versuchen Sie es erneut.';
      modal.show();
    });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('themeToggle');
  const icon = document.getElementById('themeIcon');
  if (!toggle) return;

  const sunSVG = `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" fill="currentColor"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="23"/><line x1="1" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="23" y2="12"/><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"/><line x1="4.22" y1="19.78" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.78" y2="4.22"/></g></svg>`;
  const moonSVG = `<svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" fill="currentColor"></path></svg>`;

  const stored = localStorage.getItem('qr-theme');
  let theme = stored || 'dark';

  const apply = () => {
    document.documentElement.dataset.theme = theme;
    document.body.classList.toggle('dark-mode', theme === 'dark');
    if (icon) {
      icon.innerHTML = theme === 'dark' ? sunSVG : moonSVG;
    }
    toggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
  };

  apply();

  toggle.addEventListener('click', (e) => {
    e.preventDefault();
    theme = theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('qr-theme', theme);
    document.body.classList.toggle('dark-mode', theme === 'dark');
    apply();
  });
});
