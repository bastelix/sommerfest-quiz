/**
 * AJAX submission handler for contact-form block instances.
 *
 * Attaches to all <form class="contact-form"> elements within a given root
 * and handles submission via fetch, showing inline success/error feedback.
 *
 * @module contact-form-handler
 */

/**
 * Initialise contact form handlers on all matching forms within root.
 *
 * @param {HTMLElement} [root=document] Container to search for forms
 */
export function initContactForms(root = document) {
  const forms = root.querySelectorAll('form.contact-form, form#contact-form');
  forms.forEach(attachHandler);
}

/**
 * @param {HTMLFormElement} form
 */
function attachHandler(form) {
  if (form.dataset.contactInitialized === 'true') {
    return;
  }
  form.dataset.contactInitialized = 'true';

  // Skip preview forms in the editor
  if (form.dataset.previewSubmit === 'true') {
    return;
  }

  const statusEl = form.querySelector('[data-contact-status]');
  const submitBtn = form.querySelector('button[type="submit"]');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Honeypot — silently abort
    const hp = form.querySelector('input[name="company"]');
    if (hp && hp.value.trim() !== '') {
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.classList.add('uk-disabled');
    }
    showStatus(statusEl, '', 'none');

    const endpoint = form.getAttribute('data-contact-endpoint') || form.action;
    const csrfToken = resolveCsrfToken(form);

    const formData = new FormData(form);
    const body = {};
    formData.forEach((val, key) => {
      // Skip the privacy checkbox and honeypot from the JSON payload
      if (key !== 'privacy') {
        body[key] = val;
      }
    });

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
          'X-Requested-With': 'fetch',
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(body)
      });

      if (res.ok) {
        const successMsg = form.dataset.successMessage
          || 'Vielen Dank! Wir melden uns in Kürze.';
        form.reset();
        showStatus(statusEl, successMsg, 'success');
      } else {
        let errorMsg = 'Fehler beim Versenden. Bitte versuchen Sie es erneut.';
        try {
          const json = await res.json();
          if (json.error) {
            errorMsg = json.error;
          }
        } catch { /* ignore parse error */ }
        showStatus(statusEl, errorMsg, 'error');
      }
    } catch {
      showStatus(statusEl, 'Netzwerkfehler. Bitte versuchen Sie es erneut.', 'error');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('uk-disabled');
      }
    }
  });
}

/**
 * Resolve CSRF token from form hidden field or page meta tag.
 *
 * @param {HTMLFormElement} form
 * @returns {string}
 */
function resolveCsrfToken(form) {
  const hidden = form.querySelector('input[name="csrf_token"]');
  if (hidden && hidden.value) {
    return hidden.value;
  }

  if (typeof window !== 'undefined' && typeof window.csrfToken === 'string') {
    return window.csrfToken;
  }

  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) {
    return meta.getAttribute('content') || '';
  }

  return '';
}

/**
 * @param {HTMLElement|null} el
 * @param {string} message
 * @param {'success'|'error'|'none'} type
 */
function showStatus(el, message, type) {
  if (!el) {
    return;
  }

  if (!message || type === 'none') {
    el.hidden = true;
    el.textContent = '';
    return;
  }

  el.hidden = false;
  el.textContent = message;
  el.className = 'contact-form__status uk-margin-small-top';
  if (type === 'success') {
    el.classList.add('uk-alert', 'uk-alert-success');
  } else if (type === 'error') {
    el.classList.add('uk-alert', 'uk-alert-danger');
  }
}
