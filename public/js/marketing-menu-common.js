/**
 * Shared utilities for marketing menu administration pages.
 *
 * This module eliminates code duplication across the marketing-menu-*.js files.
 * Include this script before any marketing-menu page script.
 *
 * Usage:
 *   <script src="/js/marketing-menu-common.js"></script>
 *   <script src="/js/marketing-menu-overview.js"></script>
 */
(function (global) {
  'use strict';

  /**
   * Resolve the CSRF token from the page meta tag or global variable.
   * @returns {string}
   */
  const resolveCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || global.csrfToken || '';

  /**
   * Perform an authenticated fetch request with CSRF token and credentials.
   * Falls back to window.apiFetch when available (e.g. in test environments).
   *
   * @param {string} path
   * @param {RequestInit} [options={}]
   * @returns {Promise<Response>}
   */
  const apiFetch = (path, options = {}) => {
    if (typeof global.apiFetch === 'function') {
      return global.apiFetch(path, options);
    }
    const token = resolveCsrfToken();
    const headers = {
      ...(token ? { 'X-CSRF-Token': token } : {}),
      'X-Requested-With': 'fetch',
      ...(options.headers || {})
    };
    return fetch(path, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...options,
      headers
    });
  };

  /**
   * Resolve the currently selected namespace from the page namespace select
   * or from a fallback data attribute on the given container element.
   *
   * @param {HTMLElement|null} [fallbackContainer=null] element with data-namespace
   * @returns {string}
   */
  const resolveNamespace = (fallbackContainer = null) => {
    const select = document.getElementById('pageNamespaceSelect');
    const candidate = select?.value
      || (fallbackContainer ? fallbackContainer.dataset?.namespace : '')
      || '';
    return String(candidate || '').trim();
  };

  /**
   * Append a namespace query parameter to the given path.
   *
   * @param {string} path
   * @param {HTMLElement|null} [fallbackContainer=null]
   * @returns {string}
   */
  const withNamespace = (path, fallbackContainer = null) => {
    const namespace = resolveNamespace(fallbackContainer);
    if (!namespace) {
      return path;
    }
    const separator = path.includes('?') ? '&' : '?';
    return `${path}${separator}namespace=${encodeURIComponent(namespace)}`;
  };

  /**
   * Show a feedback message in a UIkit alert element.
   *
   * @param {HTMLElement|null} feedbackEl
   * @param {string} message
   * @param {'primary'|'success'|'danger'|'warning'} [status='primary']
   */
  const setFeedback = (feedbackEl, message, status = 'primary') => {
    if (!feedbackEl) {
      return;
    }
    feedbackEl.textContent = message;
    feedbackEl.classList.remove('uk-alert-primary', 'uk-alert-success', 'uk-alert-danger', 'uk-alert-warning');
    const cls = status === 'danger'
      ? 'uk-alert-danger'
      : status === 'success'
        ? 'uk-alert-success'
        : status === 'warning'
          ? 'uk-alert-warning'
          : 'uk-alert-primary';
    feedbackEl.classList.add(cls);
    feedbackEl.hidden = false;
  };

  /**
   * Hide a feedback alert element.
   *
   * @param {HTMLElement|null} feedbackEl
   */
  const hideFeedback = (feedbackEl) => {
    if (feedbackEl) {
      feedbackEl.hidden = true;
    }
  };

  /**
   * Filter a <select> element's options by locale.
   * Options whose data-locale does not match are hidden.
   *
   * @param {HTMLSelectElement|null} select
   * @param {string} locale
   */
  const filterMenuOptions = (select, locale) => {
    if (!select) {
      return;
    }
    const normalizedLocale = String(locale || '').trim().toLowerCase();
    Array.from(select.options).forEach((option) => {
      if (!option.value) {
        return;
      }
      const optionLocale = String(option.dataset?.locale || '').trim().toLowerCase();
      if (!optionLocale || !normalizedLocale) {
        option.hidden = false;
        return;
      }
      option.hidden = optionLocale !== normalizedLocale;
    });
  };

  // Export to global scope for non-module consumption
  global.marketingMenuCommon = {
    resolveCsrfToken,
    apiFetch,
    resolveNamespace,
    withNamespace,
    setFeedback,
    hideFeedback,
    filterMenuOptions
  };
})(typeof window !== 'undefined' ? window : globalThis);
