/* global UIkit */

export const normalizeBasePath = (candidate = '') => {
  const trimmed = String(candidate || '').trim();
  if (trimmed === '') {
    return '';
  }

  try {
    const parsed = new URL(trimmed, window.location.origin);
    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
      return parsed.pathname.replace(/\/$/, '');
    }
  } catch (error) {
    // Fall back to the raw path if parsing fails (e.g. relative paths)
  }

  return trimmed.replace(/\/$/, '');
};

export const basePath = normalizeBasePath(window.basePath || '');
export const withBase = path => basePath + path;
export const resolveWithBase = (path) => {
  if (typeof path !== 'string') {
    return withBase(String(path));
  }
  if (basePath && path.startsWith(basePath + '/')) {
    return path;
  }
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }
  return withBase(path);
};
export const resolveApiEndpoint = (path) => {
  if (typeof window.baseUrl === 'string' && window.baseUrl.trim() !== '') {
    try {
      const parsed = new URL(path, window.baseUrl);
      if (parsed.origin === window.location.origin) {
        return parsed.pathname + parsed.search + parsed.hash;
      }
    } catch (error) {
      // Ignore invalid base URLs and fall back to the base path resolution.
    }
  }

  return resolveWithBase(path);
};
export const normalizeEndpointToSameOrigin = (endpoint) => {
  const ensureString = value => (value === null || value === undefined ? '' : String(value));
  const trimmed = ensureString(endpoint).trim();
  if (trimmed === '') {
    return { endpoint: '', external: false, externalHost: null };
  }

  try {
    const parsed = new URL(trimmed, window.location.origin);
    const isAbsolute = /^https?:\/\//i.test(trimmed) || trimmed.startsWith('//');
    if (parsed.origin !== window.location.origin && isAbsolute) {
      return { endpoint: trimmed, external: true, externalHost: parsed.origin };
    }
    return {
      endpoint: parsed.pathname + parsed.search + parsed.hash,
      external: false,
      externalHost: null
    };
  } catch (error) {
    return { endpoint: trimmed, external: false, externalHost: null };
  }
};
export const warnExternalEndpoint = (host) => {
  const message = `External endpoint blocked: ${host || 'unknown origin'}`;
  if (typeof UIkit !== 'undefined' && UIkit.notification) {
    UIkit.notification({ message, status: 'warning', pos: 'top-center', timeout: 4000 });
  }
  if (typeof console !== 'undefined' && console.warn) {
    console.warn(message);
  }
};
export const escape = url => encodeURI(url);
export const transEventsFetchError = window.transEventsFetchError || 'Could not load events';
export const transDashboardLinkCopied = window.transDashboardLinkCopied || 'Link copied';
export const transDashboardLinkMissing = window.transDashboardLinkMissing || 'No link available';
export const transDashboardCopyFailed = window.transDashboardCopyFailed || 'Copy failed';
export const transDashboardTokenRotated = window.transDashboardTokenRotated || 'New token created';
export const transDashboardTokenRotateError = window.transDashboardTokenRotateError || 'Token could not be renewed';
export const transDashboardNoEvent = window.transDashboardNoEvent || 'No event selected';

export const parseBooleanOption = (candidate) => {
  if (candidate === null || candidate === undefined) {
    return null;
  }
  if (typeof candidate === 'boolean') {
    return candidate;
  }
  if (typeof candidate === 'number') {
    if (!Number.isFinite(candidate)) {
      return null;
    }
    if (candidate === 0) {
      return false;
    }
    if (candidate === 1) {
      return true;
    }
    return candidate > 0;
  }
  if (typeof candidate === 'string') {
    const normalized = candidate.trim().toLowerCase();
    if (normalized === '') {
      return null;
    }
    if (['1', 'true', 'yes', 'on'].includes(normalized)) {
      return true;
    }
    if (['0', 'false', 'no', 'off'].includes(normalized)) {
      return false;
    }
  }
  return null;
};

export const resolveBooleanOption = (value, fallback = false) => {
  const parsed = parseBooleanOption(value);
  if (parsed !== null) {
    return parsed;
  }
  const fallbackParsed = parseBooleanOption(fallback);
  if (fallbackParsed !== null) {
    return fallbackParsed;
  }
  return Boolean(fallback);
};

export const formUtils = {
  toArray(list) {
    if (Array.isArray(list)) {
      return list;
    }
    if (!list) {
      return [];
    }
    if (typeof list[Symbol.iterator] === 'function') {
      return Array.from(list);
    }
    return [];
  },
  checkBoxes(list, selectedValues = []) {
    const normalized = new Set(
      (Array.isArray(selectedValues) ? selectedValues : [])
        .map(value => String(value))
    );
    formUtils.toArray(list).forEach(input => {
      if (!input || typeof input.checked === 'undefined') {
        return;
      }
      input.checked = normalized.has(String(input.value));
    });
  },
  readChecked(list) {
    return formUtils.toArray(list)
      .filter(input => input && input.checked)
      .map(input => String(input.value));
  }
};

export function isAllowed(url, allowedPaths = []) {
  try {
    const parsed = new URL(url, window.location.origin);
    const domains = [];
    if (window.location.hostname) domains.push(window.location.hostname.toLowerCase());
    if (window.mainDomain) domains.push(window.mainDomain.toLowerCase());
    const host = parsed.hostname.toLowerCase();
    const domainOk = parsed.protocol === 'https:' && domains.some(d => host === d || host.endsWith('.' + d));
    const pathOk = !allowedPaths.length || allowedPaths.some(p => parsed.pathname.startsWith(p));
    return domainOk && pathOk;
  } catch (e) {
    return false;
  }
}
export const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
  window.csrfToken || '';
export const parseDatasetJson = (value, fallback = []) => {
  if (!value) {
    return fallback;
  }
  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : fallback;
  } catch (error) {
    return fallback;
  }
};
