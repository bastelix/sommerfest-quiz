import { Editor, Extension, Mark } from './vendor/tiptap/core.esm.js';
import StarterKit from './vendor/tiptap/starter-kit.esm.js';
import BlockContentEditor from './components/block-content-editor.js';
import PreviewCanvas from './components/preview-canvas.js';

/* global notify */

// Define custom UIkit templates for the page editor
const DOMPURIFY_PAGE_EDITOR_CONFIG = {
  ADD_TAGS: ['video', 'source'],
  ADD_ATTR: [
    'autoplay',
    'muted',
    'loop',
    'playsinline',
    'preload',
    'src',
    'type',
    'aria-label',
    /^(uk|data-uk)-.*$/
  ],
  ALLOW_DATA_ATTR: true
};

const hasValidSrcsetDescriptor = descriptor => {
  const trimmed = descriptor.trim();
  if (trimmed === '' || /[{}/]/.test(trimmed)) {
    return false;
  }

  const lastToken = trimmed.split(/\s+/).pop();
  if (!lastToken) {
    return false;
  }

  return /^[0-9]+(?:\.[0-9]+)?[wx]$/.test(lastToken);
};

const hasValidSrcset = srcset => {
  if (!srcset) {
    return true;
  }

  return srcset.split(',').every(hasValidSrcsetDescriptor);
};

const scrubInvalidSrcsetAttributes = html => {
  const stripInvalidSrcsets = markup => markup.replace(/\s+srcset\s*=\s*("([^"]*)"|'([^']*)'|([^\s>]+))/gi, (match, _full, doubleQuoted, singleQuoted, unquoted) => {
    const value = doubleQuoted ?? singleQuoted ?? unquoted ?? '';
    return hasValidSrcset(value) ? match : '';
  });

  const container = document.createElement('div');
  container.innerHTML = stripInvalidSrcsets(html);

  container.querySelectorAll('img, source').forEach(el => {
    const srcset = el.getAttribute('srcset');
    if (!srcset) {
      return;
    }

    if (!hasValidSrcset(srcset)) {
      el.removeAttribute('srcset');
    }
  });

  return container.innerHTML;
};

const sanitize = str => {
  const value = typeof str === 'string' ? str : String(str ?? '');
  let sanitized = value;
  if (window.DOMPurify && typeof window.DOMPurify.sanitize === 'function') {
    sanitized = window.DOMPurify.sanitize(value, DOMPURIFY_PAGE_EDITOR_CONFIG);
  } else if (window.console && typeof window.console.warn === 'function') {
    window.console.warn('DOMPurify not available, skipping HTML sanitization for page editor content.');
  }

  return scrubInvalidSrcsetAttributes(sanitized);
};

// The block editor owns the content model; the preview only consumes the
// serialized blocks, forwards them into the renderer, and mirrors selection via
// data-block-id. This keeps the editor and renderer isolated while still
// synchronizing intent across both surfaces.
const blockPreviewBindings = new WeakMap();

const safeParseBlocks = value => {
  if (!value) {
    return { blocks: [], meta: {}, id: null };
  }
  try {
    const parsed = typeof value === 'string' ? JSON.parse(value) : value;
    return {
      blocks: Array.isArray(parsed?.blocks) ? parsed.blocks : [],
      meta: parsed?.meta || {},
      id: parsed?.id || null
    };
  } catch (error) {
    return { blocks: [], meta: {}, id: null };
  }
};

const readBlockEditorState = editor => {
  try {
    return safeParseBlocks(typeof editor?.getContent === 'function'
      ? editor.getContent()
      : editor?.state);
  } catch (error) {
    notify(error.message || 'Ungültige Blöcke', 'danger');
    return { blocks: [], meta: {}, id: null };
  }
};

const ensurePreviewSlots = form => {
  const editorEl = getEditorElement(form);
  if (!editorEl || !editorEl.parentNode) {
    return null;
  }

  let layout = form.querySelector('[data-page-preview-layout="true"]');
  if (layout) {
    return {
      layout,
      previewRoot: layout.querySelector('[data-preview-canvas="true"]'),
      editorPane: layout.querySelector('[data-editor-pane="true"]')
    };
  }

  layout = document.createElement('div');
  layout.dataset.pagePreviewLayout = 'true';
  layout.className = 'page-editor-preview-layout';

  const editorPane = document.createElement('div');
  editorPane.dataset.editorPane = 'true';
  editorPane.className = 'page-editor-pane';

  const previewPane = document.createElement('div');
  previewPane.dataset.previewPane = 'true';
  previewPane.className = 'page-preview-pane';

  const previewTitle = document.createElement('div');
  previewTitle.className = 'page-preview-title';
  previewTitle.textContent = 'Live-Vorschau';

  const previewRoot = document.createElement('div');
  previewRoot.dataset.previewCanvas = 'true';

  previewPane.append(previewTitle, previewRoot);
  layout.append(editorPane, previewPane);

  editorEl.parentNode.insertBefore(layout, editorEl);
  editorPane.append(editorEl);

  return { layout, previewRoot, editorPane };
};

const teardownBlockPreview = form => {
  const binding = blockPreviewBindings.get(form);
  if (!binding) {
    return;
  }
  const { editor, preview, restoreRender } = binding;
  if (editor) {
    editor.render = restoreRender;
  }
  if (preview && typeof preview.destroy === 'function') {
    preview.destroy();
  }
  blockPreviewBindings.delete(form);
};

const attachBlockPreview = (form, editor) => {
  if (!form || !editor) {
    return null;
  }
  const existing = blockPreviewBindings.get(form);
  if (existing) {
    existing.sync();
    return existing;
  }

  const slots = ensurePreviewSlots(form);
  if (!slots || !slots.previewRoot) {
    return null;
  }

  const preview = new PreviewCanvas(slots.previewRoot, {
    onSelect: blockId => {
      if (typeof editor.selectBlock === 'function') {
        editor.selectBlock(blockId);
      }
    }
  });

  const sync = () => {
    const snapshot = readBlockEditorState(editor);
    const highlight = editor?.state?.selectedBlockId || null;
    preview.setBlocks(snapshot.blocks || [], highlight);
  };

  const originalRender = editor.render.bind(editor);
  editor.render = () => {
    originalRender();
    sync();
  };

  sync();

  const binding = { preview, sync, restoreRender: originalRender, editor };
  blockPreviewBindings.set(form, binding);
  return binding;
};

const THEME_LIGHT = 'light';
const THEME_DARK = 'dark';
const THEME_HIGH_CONTRAST = 'high-contrast';
const THEME_STORAGE_KEY = 'pageEditorTheme';
const THEME_CHOICES = [THEME_LIGHT, THEME_DARK, THEME_HIGH_CONTRAST];

const PAGE_EDITOR_MODE = (window.pageEditorMode || window.pageEditorDriver || 'tiptap').toLowerCase();
const USE_BLOCK_EDITOR = PAGE_EDITOR_MODE === 'blocks';

const basePath = (window.basePath || '').replace(/\/$/, '');
const withBase = path => `${basePath}${path}`;

const buildPagePreviewUrl = slug => {
  const safeSlug = typeof slug === 'string' ? slug.trim() : '';
  if (!safeSlug) {
    return null;
  }
  const path = withNamespace(`/m/${encodeURIComponent(safeSlug)}`);
  return withBase(path);
};

const openPreviewInNewTab = slug => {
  const previewUrl = buildPagePreviewUrl(slug);
  if (!previewUrl) {
    return null;
  }
  const handle = window.open(previewUrl, '_blank', 'noopener');
  if (!handle && typeof notify === 'function') {
    notify('Vorschau konnte nicht geöffnet werden', 'danger');
  }
  return handle;
};

const escapeHtml = value => {
  if (typeof value !== 'string') {
    return '';
  }
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
};

let quizLinksCache = null;
let quizLinksPromise = null;

const normalizeQuizLink = entry => {
  if (!entry || typeof entry !== 'object') {
    return null;
  }
  const slug = typeof entry.slug === 'string' ? entry.slug.trim() : '';
  if (!slug) {
    return null;
  }
  return {
    slug,
    name: typeof entry.name === 'string' ? entry.name.trim() : '',
    description: typeof entry.description === 'string' ? entry.description.trim() : ''
  };
};

const fetchQuizLinks = async () => {
  if (quizLinksCache) {
    return quizLinksCache;
  }
  if (!quizLinksPromise) {
    quizLinksPromise = apiFetch('/kataloge/catalogs.json', { headers: { Accept: 'application/json' } })
      .then(response => (response.ok ? response.json() : []))
      .catch(() => [])
      .then(rows => (Array.isArray(rows) ? rows.map(normalizeQuizLink).filter(Boolean) : []));
  }

  const links = await quizLinksPromise;
  quizLinksCache = links;
  quizLinksPromise = null;
  return links;
};

const prefetchQuizLinks = () => fetchQuizLinks().catch(() => []);

const createQuizLinkHref = slug => {
  const safeSlug = encodeURIComponent(slug || '');
  return `${basePath}/?katalog=${safeSlug}`;
};

const UikitTemplates = Extension.create({
  name: 'uikitTemplates',
  addCommands() {
    const heroTemplate = `<div class="uk-section uk-section-primary uk-light">
                <div class="uk-container">
                  <h1 class="uk-heading-large">Hero-Titel</h1>
                  <p class="uk-text-lead">Introtext</p>
                </div>
              </div>
              <!-- Beispiel: Abschnitt mit alternierender Hintergrundfarbe -->
              <section class="uk-section section--alt">
                <div class="uk-container">
                  <h2>Abschnittstitel</h2>
                  <p>Inhalt hier</p>
                </div>
              </section>`;
    const cardTemplate = `<div class="uk-card qr-card uk-card-body">
                <h3 class="uk-card-title">Karte</h3>
                <p>Inhalt hier</p>
              </div>`;

    return {
      insertHeroTemplate: () => ({ commands }) => commands.insertContent(heroTemplate),
      insertCardTemplate: () => ({ commands }) => commands.insertContent(cardTemplate)
    };
  },
  addKeyboardShortcuts() {
    return {
      'Mod-Alt-h': () => this.editor.commands.insertHeroTemplate(),
      'Mod-Alt-c': () => this.editor.commands.insertCardTemplate()
    };
  }
});

const Link = Mark.create({
  name: 'link',
  inclusive: false,
  priority: 1000,
  addAttributes() {
    return {
      href: {
        default: null
      },
      target: {
        default: null
      },
      rel: {
        default: null
      }
    };
  },
  parseHTML() {
    return [
      {
        tag: 'a[href]'
      }
    ];
  },
  renderHTML({ HTMLAttributes }) {
    return ['a', HTMLAttributes, 0];
  },
  addCommands() {
    return {
      setLink: attributes => ({ chain }) => chain().setMark(this.name, attributes).run(),
      toggleLink: attributes => ({ chain }) => chain().toggleMark(this.name, attributes).run(),
      unsetLink: () => ({ chain }) => chain().unsetMark(this.name).run()
    };
  }
});

const QuizLink = Mark.create({
  name: 'quizLink',
  inclusive: false,
  addAttributes() {
    return {
      href: { default: null },
      class: {
        default: 'uk-button uk-button-primary'
      },
      title: { default: null }
    };
  },
  parseHTML() {
    return [
      {
        tag: 'a.uk-button.uk-button-primary'
      }
    ];
  },
  renderHTML({ HTMLAttributes }) {
    const attrs = { ...HTMLAttributes, class: 'uk-button uk-button-primary' };
    return ['a', attrs, 0];
  },
  addCommands() {
    return {
      insertQuizLink: options => ({ commands }) => {
        const slug = options?.slug || '';
        const label = options?.label || slug;
        const description = options?.description || label;
        if (!slug || !label) {
          return false;
        }
        const href = createQuizLinkHref(slug);
        const safeLabel = escapeHtml(label);
        const safeTitle = escapeHtml(description || label);
        return commands.insertContent(
          `<a class="uk-button uk-button-primary" href="${href}" title="${safeTitle}">${safeLabel}</a>`
        );
      }
    };
  }
});

