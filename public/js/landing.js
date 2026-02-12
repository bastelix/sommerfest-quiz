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
  const defaultEndpoint = `${basePath}/api/contact-form`;

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
        msg.textContent = 'Danke! Wir melden uns mit Terminvorschlägen.';
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
    if (lang) {
      url.searchParams.set('lang', lang);
    } else {
      url.searchParams.delete('lang');
    }
    window.location.href = url.toString();
  }));
});

document.addEventListener('DOMContentLoaded', () => {
  const sliderElement = document.getElementById('scenario-slider');
  const pills = Array.from(document.querySelectorAll('#scenario-nav > li'));
  if (!sliderElement || !pills.length || !window.UIkit) {
    return;
  }

  const slider = UIkit.slider(sliderElement);
  const slides = Array.from(document.querySelectorAll('.usecase-slider > li'));

  const setActive = (index) => {
    pills.forEach((li, i) => li.classList.toggle('uk-active', i === index));
  };

  const setCurrent = (index) => {
    slides.forEach((li, i) => li.classList.toggle('uk-current', i === index));
  };

  pills.forEach((li, i) => {
    li.addEventListener('click', (event) => {
      event.preventDefault();
      slider.show(i);
      setActive(i);
      setCurrent(i);
    });
  });

  UIkit.util.on(sliderElement, 'itemshown', () => {
    setActive(slider.index);
    setCurrent(slider.index);
  });

  setActive(0);
  setCurrent(0);
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

document.addEventListener('DOMContentLoaded', () => {
  const gallery = document.querySelector('[data-proof-gallery]');
  if (!gallery) return;

  const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  let activeModal = null;
  let restoreFocus = null;

  const getFocusableElements = (modal) => {
    const elements = Array.from(modal.querySelectorAll(focusableSelector));
    return elements.filter((el) => {
      const isDisabled = el.hasAttribute('disabled') || el.getAttribute('aria-hidden') === 'true';
      if (isDisabled) return false;
      if (el.tabIndex === -1) return false;
      if (typeof el.getBoundingClientRect !== 'function') return true;
      const rect = el.getBoundingClientRect();
      return !(rect.width === 0 && rect.height === 0);
    });
  };

  const focusFirstElement = (modal) => {
    const initial = modal.querySelector('[data-proof-gallery-initial]');
    if (initial instanceof HTMLElement) {
      initial.focus();
      return;
    }
    const focusable = getFocusableElements(modal);
    if (focusable.length > 0) {
      focusable[0].focus();
    } else {
      modal.focus();
    }
  };

  const openModal = (modalId) => {
    const modal = gallery.querySelector(`[data-proof-gallery-modal="${modalId}"]`);
    if (!(modal instanceof HTMLElement)) return;

    restoreFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-visible');
    document.body.classList.add('calhelp-proof-gallery--modal-open');
    activeModal = modal;
    window.requestAnimationFrame(() => focusFirstElement(modal));
  };

  const getMaxTransitionDuration = (element) => {
    const style = window.getComputedStyle(element);
    const durations = style.transitionDuration.split(',').map((value) => parseFloat(value) || 0);
    const delays = style.transitionDelay.split(',').map((value) => parseFloat(value) || 0);
    const pairs = durations.map((duration, index) => duration + (delays[index] || 0));
    return Math.max(0, ...pairs);
  };

  const closeModal = () => {
    if (!activeModal) return;

    const modal = activeModal;
    modal.classList.remove('is-visible');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('calhelp-proof-gallery--modal-open');

    const duration = getMaxTransitionDuration(modal);
    if (duration > 0) {
      modal.addEventListener('transitionend', () => {
        modal.hidden = true;
      }, { once: true });
    } else {
      modal.hidden = true;
    }

    const focusTarget = restoreFocus;
    activeModal = null;
    restoreFocus = null;
    if (focusTarget instanceof HTMLElement) {
      focusTarget.focus();
    }
  };

  const handleTabKey = (event) => {
    if (!activeModal) return;
    const focusable = getFocusableElements(activeModal);
    if (focusable.length === 0) {
      event.preventDefault();
      activeModal.focus();
      return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const current = document.activeElement;

    if (event.shiftKey) {
      if (current === first || !activeModal.contains(current)) {
        event.preventDefault();
        last.focus();
      }
    } else if (current === last || !activeModal.contains(current)) {
      event.preventDefault();
      first.focus();
    }
  };

  gallery.querySelectorAll('[data-proof-gallery-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = button.getAttribute('data-proof-gallery-open');
      if (target) {
        openModal(target);
      }
    });
  });

  gallery.querySelectorAll('[data-proof-gallery-close]').forEach((button) => {
    button.addEventListener('click', () => {
      closeModal();
    });
  });

  gallery.querySelectorAll('[data-proof-gallery-modal]').forEach((modal) => {
    modal.addEventListener('pointerdown', (event) => {
      if (event.target === modal) {
        closeModal();
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    if (!activeModal) return;

    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
    } else if (event.key === 'Tab') {
      handleTabKey(event);
    }
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const steppers = document.querySelectorAll('[data-calhelp-stepper]');
  if (!steppers.length) return;

  const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  const prefersReducedMotion = () => reducedMotionQuery.matches;

  const htmlLang = (document.documentElement?.lang || '').toLowerCase();
  const currentLanguage = htmlLang.startsWith('en') ? 'en' : 'de';

  steppers.forEach((stepper) => {
    const stages = Array.from(stepper.querySelectorAll('[data-calhelp-step]'));
    if (!stages.length) return;

    const track = stepper.querySelector('[data-calhelp-slider-track]');
    const triggers = Array.from(stepper.querySelectorAll('[data-calhelp-step-trigger]'));
    const toggles = Array.from(stepper.querySelectorAll('[data-calhelp-step-toggle]'));
    const navItems = triggers.map((button) => button.closest('.calhelp-process__nav-item'));
    const stepIds = stages.map((stage) => stage.dataset.calhelpStep).filter(Boolean);
    if (!stepIds.length) return;

    const details = stepper.closest('details');
    let activeId = stepIds[0];
    let ignoreObserverUntil = 0;
    let observer = null;

    const updateToggleLabel = (button, expanded) => {
      const label = button.querySelector('.calhelp-process__toggle-label');
      if (label) {
        const stateKey = expanded ? 'Expanded' : 'Collapsed';
        const preferredSuffix = currentLanguage === 'en' ? 'En' : 'De';
        const fallbackSuffix = preferredSuffix === 'En' ? 'De' : 'En';
        const datasetKeyPreferred = `calhelpToggleLabel${preferredSuffix}${stateKey}`;
        const datasetKeyFallback = `calhelpToggleLabel${fallbackSuffix}${stateKey}`;
        const translatedLabel = label.dataset[datasetKeyPreferred] || label.dataset[datasetKeyFallback];
        if (translatedLabel) {
          label.textContent = translatedLabel;
        }
      }
    };

    const applyPanelState = (button, expanded) => {
      const panelId = button.getAttribute('aria-controls');
      if (!panelId) return;
      const panel = stepper.querySelector(`#${panelId}`);
      if (!(panel instanceof HTMLElement)) return;

      const shouldExpand = Boolean(expanded);
      button.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
      panel.hidden = !shouldExpand;
      panel.classList.toggle('is-open', shouldExpand);
      updateToggleLabel(button, shouldExpand);

      const stage = button.closest('[data-calhelp-step]');
      if (stage) {
        stage.classList.toggle('calhelp-process__stage--panel-open', shouldExpand);
      }
    };

    const togglePanel = (button) => {
      const isExpanded = button.getAttribute('aria-expanded') === 'true';
      applyPanelState(button, !isExpanded);
    };

    const setActive = (id, { force = false, focusStage = false } = {}) => {
      if (!id) return;
      const index = stepIds.indexOf(id);
      if (index === -1) return;
      if (activeId === id && !force) return;

      activeId = id;

      const isReducedMotion = prefersReducedMotion();
      const hasSliderTrack = track instanceof HTMLElement;
      const useSlider = hasSliderTrack && !isReducedMotion;

      stages.forEach((stage, stageIndex) => {
        const isActive = stageIndex === index;
        stage.classList.toggle('calhelp-process__stage--active', isActive);
        if (useSlider) {
          if (isActive) {
            stage.removeAttribute('aria-hidden');
            stage.setAttribute('tabindex', '-1');
          } else {
            stage.setAttribute('aria-hidden', 'true');
            stage.removeAttribute('tabindex');
          }
        } else {
          stage.removeAttribute('aria-hidden');
          stage.removeAttribute('tabindex');
        }
      });

      if (hasSliderTrack) {
        if (useSlider) {
          track.style.setProperty('--calhelp-process-index', String(index));
        } else {
          track.style.removeProperty('--calhelp-process-index');
        }
      }

      triggers.forEach((trigger, triggerIndex) => {
        const isActive = triggerIndex === index;
        trigger.classList.toggle('is-active', isActive);
        if (isActive) {
          trigger.setAttribute('aria-current', 'step');
        } else {
          trigger.removeAttribute('aria-current');
        }

        const navItem = navItems[triggerIndex];
        if (navItem) {
          navItem.classList.toggle('is-active', isActive);
          navItem.classList.toggle('is-complete', triggerIndex < index);
        }
      });

      toggles.forEach((toggle) => {
        const stage = toggle.closest('[data-calhelp-step]');
        if (!stage) return;
        const shouldOpen = stage.dataset.calhelpStep === id;
        applyPanelState(toggle, shouldOpen);
      });

      if (focusStage && useSlider) {
        const targetStage = stages[index];
        if (targetStage instanceof HTMLElement) {
          requestAnimationFrame(() => {
            targetStage.focus({ preventScroll: true });
          });
        }
      }
    };

    const enableObserver = () => {
      if (observer || !prefersReducedMotion()) return;
      observer = new IntersectionObserver((entries) => {
        if (Date.now() < ignoreObserverUntil) return;

        const visible = entries.filter((entry) => entry.isIntersecting);
        if (!visible.length) return;

        visible.sort((a, b) => {
          const aIndex = stepIds.indexOf(a.target.dataset.calhelpStep || '');
          const bIndex = stepIds.indexOf(b.target.dataset.calhelpStep || '');
          return aIndex - bIndex;
        });

        const candidate = visible[0];
        const nextId = candidate.target.dataset.calhelpStep;
        if (nextId) {
          setActive(nextId);
        }
      }, {
        rootMargin: '-40% 0px -40% 0px',
        threshold: [0.25, 0.5, 0.75]
      });

      stages.forEach((stage) => {
        observer?.observe(stage);
      });
    };

    const disableObserver = () => {
      if (!observer) return;
      stages.forEach((stage) => {
        observer?.unobserve(stage);
      });
      observer.disconnect();
      observer = null;
    };

    const motionPreferenceChanged = () => {
      if (prefersReducedMotion()) {
        if (!details || details.open) {
          enableObserver();
        } else {
          disableObserver();
        }
      } else {
        disableObserver();
      }
      setActive(activeId, { force: true });
    };

    if (typeof reducedMotionQuery.addEventListener === 'function') {
      reducedMotionQuery.addEventListener('change', motionPreferenceChanged);
    } else if (typeof reducedMotionQuery.addListener === 'function') {
      reducedMotionQuery.addListener(motionPreferenceChanged);
    }

    if (prefersReducedMotion() && (!details || details.open)) {
      enableObserver();
    }

    if (details) {
      details.addEventListener('toggle', () => {
        if (details.open) {
          setActive(activeId, { force: true });
          if (prefersReducedMotion()) {
            enableObserver();
          }
        } else {
          disableObserver();
        }
      });
    }

    triggers.forEach((trigger) => {
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        const id = trigger.getAttribute('data-calhelp-step-trigger');
        if (!id) return;

        const stage = stages.find((item) => item.dataset.calhelpStep === id);
        const isReducedMotion = prefersReducedMotion();
        setActive(id, { force: true, focusStage: !isReducedMotion });

        ignoreObserverUntil = Date.now() + 600;

        if (stage) {
          if (isReducedMotion) {
            stage.scrollIntoView({ behavior: 'auto', block: 'start', inline: 'nearest' });
          }
          const toggle = stage.querySelector('[data-calhelp-step-toggle]');
          if (toggle instanceof HTMLElement) {
            applyPanelState(toggle, true);
          }
        }
      });
    });

    toggles.forEach((toggle) => {
      toggle.addEventListener('click', () => {
        togglePanel(toggle);
      });
    });

    setActive(activeId, { force: true });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const lang = document.documentElement.lang?.toLowerCase().startsWith('en') ? 'en' : 'de';
  const datasetKey = lang === 'en' ? 'i18nEn' : 'i18nDe';

  document.querySelectorAll('[data-calhelp-i18n]').forEach((node) => {
    const attrTarget = node.getAttribute('data-calhelp-i18n-attr');
    const translation = datasetKey === 'i18nEn' ? node.dataset.i18nEn : node.dataset.i18nDe;
    if (!translation) return;

    if (attrTarget) {
      node.setAttribute(attrTarget, translation);
      return;
    }

    if (node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement) {
      node.value = translation;
      return;
    }

    node.textContent = translation;
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const containers = document.querySelectorAll('[data-calhelp-comparison]');
  if (!containers.length) return;

  const activateState = (card, state, { focusButton = false } = {}) => {
    const toggles = Array.from(card.querySelectorAll('[data-comparison-toggle]'));
    const states = Array.from(card.querySelectorAll('[data-comparison-state]'));
    if (!toggles.length || !states.length) return;

    const targetState = state || toggles[0].dataset.comparisonToggle;
    if (!targetState) return;

    toggles.forEach((button) => {
      const isActive = button.dataset.comparisonToggle === targetState;
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      button.classList.toggle('is-active', isActive);
      if (isActive && focusButton) {
        button.focus();
      }
    });

    states.forEach((panel) => {
      const isActive = panel.dataset.comparisonState === targetState;
      panel.hidden = !isActive;
      panel.classList.toggle('is-active', isActive);
      panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
    });
  };

  const handleKeyNavigation = (event, toggles) => {
    const { key, target } = event;
    if (!(target instanceof HTMLElement)) return;
    const currentIndex = toggles.indexOf(target);
    if (currentIndex === -1) return;

    let nextIndex = currentIndex;
    if (key === 'ArrowRight' || key === 'ArrowDown') {
      nextIndex = (currentIndex + 1) % toggles.length;
    } else if (key === 'ArrowLeft' || key === 'ArrowUp') {
      nextIndex = (currentIndex - 1 + toggles.length) % toggles.length;
    } else if (key === 'Home') {
      nextIndex = 0;
    } else if (key === 'End') {
      nextIndex = toggles.length - 1;
    } else {
      return;
    }

    event.preventDefault();
    const nextButton = toggles[nextIndex];
    const state = nextButton.dataset.comparisonToggle;
    if (state) {
      activateState(nextButton.closest('[data-calhelp-comparison-card]'), state, { focusButton: true });
    }
  };

  containers.forEach((container) => {
    const cards = Array.from(container.querySelectorAll('[data-calhelp-comparison-card]'));
    cards.forEach((card) => {
      const toggles = Array.from(card.querySelectorAll('[data-comparison-toggle]'));
      if (!toggles.length) return;

      const defaultState = card.getAttribute('data-calhelp-comparison-default');
      activateState(card, defaultState);

      toggles.forEach((button) => {
        button.addEventListener('click', () => {
          const state = button.dataset.comparisonToggle;
          if (state) {
            activateState(card, state);
          }
        });

        button.addEventListener('keydown', (event) => {
          handleKeyNavigation(event, toggles);
        });
      });
    });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const megaRoot = document.querySelector('[data-calhelp-mega-root]');
  if (!megaRoot) return;

  const triggers = Array.from(megaRoot.querySelectorAll('[data-calhelp-explain]'));
  const panes = Array.from(megaRoot.querySelectorAll('[data-calhelp-pane]'));
  if (!triggers.length || !panes.length) return;

  const getId = (element) => element.getAttribute('data-calhelp-explain');

  const setActive = (id) => {
    if (!id) return;

    const activePane = panes.find((pane) => pane.getAttribute('data-calhelp-pane') === id);
    if (!activePane) return;

    panes.forEach((pane) => {
      const isActive = pane === activePane;
      pane.classList.toggle('is-active', isActive);
      pane.setAttribute('aria-hidden', isActive ? 'false' : 'true');
    });

    triggers.forEach((trigger) => {
      const isActive = getId(trigger) === id;
      trigger.classList.toggle('is-active', isActive);
      trigger.setAttribute('aria-expanded', isActive ? 'true' : 'false');
    });

    megaRoot.setAttribute('data-calhelp-active', id);
  };

  const handleTrigger = (event) => {
    const trigger = event.currentTarget;
    const id = getId(trigger);
    if (!id) return;
    setActive(id);
  };

  triggers.forEach((trigger) => {
    trigger.addEventListener('focus', handleTrigger);
    trigger.addEventListener('mouseenter', handleTrigger);
    trigger.addEventListener('pointerenter', handleTrigger);
    trigger.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        handleTrigger(event);
      }
    });
  });

  const defaultId = megaRoot.getAttribute('data-calhelp-default') || (triggers[0] ? getId(triggers[0]) : '');
  setActive(defaultId);
});
