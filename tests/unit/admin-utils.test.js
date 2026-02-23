import {
  parseBooleanOption,
  resolveBooleanOption,
  parseDatasetJson,
  formUtils,
  normalizeBasePath,
  escape,
  isAllowed,
} from '../../public/js/admin-utils.js';

// ── parseBooleanOption ───────────────────────────────────────────────────────

describe('parseBooleanOption', () => {
  it('returns null for null', () => {
    expect(parseBooleanOption(null)).toBe(null);
  });

  it('returns null for undefined', () => {
    expect(parseBooleanOption(undefined)).toBe(null);
  });

  it('returns boolean values unchanged', () => {
    expect(parseBooleanOption(true)).toBe(true);
    expect(parseBooleanOption(false)).toBe(false);
  });

  it('converts integer 1 to true', () => {
    expect(parseBooleanOption(1)).toBe(true);
  });

  it('converts integer 0 to false', () => {
    expect(parseBooleanOption(0)).toBe(false);
  });

  it('returns true for positive numbers other than 1', () => {
    expect(parseBooleanOption(42)).toBe(true);
  });

  it('returns null for non-finite numbers', () => {
    expect(parseBooleanOption(NaN)).toBe(null);
    expect(parseBooleanOption(Infinity)).toBe(null);
  });

  it.each([['1', true], ['true', true], ['yes', true], ['on', true]])(
    'parses truthy string "%s" as true',
    (input, expected) => {
      expect(parseBooleanOption(input)).toBe(expected);
    }
  );

  it.each([['0', false], ['false', false], ['no', false], ['off', false]])(
    'parses falsy string "%s" as false',
    (input, expected) => {
      expect(parseBooleanOption(input)).toBe(expected);
    }
  );

  it('is case-insensitive for string inputs', () => {
    expect(parseBooleanOption('TRUE')).toBe(true);
    expect(parseBooleanOption('False')).toBe(false);
    expect(parseBooleanOption('YES')).toBe(true);
  });

  it('returns null for empty string', () => {
    expect(parseBooleanOption('')).toBe(null);
    expect(parseBooleanOption('   ')).toBe(null);
  });

  it('returns null for unrecognized strings', () => {
    expect(parseBooleanOption('maybe')).toBe(null);
    expect(parseBooleanOption('2')).toBe(null);
  });
});

// ── resolveBooleanOption ─────────────────────────────────────────────────────

describe('resolveBooleanOption', () => {
  it('returns the parsed value when candidate is valid', () => {
    expect(resolveBooleanOption('yes')).toBe(true);
    expect(resolveBooleanOption('no')).toBe(false);
  });

  it('falls back to the fallback boolean when candidate is null', () => {
    expect(resolveBooleanOption(null, true)).toBe(true);
    expect(resolveBooleanOption(null, false)).toBe(false);
  });

  it('defaults to false when both candidate and fallback are null', () => {
    expect(resolveBooleanOption(null, null)).toBe(false);
  });

  it('parses string fallback', () => {
    expect(resolveBooleanOption(null, 'yes')).toBe(true);
    expect(resolveBooleanOption(null, '0')).toBe(false);
  });
});

// ── parseDatasetJson ─────────────────────────────────────────────────────────

describe('parseDatasetJson', () => {
  it('returns fallback for empty/falsy input', () => {
    expect(parseDatasetJson('', [])).toEqual([]);
    expect(parseDatasetJson(null, [])).toEqual([]);
    expect(parseDatasetJson(undefined, [])).toEqual([]);
  });

  it('parses a valid JSON array', () => {
    expect(parseDatasetJson('["a","b"]')).toEqual(['a', 'b']);
  });

  it('returns fallback for non-array JSON', () => {
    expect(parseDatasetJson('{"key":"value"}', [])).toEqual([]);
    expect(parseDatasetJson('"string"', [])).toEqual([]);
  });

  it('returns fallback for malformed JSON', () => {
    expect(parseDatasetJson('not json', [])).toEqual([]);
    expect(parseDatasetJson('{', [])).toEqual([]);
  });

  it('uses the provided fallback, not always []', () => {
    expect(parseDatasetJson('bad', ['default'])).toEqual(['default']);
  });
});

// ── escape ───────────────────────────────────────────────────────────────────

describe('escape', () => {
  it('encodes spaces in a URI path', () => {
    expect(escape('/path/to page')).toBe('/path/to%20page');
  });

  it('preserves reserved characters that encodeURI keeps', () => {
    expect(escape('https://example.com/path?q=1&r=2')).toBe(
      'https://example.com/path?q=1&r=2'
    );
  });
});

// ── normalizeBasePath ────────────────────────────────────────────────────────

describe('normalizeBasePath', () => {
  it('returns empty string for empty input', () => {
    expect(normalizeBasePath('')).toBe('');
    expect(normalizeBasePath()).toBe('');
  });

  it('strips trailing slash from a relative path', () => {
    expect(normalizeBasePath('/app/')).toBe('/app');
  });

  it('preserves a relative path without trailing slash', () => {
    expect(normalizeBasePath('/app')).toBe('/app');
  });

  it('extracts pathname from a full https URL', () => {
    expect(normalizeBasePath('https://example.com/app')).toBe('/app');
  });

  it('extracts pathname from absolute URL and strips trailing slash', () => {
    expect(normalizeBasePath('https://example.com/app/')).toBe('/app');
  });
});

// ── formUtils ────────────────────────────────────────────────────────────────