let currentTheme = null;

const normalizeTheme = candidate => {
  if (THEME_CHOICES.includes(candidate)) {
    return candidate;
  }
  return THEME_LIGHT;
};

const readStoredTheme = () => {
  try {
    const stored = window.localStorage?.getItem?.(THEME_STORAGE_KEY);
    return stored ? normalizeTheme(stored) : null;
  } catch (error) {
    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('Unable to read stored editor theme', error);
    }
    return null;
  }
};

const saveThemePreference = theme => {
  try {
    window.localStorage?.setItem?.(THEME_STORAGE_KEY, normalizeTheme(theme));
  } catch (error) {
    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('Unable to store editor theme', error);
    }
  }
};

const resolveInitialTheme = () => {
  const stored = readStoredTheme();
  if (stored) {
    return stored;
  }
  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    return THEME_DARK;
  }
  return THEME_LIGHT;
};

const getCurrentTheme = () => {
  if (!currentTheme) {
    currentTheme = resolveInitialTheme();
  }
  return currentTheme;
};

const setDarkStylesheetEnabled = enabled => {
  const darkLink = document.getElementById('admin-dark-css');
  if (!darkLink) {
    return;
  }
  darkLink.media = enabled ? 'all' : 'not all';
  darkLink.disabled = !enabled;
};

const updateThemeToggleButtons = theme => {
  document.querySelectorAll('[data-theme-choice]').forEach(button => {
    const isActive = button.dataset.themeChoice === theme;
    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    button.classList.toggle('uk-button-primary', isActive);
    button.classList.toggle('uk-button-default', !isActive);
  });
};

const updatePageEditorTheme = theme => {
  const activeTheme = normalizeTheme(theme);
  document.querySelectorAll('.page-editor').forEach(editor => {
    editor.dataset.theme = activeTheme;
    editor.classList.toggle('high-contrast', activeTheme === THEME_HIGH_CONTRAST);
    if (activeTheme === THEME_DARK) {
      editor.classList.add('dark-mode');
    } else {
      editor.classList.remove('dark-mode');
    }
    if (editor.classList.contains('landing-editor')) {
      applyLandingStyling(editor, activeTheme);
    }
  });

  const preview = document.getElementById('preview-content');
  if (preview) {
    preview.dataset.theme = activeTheme;
    preview.classList.toggle('high-contrast', activeTheme === THEME_HIGH_CONTRAST);
    if (activeTheme === THEME_DARK) {
      preview.classList.add('dark-mode');
    } else {
      preview.classList.remove('dark-mode');
    }
  }
};

const applyThemePreference = theme => {
  const targetTheme = normalizeTheme(theme);
  currentTheme = targetTheme;
  saveThemePreference(targetTheme);

  document.body.dataset.theme = targetTheme;
  document.body.classList.toggle('high-contrast', targetTheme === THEME_HIGH_CONTRAST);
  const enableDark = targetTheme === THEME_DARK;
  document.body.classList.toggle('dark-mode', enableDark);
  if (targetTheme !== THEME_DARK) {
    document.body.classList.remove('dark-mode');
  }

  setDarkStylesheetEnabled(enableDark);
  updatePageEditorTheme(targetTheme);
  updateThemeToggleButtons(targetTheme);
};

const initThemeToggle = () => {
  const activeTheme = getCurrentTheme();
  applyThemePreference(activeTheme);

  const toggle = document.querySelector('[data-theme-toggle]');
  if (!toggle || toggle.dataset.bound === '1') {
    return;
  }

  toggle.addEventListener('click', event => {
    const button = event.target?.closest?.('[data-theme-choice]');
    if (!button || !toggle.contains(button)) {
      return;
    }
    const choice = normalizeTheme(button.dataset.themeChoice);
    applyThemePreference(choice);
  });

  toggle.dataset.bound = '1';
};

const resolvePageNamespace = () => {
  const select = document.getElementById('pageNamespaceSelect');
  const candidate = select?.value || select?.dataset.pageNamespace || window.pageNamespace || '';
  return String(candidate || '').trim();
};

const withNamespace = (url) => {
  const namespace = resolvePageNamespace();
  if (!namespace) {
    return url;
  }
  const separator = url.includes('?') ? '&' : '?';
  return `${url}${separator}namespace=${encodeURIComponent(namespace)}`;
};

const apiFetch = (path, options = {}) => {
  const fetcher = window.apiFetch || ((url, opts = {}) => fetch(url, opts));
  return fetcher(path, options);
};

const UIKIT_FILENAME = 'uikit.min.css';
const LANDING_STYLE_FILENAMES = [
  'landing.css',
  'onboarding.css',
  'topbar.landing.css'
];

const LANDING_NAMESPACE_ASSET_FOLDERS = new Set([
  'calhelp'
]);

const LANDING_FONT_URL = 'https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap';

const landingStylesPromises = {};

const fetchCssWithFallback = paths => {
  const candidates = Array.isArray(paths) ? paths.filter(Boolean) : [paths].filter(Boolean);
  if (!candidates.length) {
    return Promise.resolve('');
  }
  const tryFetch = index => {
    const target = candidates[index];
    if (!target) {
      return Promise.resolve('');
    }
    return fetch(target)
      .then(resp => (resp.ok ? resp.text() : ''))
      .catch(() => '')
      .then(result => {
        if (result || index >= candidates.length - 1) {
          return result;
        }
        return tryFetch(index + 1);
      });
  };
  return tryFetch(0);
};

function ensureLandingFont() {
  if (document.getElementById('landing-font')) {
    return;
  }
  const fontLink = document.createElement('link');
  fontLink.id = 'landing-font';
  fontLink.rel = 'stylesheet';
  fontLink.href = LANDING_FONT_URL;
  document.head.appendChild(fontLink);
}

const buildLandingStyleSources = (options = {}) => {
  const namespace = resolvePageNamespace();
  const normalized = (namespace || '').trim().toLowerCase();
  const hasCustomNamespace = normalized && normalized !== 'default';
  const hasNamespacedAssets = hasCustomNamespace && LANDING_NAMESPACE_ASSET_FOLDERS.has(normalized);
  const files = options.includeUikit ? [UIKIT_FILENAME, ...LANDING_STYLE_FILENAMES] : LANDING_STYLE_FILENAMES;
  return files.map(file => {
    const defaultPath = withBase(`/css/${file}`);
    if (!hasNamespacedAssets) {
      return [defaultPath];
    }
    return [withBase(`/css/${encodeURIComponent(normalized)}/${file}`), defaultPath];
  });
};

function ensureScopedLandingStyles(styleId, scopeSelector, options = {}) {
  const namespace = resolvePageNamespace() || 'default';
  const existingStyle = document.getElementById(styleId);
  if (existingStyle && existingStyle.dataset.namespace === namespace) {
    return Promise.resolve();
  }
  if (existingStyle && existingStyle.dataset.namespace !== namespace) {
    existingStyle.remove();
  }
  const cacheKey = `${styleId}-${namespace}`;
  if (landingStylesPromises[cacheKey]) {
    return landingStylesPromises[cacheKey];
  }
  const requests = buildLandingStyleSources(options).map(paths => fetchCssWithFallback(paths));
  landingStylesPromises[cacheKey] = Promise.all(requests).then(chunks => {
    const scoped = chunks
      .map(chunk => scopeLandingCss(chunk, scopeSelector))
      .filter(Boolean)
      .join('\n');
    if (scoped) {
      const styleEl = document.createElement('style');
      styleEl.id = styleId;
      styleEl.dataset.namespace = namespace;
      styleEl.textContent = scoped;
      document.head.appendChild(styleEl);
    }
    ensureLandingFont();
  }).catch(err => {
    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('Failed to load landing styles', err);
    }
  });
  return landingStylesPromises[cacheKey];
}

function ensureLandingEditorStyles() {
  return ensureScopedLandingStyles('landing-editor-styles', '.landing-editor', { includeUikit: true });
}

function scopeLandingCss(css, scopeSelector) {
  if (!css) {
    return '';
  }
  let parser;
  try {
    parser = document.createElement('style');
    parser.textContent = css;
    document.head.appendChild(parser);
    const sheet = parser.sheet;
    if (!sheet || !sheet.cssRules) {
      parser.remove();
      return css;
    }

    const styleRuleType = window.CSSRule?.STYLE_RULE ?? 1;
    const mediaRuleType = window.CSSRule?.MEDIA_RULE ?? 4;
    const supportsRuleType = window.CSSRule?.SUPPORTS_RULE ?? 12;
    const keyframesRuleType = window.CSSRule?.KEYFRAMES_RULE ?? 7;

    const toArray = list => {
      try {
        return Array.from(list);
      } catch (err) {
        const arr = [];
        for (let i = 0; i < list.length; i += 1) {
          arr.push(list[i]);
        }
        return arr;
      }
    };

    const processRuleList = ruleList => {
      const rules = [];
      toArray(ruleList).forEach(rule => {
        if (rule.type === styleRuleType) {
          const selectors = prefixSelectorList(rule.selectorText, scopeSelector);
          if (selectors) {
            rules.push(`${selectors} { ${rule.style.cssText} }`);
          }
        } else if (rule.type === mediaRuleType) {
          const inner = processRuleList(rule.cssRules);
          if (inner.length) {
            rules.push(`@media ${rule.conditionText} { ${inner.join(' ')} }`);
          }
        } else if (rule.type === supportsRuleType) {
          const innerSupports = processRuleList(rule.cssRules);
          if (innerSupports.length) {
            rules.push(`@supports ${rule.conditionText} { ${innerSupports.join(' ')} }`);
          }
        } else if (rule.type === keyframesRuleType) {
          rules.push(rule.cssText);
        } else {
          rules.push(rule.cssText);
        }
      });
      return rules;
    };

    const scoped = processRuleList(sheet.cssRules).join('\n');
    parser.remove();
    return scoped;
  } catch (error) {
    if (parser && parser.parentNode) {
      parser.parentNode.removeChild(parser);
    }
    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('Failed to scope landing CSS', error);
    }
    return css;
  }
}

function prefixSelectorList(selectorText, scopeSelector) {
  if (!selectorText) {
    return '';
  }
  return selectorText
    .split(',')
    .map(sel => prefixSingleSelector(sel, scopeSelector))
    .filter(Boolean)
    .join(', ');
}

function prefixSingleSelector(selector, scopeSelector) {
  let trimmed = selector.trim();
  if (!trimmed) {
    return '';
  }
  if (trimmed.startsWith(scopeSelector)) {
    return trimmed;
  }
  if (trimmed.startsWith(':root')) {
    const remainder = trimmed.slice(':root'.length);
    return combineSelector(scopeSelector, remainder);
  }
  if (/^body\b/i.test(trimmed)) {
    trimmed = trimmed.replace(/^body\b/i, '');
  }
  if (/^html\b/i.test(trimmed)) {
    trimmed = trimmed.replace(/^html\b/i, '');
  }
  if (/^\.qr-landing\b/.test(trimmed)) {
    trimmed = trimmed.replace(/^\.qr-landing\b/, '');
  }
  return combineSelector(scopeSelector, trimmed);
}

