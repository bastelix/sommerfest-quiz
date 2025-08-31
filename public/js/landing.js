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
