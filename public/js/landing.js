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

  const submitButton = form.querySelector('button[type="submit"]');
  const turnstileContainer = form.querySelector('[data-turnstile-container]');
  const turnstileHint = form.querySelector('[data-turnstile-hint]');
  const hasTurnstile = !!(turnstileContainer && turnstileContainer.querySelector('.cf-turnstile'));
  let turnstileToken = '';

  const disableSubmit = () => {
    if (submitButton) {
      submitButton.disabled = true;
    }
    form.classList.add('is-turnstile-disabled');
  };

  const enableSubmit = () => {
    if (submitButton) {
      submitButton.disabled = false;
    }
    form.classList.remove('is-turnstile-disabled');
  };

  const showTurnstileHint = (text) => {
    if (!turnstileHint) return;
    if (text) {
      turnstileHint.textContent = text;
      turnstileHint.hidden = false;
    } else {
      turnstileHint.hidden = true;
    }
  };

  if (!hasTurnstile && turnstileContainer) {
    turnstileContainer.hidden = true;
  }

  if (hasTurnstile) {
    form.dataset.turnstileRequired = 'true';
    disableSubmit();
    window.contactTurnstileSuccess = (token) => {
      turnstileToken = typeof token === 'string' ? token : '';
      if (turnstileToken) {
        enableSubmit();
        showTurnstileHint('');
      } else {
        disableSubmit();
        showTurnstileHint('Bitte bestätigen Sie, dass Sie kein Roboter sind.');
      }
    };
    window.contactTurnstileError = () => {
      turnstileToken = '';
      disableSubmit();
      showTurnstileHint('Validierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
      if (window.turnstile && typeof window.turnstile.reset === 'function') {
        window.turnstile.reset();
      }
    };
    window.contactTurnstileExpired = () => {
      turnstileToken = '';
      disableSubmit();
      showTurnstileHint('Bitte bestätigen Sie erneut, dass Sie kein Roboter sind.');
      if (window.turnstile && typeof window.turnstile.reset === 'function') {
        window.turnstile.reset();
      }
    };
  } else {
    form.dataset.turnstileRequired = 'false';
  }

  const basePath = (window.basePath || '').replace(/\/+$/, '');
  const defaultEndpoint = `${basePath}/landing/contact`;

  const resolveEndpoint = (value) => {
    if (!value) {
      return defaultEndpoint;
    }

    const trimmed = value.trim();
    if (trimmed === '') {
      return defaultEndpoint;
    }

    if (/^(https?:)?\/\//i.test(trimmed)) {
      return trimmed;
    }

    if (basePath !== '' && trimmed.startsWith(`${basePath}/`)) {
      return trimmed;
    }

    if (trimmed.startsWith('/')) {
      return basePath === '' ? trimmed : `${basePath}${trimmed}`;
    }

    const prefix = basePath === '' ? '/' : `${basePath}/`;
    return `${prefix}${trimmed.replace(/^\/+/, '')}`;
  };

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    // Honeypot: Abbruch bei Bot
    const hp = form.querySelector('input[name="company"]');
    if (hp && hp.value.trim() !== '') return;

    if (form.dataset.turnstileRequired === 'true') {
      const currentToken = turnstileToken || (form.querySelector('input[name="cf-turnstile-response"]')?.value ?? '').trim();
      if (!currentToken) {
        disableSubmit();
        showTurnstileHint('Bitte bestätigen Sie, dass Sie kein Roboter sind.');
        return;
      }
    }

    const data = new URLSearchParams(new FormData(form));
    const endpoint = resolveEndpoint(form.dataset.contactEndpoint);
    fetch(endpoint, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      credentials: 'same-origin',
      body: data
    }).then(res => {
      if (res.ok) {
        msg.textContent = 'Vielen Dank für Ihre Nachricht!';
        form.reset();
        if (hasTurnstile) {
          turnstileToken = '';
          if (window.turnstile && typeof window.turnstile.reset === 'function') {
            window.turnstile.reset();
          }
          disableSubmit();
          showTurnstileHint('');
        }
      } else {
        msg.textContent = 'Fehler beim Versenden. Bitte versuchen Sie es erneut.';
        if (hasTurnstile) {
          turnstileToken = '';
          if (window.turnstile && typeof window.turnstile.reset === 'function') {
            window.turnstile.reset();
          }
          disableSubmit();
          showTurnstileHint('Validierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
        }
      }
      modal.show();
    }).catch(() => {
      msg.textContent = 'Fehler beim Versenden. Bitte versuchen Sie es erneut.';
      if (hasTurnstile) {
        turnstileToken = '';
        if (window.turnstile && typeof window.turnstile.reset === 'function') {
          window.turnstile.reset();
        }
        disableSubmit();
        showTurnstileHint('Validierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
      }
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
    if (lang === 'de') {
      url.searchParams.delete('lang');
    } else {
      url.searchParams.set('lang', lang);
    }
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