function combineSelector(scopeSelector, remainder) {
  const hadLeadingSpace = /^\s/.test(remainder);
  const clean = remainder.trim();
  if (!clean) {
    return scopeSelector;
  }
  if (/^[>+~]/.test(clean)) {
    return `${scopeSelector} ${clean}`;
  }
  if (hadLeadingSpace) {
    return `${scopeSelector} ${clean}`;
  }
  if (/^[.:#\[]/.test(clean)) {
    return `${scopeSelector}${clean}`;
  }
  return `${scopeSelector} ${clean}`;
}

function applyLandingStyling(element, theme = getCurrentTheme()) {
  if (!element) {
    return;
  }
  ensureLandingEditorStyles();
  const activeTheme = normalizeTheme(theme);
  element.classList.add('landing-editor');
  element.setAttribute('data-theme', activeTheme);
  element.classList.toggle('high-contrast', activeTheme === THEME_HIGH_CONTRAST);
  if (activeTheme === THEME_DARK) {
    element.classList.add('dark-mode');
  } else {
    element.classList.remove('dark-mode');
  }
}

const PREVIEW_LANDING_CLASS = 'landing-preview';

function ensureLandingPreviewStyles() {
  return ensureScopedLandingStyles('landing-preview-styles', `.${PREVIEW_LANDING_CLASS}`);
}

function applyLandingPreviewStyling(element, theme = getCurrentTheme()) {
  if (!element) {
    return;
  }
  ensureLandingPreviewStyles();
  const activeTheme = normalizeTheme(theme);
  element.classList.add(PREVIEW_LANDING_CLASS);
  element.setAttribute('data-theme', activeTheme);
  element.classList.toggle('high-contrast', activeTheme === THEME_HIGH_CONTRAST);
  if (activeTheme === THEME_DARK) {
    element.classList.add('dark-mode');
  } else {
    element.classList.remove('dark-mode');
  }
}

function resetLandingPreviewStyling(element) {
  if (!element) {
    return;
  }
  element.classList.remove(PREVIEW_LANDING_CLASS, 'dark-mode', 'high-contrast');
  element.removeAttribute('data-theme');
}

const getEditorElement = form => (form ? form.querySelector('.page-editor') : null);

const resetLandingStyling = element => {
  if (!element) {
    return;
  }
  element.classList.remove('landing-editor', 'dark-mode', 'high-contrast');
  element.removeAttribute('data-theme');
};

const editorInstances = new Map();
const bubbleMenus = new WeakMap();

const EDITOR_INSTANCE_KEY = '__pageEditorInstance';

const getEditorInstance = form => {
  if (!form) {
    return null;
  }

  const existing = editorInstances.get(form);
  if (existing) {
    return existing;
  }

  const editorEl = getEditorElement(form);
  return editorEl?.[EDITOR_INSTANCE_KEY] || null;
};

const setEditorInstance = (form, editor) => {
  if (form && editor) {
    editorInstances.set(form, editor);
    const editorEl = getEditorElement(form);
    if (editorEl) {
      editorEl[EDITOR_INSTANCE_KEY] = editor;
    }
  }
};

const removeEditorInstance = form => {
  if (form) {
    editorInstances.delete(form);
    const editorEl = getEditorElement(form);
    if (editorEl) {
      delete editorEl[EDITOR_INSTANCE_KEY];
    }
  }
};

const createToolbarButton = (label, title, onClick) => {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'uk-button uk-button-default';
  button.textContent = label;
  button.title = title || label;
  button.addEventListener('click', event => {
    event.preventDefault();
    onClick?.();
  });
  return button;
};

const createCommandButton = (editor, options) => {
  const { label, title, action, isActive } = options;
  const button = createToolbarButton(label, title, () => {
    action?.();
  });

  const updateState = () => {
    if (isActive && isActive()) {
      button.classList.add('uk-button-primary');
    } else {
      button.classList.remove('uk-button-primary');
    }
  };

  const unregister = () => {
    button.remove();
  };

  return { button, updateState, unregister };
};

const buildFormattingButtons = editor => {
  const container = document.createElement('div');
  container.className = 'uk-flex uk-flex-middle uk-margin-small-right';
  const updaters = [];

  const addGroup = buttons => {
    const group = document.createElement('div');
    group.className = 'uk-button-group uk-margin-small-right';
    buttons.forEach(config => {
      const { button, updateState } = createCommandButton(editor, config);
      group.append(button);
      updaters.push(updateState);
    });
    container.append(group);
  };

  const focusChain = () => editor.chain().focus();
  addGroup([
    {
      label: 'B',
      title: 'Fett (Strg+B)',
      action: () => focusChain().toggleBold().run(),
      isActive: () => editor.isActive('bold')
    },
    {
      label: 'I',
      title: 'Kursiv (Strg+I)',
      action: () => focusChain().toggleItalic().run(),
      isActive: () => editor.isActive('italic')
    }
  ]);

  addGroup(
    [1, 2, 3].map(level => ({
      label: `H${level}`,
      title: `Überschrift ${level}`,
      action: () => focusChain().toggleHeading({ level }).run(),
      isActive: () => editor.isActive('heading', { level })
    }))
  );

  addGroup([
    {
      label: '• Liste',
      title: 'Aufzählungsliste',
      action: () => focusChain().toggleBulletList().run(),
      isActive: () => editor.isActive('bulletList')
    },
    {
      label: '1. Liste',
      title: 'Nummerierte Liste',
      action: () => focusChain().toggleOrderedList().run(),
      isActive: () => editor.isActive('orderedList')
    }
  ]);

  addGroup([
    {
      label: 'Link',
      title: 'Link einfügen',
      action: () => {
        const href = window.prompt('Link-URL eingeben', 'https://');
        const value = typeof href === 'string' ? href.trim() : '';
        if (!value) {
          return;
        }
        focusChain().extendMarkRange('link').setLink({ href: value }).run();
      },
      isActive: () => editor.isActive('link')
    },
    {
      label: 'Link löschen',
      title: 'Link entfernen',
      action: () => focusChain().unsetLink().run(),
      isActive: null
    }
  ]);

  addGroup([
    {
      label: '↺',
      title: 'Rückgängig (Strg+Z)',
      action: () => editor.commands.undo(),
      isActive: null
    },
    {
      label: '↻',
      title: 'Wiederholen (Strg+Y)',
      action: () => editor.commands.redo(),
      isActive: null
    }
  ]);

  const refreshState = () => {
    updaters.forEach(update => update());
  };

  editor.on('selectionUpdate', refreshState);
  editor.on('update', refreshState);
  editor.on('focus', refreshState);

  refreshState();

  return { element: container, refreshState };
};

const buildTemplateButtons = editor => {
  const group = document.createElement('div');
  group.className = 'uk-button-group uk-margin-small-right';

  const heroBtn = createToolbarButton('UIkit Hero', 'UIkit Hero-Template einfügen (Mod-Alt-H)', () => {
    editor.commands.insertHeroTemplate();
  });
  const cardBtn = createToolbarButton('UIkit Card', 'UIkit Card-Template einfügen (Mod-Alt-C)', () => {
    editor.commands.insertCardTemplate();
  });

  group.append(heroBtn, cardBtn);
  return group;
};

const buildQuizLinkDropdown = editor => {
  const wrapper = document.createElement('div');
  wrapper.className = 'uk-inline uk-margin-small-right';

  const trigger = createToolbarButton('Quiz-Link', 'Quiz-Link einfügen', null);
  const dropdown = document.createElement('div');
  dropdown.className = 'uk-dropdown';
  dropdown.setAttribute('uk-dropdown', 'mode: click');
  const list = document.createElement('ul');
  list.className = 'uk-nav uk-dropdown-nav';
  dropdown.append(list);
  wrapper.append(trigger, dropdown);

  const hideDropdown = () => {
    try {
      const instance = window.UIkit?.dropdown?.(dropdown);
      instance?.hide?.();
    } catch (error) {
      if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('Failed to hide quiz link dropdown', error);
      }
    }
  };

  const renderLinks = links => {
    list.innerHTML = '';
    if (!Array.isArray(links) || links.length === 0) {
      const emptyItem = document.createElement('li');
      const emptyText = document.createElement('div');
      emptyText.className = 'uk-text-muted uk-padding-small';
      emptyText.textContent = 'Keine Quiz-Links gefunden';
      emptyItem.append(emptyText);
      list.append(emptyItem);
      return;
    }

    links.forEach(link => {
      const li = document.createElement('li');
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'uk-button uk-button-text uk-width-1-1 uk-text-left';
      option.textContent = link.name || link.slug;
      option.title = link.description || link.name || link.slug;
      option.addEventListener('click', event => {
        event.preventDefault();
        editor.commands.insertQuizLink({
          slug: link.slug,
          label: link.name || link.slug,
          description: link.description || link.name || link.slug
        });
        hideDropdown();
      });
      li.append(option);
      list.append(li);
    });
  };

  const ensureLinksLoaded = () => {
    if (wrapper.dataset.linksLoaded === '1') {
      return;
    }
    wrapper.dataset.linksLoaded = '1';
    fetchQuizLinks()
      .then(renderLinks)
      .catch(() => renderLinks([]));
  };

  trigger.addEventListener('click', () => {
    ensureLinksLoaded();
  });

  return wrapper;
};

const buildBubbleMenu = editor => {
  const bubble = document.createElement('div');
  bubble.className = 'page-editor-bubble uk-card uk-card-default uk-card-body';
  bubble.style.position = 'absolute';
  bubble.style.display = 'none';
  bubble.style.zIndex = '1000';

  const { element: formattingButtons, refreshState } = buildFormattingButtons(editor);
  bubble.append(formattingButtons);

  const hideMenu = () => {
    bubble.style.display = 'none';
  };

  const placeMenu = (left, top) => {
    bubble.style.visibility = 'hidden';
    bubble.style.display = 'block';
    bubble.style.left = `${Math.max(0, left)}px`;
    bubble.style.top = `${Math.max(0, top)}px`;
    const offsetX = bubble.offsetWidth ? bubble.offsetWidth / 2 : 0;
    bubble.style.left = `${Math.max(0, left - offsetX)}px`;
    bubble.style.visibility = 'visible';
    refreshState();
  };

  const showAtRect = rect => {
    if (!rect) {
      hideMenu();
      return;
    }
    const left = rect.left + rect.width / 2 + window.scrollX;
    const top = rect.bottom + window.scrollY + 8;
    placeMenu(left, top);
  };

  const selectionHandler = () => {
    const selection = window.getSelection();
    if (!selection || selection.isCollapsed || selection.rangeCount === 0 || !editor.isFocused) {
      hideMenu();
      return;
    }
    const rect = selection.getRangeAt(0).getBoundingClientRect();
    showAtRect(rect);
  };

  const blurHandler = () => hideMenu();
  editor.on('selectionUpdate', selectionHandler);
  editor.on('focus', selectionHandler);
  editor.on('blur', blurHandler);

  const editorEl = editor.view?.dom;
  const contextHandler = event => {
    event.preventDefault();
    refreshState();
    placeMenu(event.pageX, event.pageY);
  };
  if (editorEl) {
    editorEl.addEventListener('contextmenu', contextHandler);
  }

  const clickAwayHandler = event => {
    if (!bubble.contains(event.target) && (!editorEl || !editorEl.contains(event.target))) {
      hideMenu();
    }
  };
  document.addEventListener('click', clickAwayHandler);

  document.body.append(bubble);

  const destroy = () => {
    hideMenu();
    bubble.remove();
    if (editorEl) {
      editorEl.removeEventListener('contextmenu', contextHandler);
    }
    document.removeEventListener('click', clickAwayHandler);
  };

  return { element: bubble, destroy, refreshState, hideMenu };
};

const attachBubbleMenu = (form, editor) => {
  if (!form || !editor || bubbleMenus.has(editor)) {
    return;
  }
  const bubble = buildBubbleMenu(editor);
  bubbleMenus.set(editor, bubble);
};