describe('formUtils.toArray', () => {
  it('returns an array as-is', () => {
    expect(formUtils.toArray(['a', 'b'])).toEqual(['a', 'b']);
  });

  it('converts a Set to array', () => {
    const result = formUtils.toArray(new Set([1, 2, 3]));
    expect(result).toEqual([1, 2, 3]);
  });

  it('returns empty array for null', () => {
    expect(formUtils.toArray(null)).toEqual([]);
  });

  it('returns empty array for undefined', () => {
    expect(formUtils.toArray(undefined)).toEqual([]);
  });

  it('returns empty array for non-iterable objects', () => {
    expect(formUtils.toArray(42)).toEqual([]);
  });
});

describe('formUtils.checkBoxes', () => {
  function makeCheckbox(value, checked = false) {
    return { value, checked };
  }

  it('checks boxes whose values are in selectedValues', () => {
    const boxes = [makeCheckbox('a'), makeCheckbox('b'), makeCheckbox('c')];
    formUtils.checkBoxes(boxes, ['a', 'c']);
    expect(boxes[0].checked).toBe(true);
    expect(boxes[1].checked).toBe(false);
    expect(boxes[2].checked).toBe(true);
  });

  it('coerces numeric values to string before comparing', () => {
    const boxes = [makeCheckbox('1'), makeCheckbox('2')];
    formUtils.checkBoxes(boxes, [1]);
    expect(boxes[0].checked).toBe(true);
    expect(boxes[1].checked).toBe(false);
  });

  it('unchecks all when selectedValues is empty', () => {
    const boxes = [makeCheckbox('a', true), makeCheckbox('b', true)];
    formUtils.checkBoxes(boxes, []);
    expect(boxes[0].checked).toBe(false);
    expect(boxes[1].checked).toBe(false);
  });

  it('skips elements without a checked property', () => {
    expect(() => formUtils.checkBoxes([null, { value: 'x' }], ['x'])).not.toThrow();
  });
});

describe('formUtils.readChecked', () => {
  function makeCheckbox(value, checked) {
    return { value, checked };
  }

  it('returns values of checked checkboxes as strings', () => {
    const boxes = [makeCheckbox('a', true), makeCheckbox('b', false), makeCheckbox('c', true)];
    expect(formUtils.readChecked(boxes)).toEqual(['a', 'c']);
  });

  it('returns empty array when nothing is checked', () => {
    expect(formUtils.readChecked([makeCheckbox('a', false)])).toEqual([]);
  });

  it('returns empty array for empty list', () => {
    expect(formUtils.readChecked([])).toEqual([]);
  });
});

// ── isAllowed ────────────────────────────────────────────────────────────────
// window.location is set to https://example.com in setup.js
// so window.location.hostname === 'example.com'

describe('isAllowed', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('allows https URL on same origin', () => {
    expect(isAllowed('https://example.com/api', [])).toBe(true);
  });

  it('blocks http URLs even on same hostname', () => {
    expect(isAllowed('http://example.com/api', [])).toBe(false);
  });

  it('blocks URLs on different domains', () => {
    expect(isAllowed('https://evil.com/api', [])).toBe(false);
  });

  it('restricts to allowedPaths when provided and path matches', () => {
    expect(isAllowed('https://example.com/api/data', ['/api'])).toBe(true);
  });

  it('blocks when allowedPaths provided but path does not match', () => {
    expect(isAllowed('https://example.com/other', ['/api'])).toBe(false);
  });

  it('allows subdomains of window.mainDomain', () => {
    vi.stubGlobal('mainDomain', 'example.com');
    expect(isAllowed('https://sub.example.com/api', [])).toBe(true);
  });

  it('returns false for javascript: protocol URL', () => {
    // javascript: URLs have no host and protocol !== 'https:' so they are blocked
    expect(isAllowed('javascript:alert(1)', [])).toBe(false);
  });
});

// ── basePath / withBase – module-level constants ──────────────────────────────
// These are frozen at import time, so we use vi.resetModules() + dynamic import()

describe('basePath module constant', () => {
  beforeEach(() => {
    vi.resetModules();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('is empty string when window.basePath is not set', async () => {
    vi.stubGlobal('basePath', undefined);
    const { basePath } = await import('../../public/js/admin-utils.js');
    expect(basePath).toBe('');
  });

  it('normalizes window.basePath on module load (strips trailing slash)', async () => {
    vi.stubGlobal('basePath', '/app/');
    const { basePath } = await import('../../public/js/admin-utils.js');
    expect(basePath).toBe('/app');
  });

  it('withBase prepends basePath to a path', async () => {
    vi.stubGlobal('basePath', '/app');
    const { withBase } = await import('../../public/js/admin-utils.js');
    expect(withBase('/teams')).toBe('/app/teams');
  });
});

// ── getCsrfToken ─────────────────────────────────────────────────────────────

describe('getCsrfToken', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    // Clean up any meta tags added by tests
    document.querySelectorAll('meta[name="csrf-token"]').forEach(el => el.remove());
  });

  it('reads from meta[name="csrf-token"] when present', async () => {
    vi.resetModules();
    const meta = document.createElement('meta');
    meta.name = 'csrf-token';
    meta.content = 'abc123';
    document.head.appendChild(meta);

    const { getCsrfToken } = await import('../../public/js/admin-utils.js');
    expect(getCsrfToken()).toBe('abc123');
  });

  it('falls back to window.csrfToken when no meta tag is present', async () => {
    vi.resetModules();
    vi.stubGlobal('csrfToken', 'window-token');
    const { getCsrfToken } = await import('../../public/js/admin-utils.js');
    expect(getCsrfToken()).toBe('window-token');
  });

  it('returns empty string when neither source is present', async () => {
    vi.resetModules();
    vi.stubGlobal('csrfToken', undefined);
    const { getCsrfToken } = await import('../../public/js/admin-utils.js');
    expect(getCsrfToken()).toBe('');
  });
});
