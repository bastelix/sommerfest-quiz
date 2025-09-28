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

const waitForTurnstile = (() => {
  let promise;
  return () => {
    if (typeof window.turnstile !== 'undefined') {
      return Promise.resolve(window.turnstile);
    }

    if (!promise) {
      promise = new Promise((resolve, reject) => {
        let settled = false;
        const finish = () => {
          if (settled) return;
          if (typeof window.turnstile !== 'undefined') {
            settled = true;
            resolve(window.turnstile);
          }
        };

        const fail = (message) => {
          if (settled) return;
          settled = true;
          reject(new Error(message));
        };

        const existing = Array.from(document.getElementsByTagName('script'))
          .find((script) => script.src.includes('challenges.cloudflare.com/turnstile'));

        const attachListeners = (script) => {
          script.addEventListener('load', finish, { once: true });
          script.addEventListener('error', () => fail('Turnstile script failed to load'), { once: true });
        };

        if (existing) {
          attachListeners(existing);
        } else {
          const script = document.createElement('script');
          script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
          script.async = true;
          script.defer = true;
          script.dataset.turnstile = 'true';
          attachListeners(script);
          document.head.appendChild(script);
        }

        const interval = window.setInterval(() => {
          if (typeof window.turnstile !== 'undefined') {
            window.clearInterval(interval);
            finish();
          }
        }, 200);

        window.setTimeout(() => {
          window.clearInterval(interval);
          if (typeof window.turnstile === 'undefined') {
            fail('Turnstile script timed out');
          }
        }, 8000);
      });
    }

    return promise;
  };
})();

const setupTurnstileCaptcha = (form, siteKey, errorEl) => {
  const tokenField = form.querySelector('input[name="captcha_token"]');
  const container = form.querySelector('[data-captcha-container]');
  const wrapper = form.querySelector('[data-captcha-wrapper]');
  if (!tokenField || !container) {
    return null;
  }

  const showError = (message) => {
    if (!errorEl) return;
    errorEl.textContent = message;
    errorEl.hidden = false;
  };

  const hideError = () => {
    if (!errorEl) return;
    errorEl.hidden = true;
  };

  if (wrapper) {
    wrapper.hidden = false;
  }

  let widgetId = null;
  waitForTurnstile()
    .then((turnstile) => {
      hideError();
      widgetId = turnstile.render(container, {
        sitekey: siteKey,
        callback(token) {
          tokenField.value = token;
          hideError();
        },
        'error-callback': () => {
          tokenField.value = '';
          showError('Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.');
        },
        'expired-callback': () => {
          tokenField.value = '';
          showError('Sicherheitsprüfung abgelaufen. Bitte erneut bestätigen.');
        },
      });
    })
    .catch(() => {
      showError('Sicherheitsprüfung konnte nicht geladen werden.');
    });

  return {
    isSolved: () => tokenField.value.trim() !== '',
    reset: () => {
      tokenField.value = '';
      if (widgetId !== null && typeof window.turnstile !== 'undefined') {
        window.turnstile.reset(widgetId);
      }
    },
    showError,
    hideError,
  };
};

const setupCaptcha = (form) => {
  const provider = (form.dataset.captchaProvider || '').toLowerCase();
  const siteKey = form.dataset.captchaSitekey || '';
  const errorEl = form.querySelector('[data-captcha-error]');

  if (provider === 'turnstile' && siteKey) {
    return setupTurnstileCaptcha(form, siteKey, errorEl);
  }

  if (errorEl) {
    errorEl.hidden = true;
  }

  const wrapper = form.querySelector('[data-captcha-wrapper]');
  if (wrapper) {
    wrapper.hidden = true;
  }

  return null;
};

// Kontaktformular: Honeypot + aria-live Modal
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contact-form');
  const modal = UIkit.modal('#contact-modal');
  const msg = document.getElementById('contact-modal-message');
  if (!form || !modal || !msg) return;

  const basePath = (window.basePath || '').replace(/\/+$/, '');
  const defaultEndpoint = `${basePath}/landing/contact`;

  const captcha = setupCaptcha(form);

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

    if (captcha) {
      captcha.hideError?.();
      if (!captcha.isSolved()) {
        captcha.showError?.('Bitte bestätigen Sie, dass Sie kein Bot sind.');
        msg.textContent = 'Bitte bestätigen Sie, dass Sie kein Bot sind.';
        modal.show();
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
        captcha?.reset?.();
      } else if (res.status === 400) {
        msg.textContent = 'Bitte überprüfen Sie Ihre Eingaben und versuchen Sie es erneut.';
        captcha?.reset?.();
      } else if (res.status === 429) {
        msg.textContent = 'Zu viele Versuche oder fehlgeschlagene Sicherheitsprüfung. Bitte warten Sie einen Moment.';
        captcha?.reset?.();
      } else {
        msg.textContent = 'Fehler beim Versenden. Bitte versuchen Sie es erneut.';
        captcha?.reset?.();
      }
      modal.show();
    }).catch(() => {
      msg.textContent = 'Fehler beim Versenden. Bitte versuchen Sie es erneut.';
      captcha?.reset?.();
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