const buildEditorToolbar = editor => {
  const toolbar = document.createElement('div');
  toolbar.className = 'page-editor-toolbar uk-margin-small-bottom uk-flex uk-flex-wrap uk-flex-middle';

  const { element: formattingButtons } = buildFormattingButtons(editor);
  toolbar.append(formattingButtons);

  const quizDropdown = buildQuizLinkDropdown(editor);
  toolbar.append(quizDropdown);

  const templates = buildTemplateButtons(editor);
  toolbar.append(templates);

  return toolbar;
};

const attachEditorToolbar = (form, editor) => {
  if (!form || !editor) {
    return;
  }

  if (USE_BLOCK_EDITOR) {
    return;
  }

  const editorEl = getEditorElement(form);
  if (!editorEl || !editorEl.parentNode) {
    return;
  }

  if (form.dataset.toolbarReady !== '1') {
    const toolbar = buildEditorToolbar(editor);
    editorEl.parentNode.insertBefore(toolbar, editorEl);
    form.dataset.toolbarReady = '1';
  }

  attachBubbleMenu(form, editor);
};

const buildEditorExtensions = () => [
  StarterKit.configure({}),
  Link,
  QuizLink,
  UikitTemplates
];

const ensurePageEditorInitialized = form => {
  const editorEl = getEditorElement(form);
  if (!editorEl || editorEl.dataset.editorInitializing === '1') {
    return null;
  }

  if (USE_BLOCK_EDITOR) {
    let existing = getEditorInstance(form);
    if (existing) {
      attachBlockPreview(form, existing);
      return existing;
    }
    const initialContent = editorEl.dataset.content || editorEl.textContent || '{}';
    editorEl.dataset.content = initialContent;
    try {
      const blockEditor = new BlockContentEditor(editorEl, initialContent, { pageId: form?.dataset.pageId });
      setEditorInstance(form, blockEditor);
      attachBlockPreview(form, blockEditor);
      return blockEditor;
    } catch (error) {
      notify(error.message || 'Ungültige Blöcke', 'danger');
      return null;
    }
  }

  let existing = getEditorInstance(form);
  if (existing) {
    attachEditorToolbar(form, existing);
    return existing;
  }

  const initial = editorEl.dataset.content || '';
  const sanitized = sanitize(initial);
  editorEl.dataset.content = sanitized;
  editorEl.innerHTML = '';

  if (form?.dataset.landing === 'true') {
    applyLandingStyling(editorEl);
  } else {
    resetLandingStyling(editorEl);
  }

  editorEl.dataset.editorInitializing = '1';
  let editor;
  try {
    editor = new Editor({
      element: editorEl,
      content: sanitized,
      extensions: buildEditorExtensions(),
      editorProps: {
        attributes: {
          class: 'tiptap-editor',
          spellcheck: 'true'
        }
      },
      onUpdate: ({ editor: instance }) => {
        editorEl.dataset.content = sanitize(instance.getHTML());
      }
    });
  } catch (error) {
    delete editorEl.dataset.editorInitializing;
    throw error;
  }

  editorEl.dataset.editorInitialized = '1';
  delete editorEl.dataset.editorInitializing;
  setEditorInstance(form, editor);
  attachEditorToolbar(form, editor);
  return editor;
};

const teardownPageEditor = form => {
  const editor = getEditorInstance(form);
  const editorEl = getEditorElement(form);
  if (!editor || !editorEl) {
    return;
  }

  if (USE_BLOCK_EDITOR) {
    if (typeof editor.getContent === 'function') {
      const content = editor.getContent();
      editorEl.dataset.content = content;
      const slug = form?.dataset.slug;
      if (slug && window.pagesContent && typeof window.pagesContent === 'object') {
        window.pagesContent[slug] = content;
      }
    }
    if (typeof editor.destroy === 'function') {
      editor.destroy();
    }
    teardownBlockPreview(form);
    removeEditorInstance(form);
    return;
  }

  let html = editor.getHTML();
  try {
    html = editor.getHTML();
  } catch (error) {
    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('Failed to read page editor HTML before teardown', error);
    }
  }

  if (typeof html === 'string') {
    const sanitized = sanitize(html);
    editorEl.dataset.content = sanitized;
    const slug = form?.dataset.slug;
    if (slug && window.pagesContent && typeof window.pagesContent === 'object') {
      window.pagesContent[slug] = sanitized;
    }
  }

  try {
    editor.destroy();
  } catch (error) {
    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('Failed to destroy page editor instance', error);
    }
  }

  const bubble = bubbleMenus.get(editor);
  if (bubble && typeof bubble.destroy === 'function') {
    bubble.destroy();
  }
  bubbleMenus.delete(editor);

  removeEditorInstance(form);
  editorEl.innerHTML = '';
  resetLandingStyling(editorEl);
  delete editorEl.dataset.editorInitialized;
};

let pageSelectionState = null;
let currentStartpagePageId = null;
let startpageMap = {};
let selectedStartpageDomain = '';

const getStartpageToggle = () => document.querySelector('[data-startpage-toggle]');
const getDomainSelect = () => document.querySelector('[data-startpage-domain]');
const isStartpageDisabled = () => getStartpageToggle()?.dataset.startpageDisabled === '1';

const resolveSelectedPageId = () => {
  const select = document.getElementById('pageContentSelect');
  const option = select?.selectedOptions?.[0];
  const raw = option?.dataset.pageId || '';
  return raw ? Number(raw) : null;
};

const normalizeStartpageMap = raw => {
  if (typeof raw !== 'string' || raw.trim() === '') {
    return {};
  }
  try {
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') {
      return {};
    }
    return parsed;
  } catch (error) {
    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('Failed to parse startpage map', error);
    }
    return {};
  }
};

const resolveStartpageIdForDomain = domain => {
  const key = typeof domain === 'string' ? domain : '';
  const value = startpageMap[key];
  if (value === null || value === undefined || value === '') {
    return null;
  }
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
};

const refreshStartpageOptionState = startpageId => {
  const select = document.getElementById('pageContentSelect');
  if (!select) {
    return;
  }
  Array.from(select.options).forEach(option => {
    const optionId = option.dataset.pageId ? Number(option.dataset.pageId) : null;
    option.dataset.startpage = optionId && startpageId === optionId ? '1' : '0';
  });
};

const syncStartpageToggle = () => {
  const toggle = getStartpageToggle();
  if (!toggle) {
    return;
  }

  if (isStartpageDisabled()) {
    toggle.checked = false;
    toggle.disabled = true;
    return;
  }
  const selectedId = resolveSelectedPageId();
  if (!selectedId) {
    toggle.checked = false;
    toggle.disabled = true;
    return;
  }

  toggle.disabled = false;
  toggle.checked = currentStartpagePageId === selectedId;
};

const loadStartpageState = () => {
  const manager = document.querySelector('[data-page-namespace-manager]');
  const rawStartpageId = manager?.dataset.startpagePageId || '';
  const rawMap = manager?.dataset.startpageMap || '';
  startpageMap = normalizeStartpageMap(rawMap);
  selectedStartpageDomain = manager?.dataset.selectedDomain || '';

  if (startpageMap === null || typeof startpageMap !== 'object') {
    startpageMap = {};
  }

  if (!Object.keys(startpageMap).length && rawStartpageId) {
    const numeric = Number(rawStartpageId);
    startpageMap[''] = Number.isFinite(numeric) ? numeric : null;
  }

  currentStartpagePageId = resolveStartpageIdForDomain(selectedStartpageDomain);

  if (currentStartpagePageId === null && rawStartpageId) {
    const numeric = Number(rawStartpageId);
    currentStartpagePageId = Number.isFinite(numeric) ? numeric : null;
  }

  refreshStartpageOptionState(currentStartpagePageId);
  syncStartpageToggle();
};

const bindStartpageDomainSelect = () => {
  const select = getDomainSelect();
  if (!select || select.dataset.bound === '1') {
    return;
  }

  select.addEventListener('change', () => {
    selectedStartpageDomain = select.value || '';
    currentStartpagePageId = resolveStartpageIdForDomain(selectedStartpageDomain);
    refreshStartpageOptionState(currentStartpagePageId);
    syncStartpageToggle();
  });

  select.dataset.bound = '1';
};

const updateStartpage = async (pageId, enabled) => {
  const domain = selectedStartpageDomain || '';
  const response = await apiFetch(withNamespace(`/admin/pages/${encodeURIComponent(pageId)}/startpage`), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-Token': window.csrfToken || ''
    },
    body: JSON.stringify({ is_startpage: enabled, domain })
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const message = payload?.error || 'Startseite konnte nicht gespeichert werden.';
    throw new Error(message);
  }

  const updatedId = payload?.startpagePageId ? Number(payload.startpagePageId) : null;
  currentStartpagePageId = Number.isFinite(updatedId) ? updatedId : null;
  startpageMap[domain] = currentStartpagePageId;
  refreshStartpageOptionState(currentStartpagePageId);
  syncStartpageToggle();
};

const bindStartpageToggle = () => {
  const toggle = getStartpageToggle();
  if (!toggle || toggle.dataset.bound === '1' || isStartpageDisabled()) {
    return;
  }

  toggle.addEventListener('change', () => {
    const selectedId = resolveSelectedPageId();
    if (!selectedId) {
      toggle.checked = false;
      return;
    }

    const desired = !!toggle.checked;
    toggle.disabled = true;
    updateStartpage(selectedId, desired)
      .then(() => {
        const message = desired
          ? 'Startseite für dieses Projekt gesetzt.'
          : 'Startseite wurde entfernt.';
        notify(message, 'success');
      })
      .catch(error => {
        toggle.checked = !desired;
        notify(error instanceof Error ? error.message : 'Startseite konnte nicht gespeichert werden.', 'danger');
      })
      .finally(() => {
        toggle.disabled = false;
      });
  });

  toggle.dataset.bound = '1';
  syncStartpageToggle();
};
const formatPageLabel = page => {
  const title = (page?.title || '').trim();
  if (title) {
    return title;
  }
  const slug = (page?.slug || '').trim();
  if (!slug) {
    return 'Neue Seite';
  }
  return slug
    .split('-')
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
};

const getExcludedLandingSlugs = () => {
  const select = document.getElementById('pageContentSelect');
  if (!select) {
    return [];
  }
  return (select.dataset.excludedLanding || '')
    .split(',')
    .map(value => value.trim())
    .filter(Boolean);
};

const getTranslation = (name, fallback) => {
  const value = window[name];
  return typeof value === 'string' && value ? value : fallback;
};

const removePagesEmptyMessage = (root = document.getElementById('pageFormsContainer')) => {
  root?.querySelector('.pages-empty-alert')?.remove();
};

const showPagesEmptyMessage = () => {
  const container = document.getElementById('pageFormsContainer');
  if (!container) {
    return;
  }
  removePagesEmptyMessage(container);
  const alert = document.createElement('div');
  alert.className = 'uk-alert uk-alert-warning pages-empty-alert';
  alert.textContent = getTranslation('transMarketingPagesEmpty', 'Keine Marketing-Seiten gefunden.');
  container.append(alert);
};

