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
  const themeToggles = document.querySelectorAll('.theme-toggle');
  const accessibilityToggles = document.querySelectorAll('.accessibility-toggle');
  const langButtons = document.querySelectorAll('.lang-option');

  // initial state already set by app.js; store for marketing preferences
  setStored(
    STORAGE_KEYS.QR_THEME,
    document.body.dataset.theme === 'dark' ? 'dark' : 'light'
  );
  setStored(
    STORAGE_KEYS.QR_CONTRAST,
    document.body.classList.contains('high-contrast') ? 'high' : 'normal'
  );

  themeToggles.forEach(btn =>
    btn.addEventListener('click', () => {
      const dark = document.body.dataset.theme === 'dark';
      setStored(STORAGE_KEYS.QR_THEME, dark ? 'dark' : 'light');
    })
  );

  accessibilityToggles.forEach(btn =>
    btn.addEventListener('click', () => {
      const accessible = document.body.classList.contains('high-contrast');
      setStored(STORAGE_KEYS.QR_CONTRAST, accessible ? 'high' : 'normal');
    })
  );

  langButtons.forEach(btn => btn.addEventListener('click', () => {
    const lang = btn.dataset.lang;
    const url = new URL(window.location.href);
    url.searchParams.set('lang', lang);
    window.location.href = url.toString();
  }));
});

document.addEventListener('DOMContentLoaded', () => {
  const counters = document.querySelectorAll('[data-counter-target]');
  if (!counters.length) return;

  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const locale = document.documentElement.lang?.toLowerCase().startsWith('en') ? 'en-US' : 'de-DE';

  const formatValue = (value, decimals) => new Intl.NumberFormat(locale, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  }).format(value);

  const setFinalValue = (el) => {
    const target = Number(el.dataset.counterTarget);
    if (!Number.isFinite(target)) return;
    const decimals = Number(el.dataset.counterDecimals ?? 0) || 0;
    const prefix = el.dataset.counterPrefix ?? '';
    const suffix = el.dataset.counterSuffix ?? '';
    el.textContent = `${prefix}${formatValue(target, decimals)}${suffix}`;
  };

  const animateCounter = (el) => {
    const target = Number(el.dataset.counterTarget);
    if (!Number.isFinite(target)) return;

    const duration = Number(el.dataset.counterDuration ?? 0) || 1500;
    const decimals = Number(el.dataset.counterDecimals ?? 0) || 0;
    const prefix = el.dataset.counterPrefix ?? '';
    const suffix = el.dataset.counterSuffix ?? '';
    const startValue = Number(el.dataset.counterStart ?? 0) || 0;
    const diff = target - startValue;
    let start = null;

    el.textContent = `${prefix}${formatValue(startValue, decimals)}${suffix}`;

    const step = (timestamp) => {
      if (start === null) start = timestamp;
      const progress = Math.min((timestamp - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = startValue + diff * eased;
      el.textContent = `${prefix}${formatValue(current, decimals)}${suffix}`;
      if (progress < 1) {
        window.requestAnimationFrame(step);
      }
    };

    window.requestAnimationFrame(step);
  };

  if (prefersReduced) {
    counters.forEach(setFinalValue);
    return;
  }

  const observer = new IntersectionObserver((entries, obs) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      animateCounter(el);
      setTimeout(() => setFinalValue(el), Number(el.dataset.counterDuration ?? 0) || 1500);
      obs.unobserve(el);
    });
  }, { threshold: 0.5 });

  counters.forEach((counter) => {
    if (counter.dataset.counterInstant === 'true') {
      setFinalValue(counter);
    } else {
      observer.observe(counter);
    }
  });
});