const buildPageForm = page => {
  const slug = (page?.slug || '').trim();
  const content = page?.content || '';
  const excluded = getExcludedLandingSlugs();
  const form = document.createElement('form');
  form.className = 'page-form uk-hidden';
  form.dataset.slug = slug;
  form.dataset.landing = excluded.includes(slug) ? 'false' : 'true';
  if (page?.id) {
    form.dataset.pageId = String(page.id);
  }

  const hiddenInput = document.createElement('input');
  hiddenInput.type = 'hidden';
  hiddenInput.name = 'content';
  hiddenInput.id = `page_${slug}`;
  hiddenInput.value = content;

  const editor = document.createElement('div');
  editor.className = 'page-editor';
  editor.dataset.content = content;

  const actions = document.createElement('div');
  actions.className = 'uk-margin-top';

  const saveBtn = document.createElement('button');
  saveBtn.className = 'uk-button uk-button-primary save-page-btn';
  saveBtn.type = 'button';
  saveBtn.textContent = 'Speichern';

  const previewLink = document.createElement('button');
  previewLink.className = 'uk-button uk-button-default uk-margin-small-left preview-link';
  previewLink.type = 'button';
  previewLink.textContent = 'Vorschau';

  const deleteBtn = document.createElement('button');
  deleteBtn.className = 'uk-button uk-button-danger uk-margin-small-left delete-page-btn';
  deleteBtn.type = 'button';
  deleteBtn.textContent = getTranslation('transDelete', 'Löschen');

  actions.append(saveBtn, previewLink, deleteBtn);
  form.append(hiddenInput, editor, actions);

  return form;
};

const setupPageForm = form => {
  if (!form || form.dataset.pageReady === '1') {
    return;
  }

  const slug = (form.dataset.slug || '').trim();
  if (!slug) {
    return;
  }

  const input = form.querySelector('input[name="content"]');
  const editorEl = form.querySelector('.page-editor');
  if (!input || !editorEl) {
    return;
  }

  if (form.classList.contains('uk-hidden')) {
    editorEl.innerHTML = '';
    resetLandingStyling(editorEl);
  } else {
    ensurePageEditorInitialized(form);
  }

  const saveBtn = form.querySelector('.save-page-btn');
  if (saveBtn && !saveBtn.dataset.bound) {
    saveBtn.addEventListener('click', event => {
      event.preventDefault();
      const activeEditor = ensurePageEditorInitialized(form);
      let content;
      try {
        content = USE_BLOCK_EDITOR
          ? (activeEditor && typeof activeEditor.getContent === 'function'
            ? activeEditor.getContent()
            : editorEl.dataset.content || '{}')
          : sanitize(activeEditor ? activeEditor.getHTML() : editorEl.dataset.content || '');
      } catch (error) {
        notify(error.message || 'Ungültige Blöcke', 'danger');
        return;
      }

      input.value = content;
      editorEl.dataset.content = content;
      if (window.pagesContent && typeof window.pagesContent === 'object') {
        window.pagesContent[slug] = content;
      }

      apiFetch(withNamespace(`/admin/pages/${encodeURIComponent(slug)}`), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ content })
      })
        .then(response => {
          if (!response.ok) {
            throw new Error(response.statusText || 'save-failed');
          }
          notify('Seite gespeichert', 'success');
        })
        .catch(() => notify('Fehler beim Speichern', 'danger'));
    });
    saveBtn.dataset.bound = '1';
  }

  const previewBtn = form.querySelector('.preview-link');
  if (previewBtn && !previewBtn.dataset.bound) {
    previewBtn.addEventListener('click', event => {
      event.preventDefault();
      showPreview(form);
    });
    previewBtn.dataset.bound = '1';
  }

  const deleteBtn = form.querySelector('.delete-page-btn');
  if (deleteBtn && !deleteBtn.dataset.bound) {
    deleteBtn.addEventListener('click', event => {
      event.preventDefault();
      const confirmMessage = getTranslation('transDeletePageConfirm', 'Diese Seite wirklich löschen?');
      if (typeof window.confirm === 'function' && !window.confirm(confirmMessage)) {
        return;
      }

      apiFetch(withNamespace(`/admin/pages/${encodeURIComponent(slug)}`), { method: 'DELETE' })
        .then(response => {
          if (response.status === 204) {
            removePageFromInterface(slug);
            notify(getTranslation('transPageDeleted', 'Seite gelöscht'), 'success');
            return;
          }
          if (response.status === 404) {
            removePageFromInterface(slug);
            throw new Error('not-found');
          }
          throw new Error('delete-failed');
        })
        .catch(error => {
          const status = error instanceof Error && error.message === 'not-found' ? 'warning' : 'danger';
          notify(getTranslation('transPageDeleteError', 'Seite konnte nicht gelöscht werden.'), status);
        });
    });
    deleteBtn.dataset.bound = '1';
  }

  form.dataset.pageReady = '1';
};

const addPageToInterface = page => {
  if (!page) {
    return;
  }

  const select = document.getElementById('pageContentSelect');
  const container = document.getElementById('pageFormsContainer');
  const slug = (page.slug || '').trim();
  if (!select || !container || !slug) {
    return;
  }

  removePagesEmptyMessage(container);

  const existingOption = Array.from(select.options).find(option => option.value === slug);
  if (!existingOption) {
    const option = document.createElement('option');
    option.value = slug;
    option.textContent = formatPageLabel(page);
    if (page?.id) {
      option.dataset.pageId = String(page.id);
    }
    select.append(option);
  } else if (page?.id && !existingOption.dataset.pageId) {
    existingOption.dataset.pageId = String(page.id);
  }

  let form = container.querySelector(`.page-form[data-slug="${slug}"]`);
  if (!form) {
    form = buildPageForm(page);
    container.append(form);
  } else if (page?.id && !form.dataset.pageId) {
    form.dataset.pageId = String(page.id);
  }
  setupPageForm(form);

  if (window.pagesContent && typeof window.pagesContent === 'object') {
    window.pagesContent[slug] = page.content || '';
  }

  if (pageSelectionState) {
    pageSelectionState.refresh();
    select.value = slug;
    pageSelectionState.toggleForms(slug);
  }

  refreshStartpageOptionState(currentStartpagePageId);
  syncStartpageToggle();

  document.dispatchEvent(new CustomEvent('marketing-page:created', { detail: page }));
};

const updatePageOptionLabel = page => {
  if (!page) {
    return;
  }

  const select = document.getElementById('pageContentSelect');
  if (!select) {
    return;
  }
  const slug = (page.slug || '').trim();
  if (!slug) {
    return;
  }
  const option = Array.from(select.options).find(item => item.value === slug);
  if (!option) {
    return;
  }
  option.textContent = formatPageLabel(page);
};

const updatePageContentInInterface = (slug, html) => {
  const normalized = (slug || '').trim();
  if (!normalized) {
    return;
  }
  const form = document.querySelector(`.page-form[data-slug="${normalized}"]`);
  if (!form) {
    return;
  }

  if (USE_BLOCK_EDITOR) {
    const serialized = typeof html === 'string' ? html : JSON.stringify(html || {});
    const input = form.querySelector('input[name="content"]');
    const editorEl = form.querySelector('.page-editor');
    const editor = getEditorInstance(form);
    if (input) {
      input.value = serialized;
    }
    if (editor && typeof editor.setContent === 'function') {
      editor.setContent(serialized);
    } else if (editorEl) {
      editorEl.dataset.content = serialized;
    }
    if (window.pagesContent && typeof window.pagesContent === 'object') {
      window.pagesContent[normalized] = serialized;
    }
    return;
  }

  const sanitized = sanitize(typeof html === 'string' ? html : '');
  const input = form.querySelector('input[name="content"]');
  const editorEl = form.querySelector('.page-editor');
  const editor = getEditorInstance(form);
  if (input) {
    input.value = sanitized;
  }
  if (editorEl) {
    editorEl.dataset.content = sanitized;
    if (editor) {
      try {
        editor.commands.setContent(sanitized, false);
      } catch (error) {
        if (window.console && typeof window.console.warn === 'function') {
          window.console.warn('Failed to update page editor HTML', error);
        }
      }
    } else {
      editorEl.innerHTML = '';
    }
  }

  if (window.pagesContent && typeof window.pagesContent === 'object') {
    window.pagesContent[normalized] = sanitized;
  }
};

const removePageFromInterface = slug => {
  const select = document.getElementById('pageContentSelect');
  const container = document.getElementById('pageFormsContainer');
  const normalized = (slug || '').trim();
  if (!select || !container || !normalized) {
    return;
  }

  const targetOption = Array.from(select.options).find(option => option.value === normalized);
  const removedPageId = targetOption?.dataset.pageId ? Number(targetOption.dataset.pageId) : null;
  const optionIndex = Array.from(select.options).findIndex(option => option.value === normalized);
  if (optionIndex >= 0) {
    select.remove(optionIndex);
  }

  const form = container.querySelector(`.page-form[data-slug="${normalized}"]`);
  if (form) {
    teardownPageEditor(form);
    form.remove();
  }

  if (window.pagesContent && typeof window.pagesContent === 'object') {
    delete window.pagesContent[normalized];
  }

  removePagesEmptyMessage(container);
  pageSelectionState?.refresh();

  const remainingForms = Array.from(container.querySelectorAll('.page-form'));
  const remainingOptions = select.options.length;

  if (!remainingForms.length || remainingOptions === 0) {
    select.value = '';
    showPagesEmptyMessage();
  } else {
    let nextValue = select.value;
    if (!remainingForms.some(formEl => formEl.dataset.slug === nextValue)) {
      nextValue = remainingForms[0]?.dataset.slug || select.options[0]?.value || '';
    }
    if (nextValue) {
      select.value = nextValue;
    }

    const toggleForms = pageSelectionState?.toggleForms;
    if (typeof toggleForms === 'function') {
      toggleForms(select.value);
    } else {
      remainingForms.forEach(formEl => {
        formEl.classList.toggle('uk-hidden', formEl.dataset.slug !== select.value);
      });
    }
  }

  if (removedPageId && currentStartpagePageId === removedPageId) {
    currentStartpagePageId = null;
  }

  refreshStartpageOptionState(currentStartpagePageId);
  syncStartpageToggle();

  document.dispatchEvent(new CustomEvent('marketing-page:deleted', { detail: { slug: normalized } }));
};

export function initPageEditors() {
  document.querySelectorAll('.page-form').forEach(setupPageForm);
}

export function initPageSelection() {
  const select = document.getElementById('pageContentSelect');
  if (!select) {
    pageSelectionState = null;
    return null;
  }

  const container = document.getElementById('pageFormsContainer');
  let forms = [];

  const updateEmptyState = () => {
    if (!container) {
      return;
    }
    const hasForms = forms.length > 0;
    const hasOptions = select.options.length > 0;
    if (!hasForms || !hasOptions) {
      showPagesEmptyMessage();
    } else {
      removePagesEmptyMessage(container);
    }
  };

  const refresh = () => {
    forms = container
      ? Array.from(container.querySelectorAll('.page-form'))
      : Array.from(document.querySelectorAll('.page-form'));
    updateEmptyState();
  };

  const toggleForms = slug => {
    if (!forms.length) {
      refresh();
    }
    let activeSlug = slug;
    if (!forms.some(form => form.dataset.slug === activeSlug)) {
      activeSlug = forms[0]?.dataset.slug || '';
    }
    forms.forEach(form => {
      const isActive = form.dataset.slug === activeSlug;
      form.classList.toggle('uk-hidden', !isActive);
      if (isActive) {
        ensurePageEditorInitialized(form);
      } else {
        teardownPageEditor(form);
      }
    });
    updateEmptyState();
  };

  refresh();

  let selected = select.dataset.selected || select.value;
  if (!selected && select.options.length > 0) {
    selected = select.options[0].value;
  }
  if (selected) {
    select.value = selected;
  }
  toggleForms(selected);
  syncStartpageToggle();

  select.addEventListener('change', () => {
    toggleForms(select.value);
    syncStartpageToggle();
  });

  const state = { select, refresh, toggleForms };
  pageSelectionState = state;
  return state;
}

const initPageCreation = () => {
  const form = document.getElementById('createPageForm');
  if (!form) {
    return;
  }

  const slugInput = form.querySelector('#newPageSlug');
  const titleInput = form.querySelector('#newPageTitle');
  const contentInput = form.querySelector('#newPageContent');
  const feedback = document.getElementById('createPageFeedback');
  const submitBtn = form.querySelector('button[type="submit"]');
  const modalEl = document.getElementById('createPageModal');
  const modal = modalEl && window.UIkit ? window.UIkit.modal(modalEl) : null;

  const setFeedback = (message, status = 'danger') => {
    if (!feedback) {
      return;
    }
    feedback.classList.remove('uk-alert-danger', 'uk-alert-success');
    if (!message) {
      feedback.hidden = true;
      feedback.textContent = '';
      return;
    }
    feedback.textContent = message;
    feedback.hidden = false;
    feedback.classList.add(status === 'success' ? 'uk-alert-success' : 'uk-alert-danger');
  };

  form.addEventListener('submit', async event => {
    event.preventDefault();
    setFeedback('');

    const slugValue = (slugInput?.value || '').trim().toLowerCase();
    const titleValue = (titleInput?.value || '').trim();
    const contentValue = contentInput ? contentInput.value : '';

    if (!slugValue || !titleValue) {
      setFeedback('Bitte fülle Slug und Titel aus.');
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
    }

    try {
      const response = await apiFetch(withNamespace('/admin/pages'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify({
          slug: slugValue,
          title: titleValue,
          content: contentValue
        })
      });

      const contentType = response.headers.get('content-type') || '';
      const responseText = await response.text();
      let payload = {};
      let parsedJson = false;
      if (contentType.includes('application/json')) {
        try {
          payload = responseText ? JSON.parse(responseText) : {};
          parsedJson = true;
        } catch (err) {
          const snippet = responseText.trim().slice(0, 200);
          if (window.console && typeof window.console.warn === 'function') {
            window.console.warn('Failed to parse JSON response for page creation.', {
              status: response.status,
              snippet
            });
          }
          throw new Error(`Serverantwort konnte nicht als JSON gelesen werden, Status ${response.status}.`);
        }
      } else {
        const snippet = responseText.trim().slice(0, 200);
        if (window.console && typeof window.console.warn === 'function') {
          window.console.warn('Unexpected content type for page creation response.', {
            status: response.status,
            contentType,
            snippet
          });
        }
        throw new Error(`Serverantwort ist kein JSON, Status ${response.status}.`);
      }

      if (!response.ok || !payload.page) {
        const errorMessage = parsedJson && payload.error
          ? payload.error
          : `Die Seite konnte nicht erstellt werden (Status ${response.status}).`;
        throw new Error(errorMessage);
      }

      addPageToInterface(payload.page);
      form.reset();
      if (modal) {
        modal.hide();
      }
      setFeedback('');
      notify('Seite erstellt', 'success');
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Die Seite konnte nicht erstellt werden.';
      setFeedback(message);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
      }
    }
  });
};

const initAiPageCreation = () => {
  const form = document.getElementById('aiPageForm');
  if (!form) {
    return;
  }

  const themeInput = form.querySelector('#aiPageTheme');
  const colorSchemeInput = form.querySelector('#aiPageColorScheme');
  const problemInput = form.querySelector('#aiPageProblem');
  const promptTemplateSelect = form.querySelector('#aiPagePromptTemplate');
  const slugInput = form.querySelector('#aiPageSlug');
  const titleInput = form.querySelector('#aiPageTitle');
  const primaryColorInput = form.querySelector('#aiPagePrimaryColor');
  const backgroundColorInput = form.querySelector('#aiPageBackgroundColor');
  const accentColorInput = form.querySelector('#aiPageAccentColor');
  const colorValueDisplays = {
    primary: form.querySelector('[data-ai-color-value="aiPagePrimaryColor"]'),
    background: form.querySelector('[data-ai-color-value="aiPageBackgroundColor"]'),
    accent: form.querySelector('[data-ai-color-value="aiPageAccentColor"]')
  };
  const createMenuItemCheckbox = form.querySelector('#aiPageCreateMenuItem');
  const menuLabelInput = form.querySelector('#aiPageMenuLabel');
  const menuLabelWrapper = form.querySelector('[data-ai-page-menu-label]');
  const feedback = form.querySelector('[data-ai-page-feedback]');
  const submitBtn = form.querySelector('button[type="submit"]');
  const modalEl = document.getElementById('aiPageModal');
  const modal = modalEl && window.UIkit ? window.UIkit.modal(modalEl) : null;
  const missingFieldsMessage = window.transAiPageMissingFields || 'Bitte fülle alle Felder aus.';
  const createErrorMessage = window.transAiPageCreateError || 'Die KI-Seite konnte nicht erstellt werden.';
  const aiUnavailableMessage = window.transAiPageUnavailable || createErrorMessage;
  const promptMissingMessage = window.transAiPagePromptMissing || createErrorMessage;
  const promptInvalidMessage = window.transAiPagePromptInvalid || createErrorMessage;
  const emptyResponseMessage = window.transAiPageEmptyResponse || 'Die KI-Antwort ist leer oder ungültig.';
  const invalidHtmlMessage = window.transAiPageInvalidHtml || createErrorMessage;
  const timeoutMessage = window.transAiPageTimeout || 'Server antwortet nicht rechtzeitig.';
  const pendingMessage = window.transAiPagePending || 'Die KI-Seite wird erstellt…';
  const createdMessage = window.transAiPageCreated || 'KI-Seite erstellt';
  const defaultColorTokens = {
    primary: primaryColorInput?.dataset.default || '#1e87f0',
    background: backgroundColorInput?.dataset.default || '#0f172a',
    accent: accentColorInput?.dataset.default || '#f59e0b'
  };
  const errorMessageMap = {
    missing_fields: missingFieldsMessage,
    invalid_payload: createErrorMessage,
    invalid_slug: createErrorMessage,
    page_not_found: createErrorMessage,
    prompt_missing: promptMissingMessage,
    prompt_template_invalid: promptInvalidMessage,
    ai_unavailable: aiUnavailableMessage,
    ai_empty: emptyResponseMessage,
    ai_timeout: timeoutMessage,
    ai_failed: createErrorMessage,
    ai_error: createErrorMessage,
    ai_invalid_html: invalidHtmlMessage
  };

  const delay = ms => new Promise(resolve => {
    window.setTimeout(resolve, ms);
  });

  const parseJsonResponse = async response => {
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const snippet = await response.text().catch(() => '');
      if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('Unexpected content type for AI page response.', {
          status: response.status,
          contentType,
          snippet: snippet.trim().slice(0, 200)
        });
      }
      throw new Error(timeoutMessage);
    }

    return response.json().catch(() => ({}));
  };

  const resolveErrorMessage = (payload, fallback) => {
    const code = typeof payload.error === 'string' ? payload.error : '';
    let errorMessage = errorMessageMap[code] || fallback;
    if (payload.message && ['ai_failed', 'ai_error', 'ai_timeout'].includes(code)) {
      errorMessage = payload.message;
    }
    return errorMessage;
  };

  const updateMenuLabelVisibility = () => {
    if (!menuLabelInput || !menuLabelWrapper) {
      return;
    }
    const enabled = Boolean(createMenuItemCheckbox?.checked);
    menuLabelWrapper.hidden = !enabled;
    menuLabelInput.disabled = !enabled;
    if (!enabled) {
      menuLabelInput.value = '';
    }
  };

  if (createMenuItemCheckbox) {
    createMenuItemCheckbox.addEventListener('change', updateMenuLabelVisibility);
  }
  updateMenuLabelVisibility();

  const normaliseColorValue = (value, fallback) => {
    const candidate = (value || '').trim();
    if (/^#[0-9a-fA-F]{6}$/.test(candidate)) {
      return candidate;
    }
    if (/^[0-9a-fA-F]{6}$/.test(candidate)) {
      return `#${candidate}`;
    }
    return fallback;
  };

  const getColorTokens = () => ({
    primary: normaliseColorValue(primaryColorInput?.value, defaultColorTokens.primary),
    background: normaliseColorValue(backgroundColorInput?.value, defaultColorTokens.background),
    accent: normaliseColorValue(accentColorInput?.value, defaultColorTokens.accent)
  });

  const getColorTokensText = () => Object.entries(getColorTokens())
    .filter(([, value]) => Boolean(value))
    .map(([key, value]) => `${key.charAt(0).toUpperCase()}${key.slice(1)}: ${value}`)
    .join('; ');

  const stripTokensFromValue = value => {
    const tokensText = getColorTokensText();
    if (!tokensText) {
      return (value || '').trim();
    }

    const escapedTokens = tokensText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = new RegExp(`\\s*${escapedTokens}$`);
    return (value || '').replace(pattern, '').trim();
  };

  const applyColorTokensToScheme = () => {
    if (!colorSchemeInput) {
      return;
    }
    const manualValue = stripTokensFromValue(colorSchemeInput.dataset.manual || colorSchemeInput.value);
    colorSchemeInput.dataset.manual = manualValue;

    const colorTokensText = getColorTokensText();
    const combined = [manualValue, colorTokensText].filter(Boolean).join(' ').trim();
    colorSchemeInput.value = combined;
  };

  if (colorSchemeInput) {
    colorSchemeInput.dataset.manual = stripTokensFromValue(colorSchemeInput.value);
    colorSchemeInput.addEventListener('input', () => {
      colorSchemeInput.dataset.manual = stripTokensFromValue(colorSchemeInput.value);
      applyColorTokensToScheme();
    });
  }

  const syncColorDisplay = (input, display, fallback, onChange) => {
    if (!input) {
      return;
    }
    const updateDisplay = () => {
      const value = normaliseColorValue(input.value, fallback);
      input.value = value;
      if (display) {
        display.textContent = value;
      }
      if (typeof onChange === 'function') {
        onChange();
      }
    };
    updateDisplay();
    input.addEventListener('input', updateDisplay);
    input.addEventListener('change', updateDisplay);
  };

  syncColorDisplay(primaryColorInput, colorValueDisplays.primary, defaultColorTokens.primary, applyColorTokensToScheme);
  syncColorDisplay(backgroundColorInput, colorValueDisplays.background, defaultColorTokens.background, applyColorTokensToScheme);
  syncColorDisplay(accentColorInput, colorValueDisplays.accent, defaultColorTokens.accent, applyColorTokensToScheme);
  applyColorTokensToScheme();

  const setFeedback = message => {
    if (!feedback) {
      return;
    }
    if (!message) {
      feedback.hidden = true;
      feedback.textContent = '';
      return;
    }
    feedback.textContent = message;
    feedback.hidden = false;
  };

  const ensurePageSelected = slug => {
    const select = document.getElementById('pageContentSelect');
    if (!select || !slug) {
      return;
    }
    select.value = slug;
    const state = pageSelectionState || initPageSelection();
    if (state && typeof state.toggleForms === 'function') {
      state.toggleForms(slug);
    }
  };

  const resolvePageId = slug => {
    const normalized = (slug || '').trim();
    if (!normalized) {
      return null;
    }
    const select = document.getElementById('pageContentSelect');
    const option = select
      ? Array.from(select.options).find(item => item.value === normalized)
      : null;
    const formEl = document.querySelector(`.page-form[data-slug="${normalized}"]`);
    const candidate = formEl?.dataset.pageId || option?.dataset.pageId || '';
    const pageId = Number.parseInt(candidate, 10);
    return Number.isFinite(pageId) ? pageId : null;
  };

  const pollJobStatus = async jobId => {
    const maxAttempts = 60;
    const pollDelayMs = 2000;

    for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
      const statusResponse = await apiFetch(
        withNamespace(`/admin/pages/ai-generate/status?id=${encodeURIComponent(jobId)}`),
        {
          headers: { Accept: 'application/json' }
        }
      );

      if (statusResponse.status === 504) {
        throw new Error(timeoutMessage);
      }

      const statusPayload = await parseJsonResponse(statusResponse);

      if (!statusResponse.ok) {
        const fallback = statusPayload.message || statusPayload.error || createErrorMessage;
        throw new Error(resolveErrorMessage(statusPayload, fallback));
      }

      const status = typeof statusPayload.status === 'string' ? statusPayload.status : '';
      if (status === 'pending') {
        await delay(pollDelayMs);
        continue;
      }

      if (status === 'failed') {
        const fallback = statusPayload.message || createErrorMessage;
        throw new Error(resolveErrorMessage(statusPayload, fallback));
      }

      if (status === 'done') {
        return statusPayload;
      }

      throw new Error(createErrorMessage);
    }

    throw new Error(timeoutMessage);
  };

  form.addEventListener('submit', async event => {
    event.preventDefault();
    setFeedback('');

    const slugValue = (slugInput?.value || '').trim().toLowerCase();
    const titleValue = (titleInput?.value || '').trim();
    const themeValue = (themeInput?.value || '').trim();
    const colorSchemeValue = (colorSchemeInput?.value || '').trim();
    const problemValue = (problemInput?.value || '').trim();
    const colorTokensText = getColorTokensText();
    const colorSchemeWithTokens = [colorSchemeValue, colorTokensText].filter(Boolean).join(' ').trim();
    const promptTemplateId = (promptTemplateSelect?.value || '').trim();
    const shouldCreateMenuItem = Boolean(createMenuItemCheckbox?.checked);
    const menuLabelValue = (menuLabelInput?.value || '').trim();

    if (!slugValue || !titleValue || !themeValue || !colorSchemeValue || !problemValue) {
      setFeedback(missingFieldsMessage);
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
    }

    try {
      const select = document.getElementById('pageContentSelect');
      const existingOption = select
        ? Array.from(select.options).find(option => option.value === slugValue)
        : null;
      let createdPage = null;

      if (!existingOption) {
        const createResponse = await apiFetch(withNamespace('/admin/pages'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json'
          },
          body: JSON.stringify({
            slug: slugValue,
            title: titleValue,
            content: ''
          })
        });
        const createPayload = await createResponse.json().catch(() => ({}));
        if (!createResponse.ok || !createPayload.page) {
          const errorMessage = createPayload.error || createErrorMessage;
          throw new Error(errorMessage);
        }
        createdPage = createPayload.page;
      }

      const response = await apiFetch(withNamespace('/admin/pages/ai-generate'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify({
          slug: slugValue,
          title: titleValue,
          theme: themeValue,
          colorScheme: colorSchemeWithTokens,
          problem: problemValue,
          promptTemplateId: promptTemplateId || undefined
        })
      });

      if (response.status === 504) {
        throw new Error(timeoutMessage);
      }

      const payload = await parseJsonResponse(response);
      if (!response.ok) {
        const fallback = payload.message || payload.error || createErrorMessage;
        throw new Error(resolveErrorMessage(payload, fallback));
      }

      const jobId = typeof payload.jobId === 'string' ? payload.jobId.trim() : '';
      if (!jobId) {
        throw new Error(createErrorMessage);
      }

      setFeedback(pendingMessage);

      const statusPayload = await pollJobStatus(jobId);
      const html = typeof statusPayload.html === 'string' ? statusPayload.html.trim() : '';
      if (!html) {
        setFeedback(emptyResponseMessage);
        return;
      }

      const page = createdPage || {
        slug: slugValue,
        title: titleValue,
        content: html
      };
      page.title = titleValue;
      page.content = html;

      if (existingOption) {
        updatePageOptionLabel(page);
        updatePageContentInInterface(slugValue, html);
        ensurePageSelected(slugValue);
      } else {
        addPageToInterface(page);
      }

      if (shouldCreateMenuItem) {
        const pageId = createdPage?.id ?? resolvePageId(slugValue);
        if (!pageId) {
          notify('Menüpunkt konnte nicht erstellt werden', 'danger');
        } else {
          const label = menuLabelValue || titleValue;
          const href = withBase(`/${slugValue}`);
          try {
            const menuResponse = await apiFetch(withNamespace(`/admin/pages/${pageId}/menu`), {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
              },
              body: JSON.stringify({ label, href })
            });
            const menuPayload = await menuResponse.json().catch(() => ({}));
            if (!menuResponse.ok) {
              const menuError = menuPayload.error || 'Menüpunkt konnte nicht erstellt werden.';
              throw new Error(menuError);
            }
            notify('Menüpunkt erstellt', 'success');
          } catch (error) {
            notify('Menüpunkt konnte nicht erstellt werden', 'danger');
          }
        }
      }

      form.reset();
      updateMenuLabelVisibility();
      if (modal) {
        modal.hide();
      }
      setFeedback('');
      notify(createdMessage, 'success');
    } catch (error) {
      const message = error instanceof Error ? error.message : createErrorMessage;
      setFeedback(message);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
      }
    }
  });
};

const assetUrlToAbsolute = href => {
  try {
    return new URL(href, window.location.href).href;
  } catch (error) {
    return href;
  }
};

const ensureStylesheetLoaded = (id, href, options = {}) => {
  if (document.getElementById(id)) {
    return;
  }
  const absoluteHref = assetUrlToAbsolute(href);
  const alreadyLoaded = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
    .some(link => link.href === absoluteHref);
  if (alreadyLoaded) {
    return;
  }
  const link = document.createElement('link');
  link.id = id;
  link.rel = 'stylesheet';
  link.href = href;
  if (options.media) {
    link.media = options.media;
  }
  if (options.dataset) {
    Object.entries(options.dataset).forEach(([key, value]) => {
      link.dataset[key] = value;
    });
  }
  document.head.appendChild(link);
};

const loadStylesheet = (id, href, options = {}) => new Promise(resolve => {
  const absoluteHref = assetUrlToAbsolute(href);
  let link = document.getElementById(id);
  if (!link) {
    link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    document.head.appendChild(link);
  }
  if (options.media) {
    link.media = options.media;
  }
  if (options.dataset) {
    Object.entries(options.dataset).forEach(([key, value]) => {
      link.dataset[key] = value;
    });
  }
  const matchesHref = link.href === absoluteHref;
  if (matchesHref && (link.dataset.loaded === '1' || link.sheet)) {
    resolve(true);
    return;
  }
  const cleanup = () => {
    link.removeEventListener('load', onLoad);
    link.removeEventListener('error', onError);
  };
  const onLoad = () => {
    cleanup();
    link.dataset.loaded = '1';
    resolve(true);
  };
  const onError = () => {
    cleanup();
    link.dataset.loaded = '0';
    resolve(false);
  };
  link.addEventListener('load', onLoad);
  link.addEventListener('error', onError);
  link.dataset.loaded = '0';
  link.href = href;
});

const ensureStylesheetWithFallback = (id, hrefs, options = {}) => {
  const candidates = Array.isArray(hrefs) ? hrefs.filter(Boolean) : [hrefs].filter(Boolean);
  if (!candidates.length) {
    return Promise.resolve();
  }
  return candidates
    .reduce(
      (promise, href) => promise.then(loaded => (loaded ? loaded : loadStylesheet(id, href, options))),
      Promise.resolve(false)
    )
    .then(() => undefined);
};

const ensureScriptLoaded = (id, src) => new Promise(resolve => {
  if (document.getElementById(id)) {
    resolve();
    return;
  }
  const absoluteSrc = assetUrlToAbsolute(src);
  const alreadyLoaded = Array.from(document.querySelectorAll('script[src]'))
    .some(script => script.src === absoluteSrc);
  if (alreadyLoaded) {
    resolve();
    return;
  }
  const script = document.createElement('script');
  script.id = id;
  script.src = src;
  script.defer = true;
  script.onload = () => resolve();
  script.onerror = () => resolve();
  document.body.appendChild(script);
});

const buildNamespacedCssCandidates = filename => {
  const namespace = resolvePageNamespace();
  const normalized = (namespace || '').trim().toLowerCase();
  const candidates = [];
  if (normalized && normalized !== 'default' && LANDING_NAMESPACE_ASSET_FOLDERS.has(normalized)) {
    candidates.push(withBase(`/css/${encodeURIComponent(normalized)}/${filename}`));
  }
  candidates.push(withBase(`/css/${filename}`));
  return candidates;
};

let previewAssetsPromises = {};

const ensurePreviewAssets = () => {
  const namespace = resolvePageNamespace();
  const cacheKey = namespace || 'default';
  if (previewAssetsPromises[cacheKey]) {
    return previewAssetsPromises[cacheKey];
  }
  const uikitCss = withBase('/css/uikit.min.css');
  const uikitJs = withBase('/js/uikit.min.js');
  const uikitIconsJs = withBase('/js/uikit-icons.min.js');
  const landingHighcontrastCss = withBase('/css/highcontrast.css');

  const styles = [
    ensureStylesheetWithFallback('preview-uikit-css', uikitCss),
    ensureStylesheetWithFallback('preview-landing-css', buildNamespacedCssCandidates('landing.css'), {
      media: 'print',
      dataset: { previewAsset: 'landing' }
    }),
    ensureStylesheetWithFallback('preview-landing-topbar-css', buildNamespacedCssCandidates('topbar.landing.css'), {
      media: 'print',
      dataset: { previewAsset: 'landing' }
    }),
    ensureStylesheetWithFallback('preview-landing-onboarding-css', buildNamespacedCssCandidates('onboarding.css'), {
      media: 'print',
      dataset: { previewAsset: 'landing' }
    }),
    ensureStylesheetWithFallback('preview-landing-highcontrast-css', landingHighcontrastCss, {
      media: 'print',
      dataset: { previewAsset: 'landing' }
    })
  ];

  const scripts = [];
  if (!window.UIkit) {
    scripts.push(ensureScriptLoaded('preview-uikit-js', uikitJs));
  }
  scripts.push(ensureScriptLoaded('preview-uikit-icons-js', uikitIconsJs));
  previewAssetsPromises[cacheKey] = Promise.all([...styles, ...scripts]).then(() => undefined);
  return previewAssetsPromises[cacheKey];
};

const setLandingPreviewMedia = enabled => {
  const targetMedia = enabled ? 'all' : 'print';
  document.querySelectorAll('link[data-preview-asset="landing"]').forEach(link => {
    link.media = targetMedia;
  });
};

const bindPreviewModal = () => {
  const modalEl = document.getElementById('preview-modal');
  if (!modalEl || modalEl.dataset.previewBound === '1') {
    return;
  }
  modalEl.addEventListener('hidden', () => {
    setLandingPreviewMedia(false);
  });
  modalEl.dataset.previewBound = '1';
};

export async function showPreview(formOverride = null) {
  const activeForm = formOverride || document.querySelector('.page-form:not(.uk-hidden)');
  const slug = (activeForm?.dataset.slug || '').trim();
  if (!slug) {
    return;
  }
  openPreviewInNewTab(slug);
}

window.showPreview = showPreview;

const TYPE_LABELS = {
  landing: 'Landing',
  legal: 'Legal',
  wiki: 'Wiki'
};

const TYPE_CLASSES = {
  landing: 'uk-label-success',
  legal: 'uk-label-warning',
  wiki: 'uk-label-danger'
};

function getTypeLabel(type) {
  if (!type) {
    return 'Standard';
  }
  const normalized = String(type);
  return TYPE_LABELS[normalized] || normalized;
}

function getTypeClass(type) {
  if (!type) {
    return 'uk-label';
  }
  const normalized = String(type);
  return TYPE_CLASSES[normalized] || 'uk-label';
}

function buildPageTreeList(nodes, level = 0) {
  const list = document.createElement('ul');
  list.className = 'uk-list uk-list-collapse';
  if (level > 0) {
    list.classList.add('uk-margin-small-left');
  }

  nodes.forEach(node => {
    const selectableSlug = node.slug || node.id;
    const item = document.createElement('li');
    if (selectableSlug) {
      item.dataset.pageTreeItem = selectableSlug;
    }
    const row = document.createElement('div');
    row.className = 'uk-flex uk-flex-between uk-flex-middle uk-flex-wrap';

    const info = document.createElement('div');
    const title = document.createElement('span');
    title.className = 'uk-text-bold';
    title.textContent = node.title || node.slug || 'Ohne Titel';
    info.appendChild(title);

    if (node.slug) {
      const slug = document.createElement('span');
      slug.className = 'uk-text-meta uk-margin-small-left';
      slug.textContent = `/${node.slug}`;
      info.appendChild(slug);
    }

    if (selectableSlug) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'uk-button uk-button-text uk-margin-small-left page-tree-select';
      button.dataset.pageSlug = selectableSlug;
      button.textContent = getTranslation('transEdit', 'Bearbeiten');
      info.appendChild(button);
    }

    const meta = document.createElement('div');
    meta.className = 'uk-flex uk-flex-middle uk-flex-wrap';

    const namespaceLabel = document.createElement('span');
    namespaceLabel.className = 'uk-text-meta';
    namespaceLabel.textContent = node.namespace || 'default';
    meta.appendChild(namespaceLabel);

    const typeLabel = document.createElement('span');
    typeLabel.className = `uk-label ${getTypeClass(node.type)} uk-margin-small-left`;
    typeLabel.textContent = getTypeLabel(node.type);
    meta.appendChild(typeLabel);

    if (node.language) {
      const language = document.createElement('span');
      language.className = 'uk-text-meta uk-margin-small-left';
      language.textContent = node.language;
      meta.appendChild(language);
    }

    if (selectableSlug) {
      const actionBtn = document.createElement('button');
      actionBtn.type = 'button';
      actionBtn.className = 'uk-button uk-button-default uk-button-small uk-margin-small-left page-tree-action';
      actionBtn.dataset.pageActionTrigger = '1';
      actionBtn.dataset.pageSlug = selectableSlug;
      actionBtn.dataset.pageTitle = node.title || node.slug || selectableSlug;
      actionBtn.textContent = getTranslation('transPageAction', 'Aktion');
      meta.appendChild(actionBtn);
    }

    row.appendChild(info);
    row.appendChild(meta);
    item.appendChild(row);

    if (Array.isArray(node.children) && node.children.length) {
      item.appendChild(buildPageTreeList(node.children, level + 1));
    }

    list.appendChild(item);
  });

  return list;
}

function resolveActiveNamespace(container) {
  const select = document.getElementById('pageNamespaceSelect');
  const candidate = select?.value || select?.dataset.pageNamespace || container?.dataset.namespace || '';
  return candidate.trim();
}

function updatePageTreeActive(container, slug) {
  if (!container) {
    return;
  }
  const activeSlug = (slug || '').trim();
  container.querySelectorAll('[data-page-tree-item]').forEach(item => {
    const isActive = item.dataset.pageTreeItem === activeSlug;
    item.classList.toggle('is-active', isActive);
    const button = item.querySelector('[data-page-slug]');
    if (button) {
      button.classList.toggle('is-active', isActive);
    }
  });
}

let pageTransferState = null;

const initPageTransferModal = () => {
  const modalEl = document.getElementById('pageTransferModal');
  if (!modalEl) {
    pageTransferState = null;
    return null;
  }

  const form = modalEl.querySelector('#pageTransferForm');
  const slugInput = modalEl.querySelector('#pageTransferSlug');
  const actionSelect = modalEl.querySelector('#pageTransferAction');
  const namespaceSelect = modalEl.querySelector('#pageTransferNamespace');
  const feedback = modalEl.querySelector('[data-page-transfer-feedback]');
  const titleEl = modalEl.querySelector('[data-page-transfer-title]');
  const submitBtn = modalEl.querySelector('[data-page-transfer-submit]');
  let modal = window.UIkit ? window.UIkit.modal(modalEl) : null;

  if (!form || !slugInput || !actionSelect || !namespaceSelect) {
    pageTransferState = null;
    return null;
  }

  const setFeedback = (message, status = 'danger') => {
    if (!feedback) {
      return;
    }
    feedback.classList.remove('uk-alert-danger', 'uk-alert-success');
    if (!message) {
      feedback.hidden = true;
      feedback.textContent = '';
      return;
    }
    feedback.textContent = message;
    feedback.hidden = false;
    feedback.classList.add(status === 'success' ? 'uk-alert-success' : 'uk-alert-danger');
  };

  const ensureTargetsAvailable = () => {
    const hasTargets = namespaceSelect.options.length > 1;
    if (!hasTargets) {
      const message = getTranslation('transPageTransferNoTargets', 'Keine weiteren Namespaces verfügbar.');
      setFeedback(message, 'danger');
      if (submitBtn) {
        submitBtn.disabled = true;
      }
    } else if (submitBtn) {
      submitBtn.disabled = false;
    }
    return hasTargets;
  };

  const open = ({ slug, title } = {}) => {
    const normalizedSlug = String(slug || '').trim();
    if (!normalizedSlug) {
      return;
    }
    slugInput.value = normalizedSlug;
    if (titleEl) {
      titleEl.textContent = title
        ? `${title} (${normalizedSlug})`
        : normalizedSlug;
    }
    actionSelect.value = '';
    if (namespaceSelect.options.length > 0) {
      namespaceSelect.value = '';
    }
    setFeedback('');
    ensureTargetsAvailable();
    if (!modal && window.UIkit) {
      modal = window.UIkit.modal(modalEl);
    }
    if (modal) {
      modal.show();
      return;
    }
    modalEl.hidden = false;
    modalEl.classList.add('uk-open');
    if (typeof notify === 'function') {
      notify('UIkit Modal ist nicht verfügbar. Dialog wird ohne UIkit geöffnet.', 'warning');
    }
  };

  form.addEventListener('submit', async event => {
    event.preventDefault();
    setFeedback('');

    if (!ensureTargetsAvailable()) {
      return;
    }

    const slugValue = slugInput.value.trim();
    const actionValue = actionSelect.value.trim();
    const targetNamespace = namespaceSelect.value.trim();
    if (!slugValue || !actionValue || !targetNamespace) {
      setFeedback(getTranslation('transPageTransferSelectionError', 'Bitte Aktion und Ziel-Namespace wählen.'));
      return;
    }

    const currentNamespace = resolvePageNamespace();
    if (currentNamespace && targetNamespace === currentNamespace) {
      setFeedback(getTranslation('transPageTransferSameNamespace', 'Ziel-Namespace muss unterschiedlich sein.'));
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
    }

    const endpoint = actionValue === 'move'
      ? `/admin/pages/${encodeURIComponent(slugValue)}/move`
      : `/admin/pages/${encodeURIComponent(slugValue)}/copy`;

    const select = document.getElementById('pageContentSelect');
    const previousSelection = select?.value || '';

    try {
      const response = await apiFetch(withNamespace(endpoint), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify({ targetNamespace })
      });

      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const message = payload.error || getTranslation('transPageTransferError', 'Aktion fehlgeschlagen.');
        throw new Error(message);
      }

      if (actionValue === 'move' && targetNamespace !== currentNamespace) {
        removePageFromInterface(slugValue);
      }

      const selectedAfter = select?.value || previousSelection;
      if (select && selectedAfter) {
        select.value = selectedAfter;
      }

      await initPageTree();

      const successMessage = actionValue === 'move'
        ? getTranslation('transPageMoved', 'Seite verschoben.')
        : getTranslation('transPageCopied', 'Seite kopiert.');
      notify(successMessage, 'success');
      if (modal) {
        modal.hide();
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : getTranslation('transPageTransferError', 'Aktion fehlgeschlagen.');
      setFeedback(message);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
      }
    }
  });

  const state = { open };
  pageTransferState = state;
  return state;
};

function bindPageTreeInteractions(container) {
  if (!container || container.dataset.treeBound === '1') {
    return;
  }
  container.dataset.treeBound = '1';

  container.addEventListener('click', event => {
    const actionTrigger = event.target?.closest?.('[data-page-action-trigger]');
    if (actionTrigger && container.contains(actionTrigger)) {
      event.preventDefault();
      const slug = (actionTrigger.dataset.pageSlug || '').trim();
      if (!slug) {
        return;
      }
      const title = (actionTrigger.dataset.pageTitle || '').trim();
      const state = pageTransferState || initPageTransferModal();
      state?.open({ slug, title });
      return;
    }

    const trigger = event.target?.closest?.('[data-page-slug]');
    if (!trigger || !container.contains(trigger)) {
      return;
    }
    const slug = (trigger.dataset.pageSlug || '').trim();
    if (!slug) {
      return;
    }
    const select = document.getElementById('pageContentSelect');
    if (select) {
      select.value = slug;
    }
    const state = pageSelectionState || initPageSelection();
    if (state && typeof state.toggleForms === 'function') {
      state.toggleForms(slug);
    }
    updatePageTreeActive(container, slug);
  });

  const select = document.getElementById('pageContentSelect');
  if (select) {
    select.addEventListener('change', () => {
      updatePageTreeActive(container, select.value);
    });
  }

  document.addEventListener('marketing-page:created', () => {
    initPageTree();
  });
  document.addEventListener('marketing-page:deleted', () => {
    initPageTree();
  });
}

async function initPageTree() {
  const container = document.querySelector('[data-page-tree]');
  if (!container) {
    return;
  }
  bindPageTreeInteractions(container);

  const loading = container.querySelector('[data-page-tree-loading]');
  const emptyMessage = container.dataset.empty || 'Keine Seiten vorhanden.';
  const errorMessage = container.dataset.error || 'Seitenbaum konnte nicht geladen werden.';
  const endpoint = withNamespace(container.dataset.endpoint || '/admin/pages/tree');

  try {
    const response = await (window.apiFetch ? window.apiFetch(endpoint) : fetch(endpoint));
    if (!response.ok) {
      throw new Error('page-tree-request-failed');
    }
    const payload = await response.json();
    const tree = Array.isArray(payload.tree) ? payload.tree : [];
    const activeNamespace = resolveActiveNamespace(container);
    const filteredTree = activeNamespace
      ? tree.filter(section => (section.namespace || '').trim() === activeNamespace)
      : tree;
    container.innerHTML = '';

    if (!filteredTree.length) {
      const empty = document.createElement('div');
      empty.className = 'uk-text-meta';
      empty.textContent = emptyMessage;
      container.appendChild(empty);
      return;
    }

    filteredTree.forEach(section => {
      const heading = document.createElement('h4');
      heading.className = 'uk-heading-line uk-margin-small-top';
      const headingText = document.createElement('span');
      headingText.textContent = section.namespace || 'default';
      heading.appendChild(headingText);
      container.appendChild(heading);

      const pages = Array.isArray(section.pages) ? section.pages : [];
      container.appendChild(buildPageTreeList(pages));
    });

    const select = document.getElementById('pageContentSelect');
    if (select) {
      updatePageTreeActive(container, select.value);
    }
  } catch (error) {
    if (loading) {
      loading.textContent = errorMessage;
    } else {
      const errorEl = document.createElement('div');
      errorEl.className = 'uk-text-danger';
      errorEl.textContent = errorMessage;
      container.appendChild(errorEl);
    }
  }
}

const initPagesModule = () => {
  initThemeToggle();
  prefetchQuizLinks();
  bindStartpageDomainSelect();
  loadStartpageState();
  initPageEditors();
  initPageSelection();
  bindStartpageToggle();
  initPageCreation();
  initAiPageCreation();
  initPageTransferModal();
  initPageTree();
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPagesModule);
} else {
  initPagesModule();
}
