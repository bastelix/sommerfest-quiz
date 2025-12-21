/* global $, apiFetch, notify */

// Define custom UIkit templates for Trumbowyg
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
    'uk-accordion',
    'uk-close',
    'uk-grid',
    'uk-icon',
    'uk-modal',
    'uk-navbar',
    'uk-navbar-toggle-icon',
    'uk-offcanvas',
    'uk-scroll',
    'uk-scrollspy',
    'uk-slider',
    'uk-slider-item',
    'uk-slidenav-next',
    'uk-slidenav-previous',
    'uk-slideshow',
    'uk-switcher',
    'uk-toggle',
    'uk-tooltip',
    'data-uk-accordion',
    'data-uk-close',
    'data-uk-grid',
    'data-uk-icon',
    'data-uk-modal',
    'data-uk-offcanvas',
    'data-uk-scrollspy',
    'data-uk-slider',
    'data-uk-slider-item',
    'data-uk-slidenav-next',
    'data-uk-slidenav-previous',
    'data-uk-switcher',
    'data-uk-toggle',
    'data-uk-tooltip'
  ]
};

const sanitize = str => {
  const value = typeof str === 'string' ? str : String(str ?? '');
  if (window.DOMPurify && typeof window.DOMPurify.sanitize === 'function') {
    return window.DOMPurify.sanitize(value, DOMPURIFY_PAGE_EDITOR_CONFIG);
  }
  if (window.console && typeof window.console.warn === 'function') {
    window.console.warn('DOMPurify not available, skipping HTML sanitization for page editor content.');
  }
  return value;
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
$.extend(true, $.trumbowyg, {
  langs: { de: { template: 'Vorlage', variable: 'Variable' } },
  plugins: {
    template: {
      init: function (trumbowyg) {
        trumbowyg.addBtnDef('template', {
          dropdown: ['uikit-hero', 'uikit-card'],
          ico: 'insertTemplate'
        });

        trumbowyg.addBtnDef('uikit-hero', {
          fn: function () {
            trumbowyg.execCmd('insertHTML',
              `<div class="uk-section uk-section-primary uk-light">
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
              </section>`);
          },
          title: 'UIkit Hero'
        });

        trumbowyg.addBtnDef('uikit-card', {
          fn: function () {
            trumbowyg.execCmd('insertHTML',
              `<div class="uk-card qr-card uk-card-body">
                <h3 class="uk-card-title">Karte</h3>
                <p>Inhalt hier</p>
              </div>`);
          },
          title: 'UIkit Card'
        });
      }
    },
    variable: {
      init: function (trumbowyg) {
        const vars = window.profileVars || {};
        const keys = Object.keys(vars);
        if (!keys.length) return;
        const dropdown = keys.map(k => `var-${k}`);
        trumbowyg.addBtnDef('variable', {
          dropdown: dropdown,
          ico: 'insertTemplate'
        });
        keys.forEach(k => {
          trumbowyg.addBtnDef(`var-${k}`, {
            fn: function () {
              trumbowyg.execCmd('insertText', `[${k}]`);
            },
            title: `${k}${vars[k] ? ' (' + vars[k] + ')' : ''}`
          });
        });
      }
    }
  }
});

const LANDING_STYLE_FILES = [
  '/css/landing.css',
  '/css/onboarding.css',
  '/css/topbar.landing.css'
];

const LANDING_FONT_URL = 'https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap';

const landingStylesPromises = {};

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

function ensureScopedLandingStyles(styleId, scopeSelector) {
  if (document.getElementById(styleId)) {
    return Promise.resolve();
  }
  if (landingStylesPromises[styleId]) {
    return landingStylesPromises[styleId];
  }
  const base = window.basePath || '';
  const requests = LANDING_STYLE_FILES.map(path => {
    const url = base.replace(/\/$/, '') + path;
    return fetch(url).then(resp => (resp.ok ? resp.text() : '')).catch(() => '');
  });
  landingStylesPromises[styleId] = Promise.all(requests).then(chunks => {
    const scoped = chunks
      .map(chunk => scopeLandingCss(chunk, scopeSelector))
      .filter(Boolean)
      .join('\n');
    if (scoped) {
      const styleEl = document.createElement('style');
      styleEl.id = styleId;
      styleEl.textContent = scoped;
      document.head.appendChild(styleEl);
    }
    ensureLandingFont();
  }).catch(err => {
    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('Failed to load landing styles', err);
    }
  });
  return landingStylesPromises[styleId];
}

function ensureLandingEditorStyles() {
  return ensureScopedLandingStyles('landing-editor-styles', '.landing-editor');
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

function applyLandingStyling(element) {
  if (!element) {
    return;
  }
  ensureLandingEditorStyles();
  element.classList.add('landing-editor');
  if (!element.hasAttribute('data-theme')) {
    element.setAttribute('data-theme', 'dark');
  }
  element.classList.add('dark-mode');
}

const PREVIEW_LANDING_CLASS = 'landing-preview';

function ensureLandingPreviewStyles() {
  return ensureScopedLandingStyles('landing-preview-styles', `.${PREVIEW_LANDING_CLASS}`);
}

function applyLandingPreviewStyling(element) {
  if (!element) {
    return;
  }
  ensureLandingPreviewStyles();
  element.classList.add(PREVIEW_LANDING_CLASS);
  if (!element.hasAttribute('data-theme')) {
    element.setAttribute('data-theme', 'dark');
  }
  element.classList.add('dark-mode');
}

function resetLandingPreviewStyling(element) {
  if (!element) {
    return;
  }
  element.classList.remove(PREVIEW_LANDING_CLASS, 'dark-mode');
  element.removeAttribute('data-theme');
}

const PAGE_EDITOR_BUTTON_GROUPS = [
  ['viewHTML'],
  ['formatting'],
  ['bold', 'italic', 'underline'],
  ['link'],
  ['insertImage'],
  ['unorderedList', 'orderedList'],
  ['variable'],
  ['template'],
  ['fullscreen']
];

const getEditorElement = form => (form ? form.querySelector('.page-editor') : null);

const resetLandingStyling = element => {
  if (!element) {
    return;
  }
  element.classList.remove('landing-editor', 'dark-mode');
  element.removeAttribute('data-theme');
};

const ensurePageEditorInitialized = form => {
  const editorEl = getEditorElement(form);
  if (!editorEl || editorEl.dataset.editorInitialized === '1') {
    return editorEl;
  }

  const initial = editorEl.dataset.content || '';
  const sanitized = sanitize(initial);
  editorEl.innerHTML = sanitized;
  editorEl.dataset.content = sanitized;

  if (form?.dataset.landing === 'true') {
    applyLandingStyling(editorEl);
  } else {
    resetLandingStyling(editorEl);
  }

  $(editorEl).trumbowyg({
    lang: 'de',
    btns: PAGE_EDITOR_BUTTON_GROUPS,
    plugins: { template: true, variable: true }
  });
  editorEl.dataset.editorInitialized = '1';
  return editorEl;
};

const teardownPageEditor = form => {
  const editorEl = getEditorElement(form);
  if (!editorEl || editorEl.dataset.editorInitialized !== '1') {
    return;
  }

  let html = '';
  try {
    html = $(editorEl).trumbowyg('html');
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
    $(editorEl).trumbowyg('destroy');
  } catch (error) {
    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('Failed to destroy page editor instance', error);
    }
  }

  editorEl.innerHTML = '';
  resetLandingStyling(editorEl);
  delete editorEl.dataset.editorInitialized;
};

const basePath = (window.basePath || '').replace(/\/$/, '');
const withBase = path => `${basePath}${path}`;

let pageSelectionState = null;

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

  const previewLink = document.createElement('a');
  previewLink.className = 'uk-button uk-button-default uk-margin-small-left preview-link';
  previewLink.href = withBase(`/${slug}`);
  previewLink.target = '_blank';
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
      const activeEditor = ensurePageEditorInitialized(form) || editorEl;
      const html = sanitize($(activeEditor).trumbowyg('html'));
      input.value = html;
      editorEl.dataset.content = html;
      if (window.pagesContent && typeof window.pagesContent === 'object') {
        window.pagesContent[slug] = html;
      }
      apiFetch(withNamespace(`/admin/pages/${encodeURIComponent(slug)}`), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ content: html })
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
    select.append(option);
  }

  let form = container.querySelector(`.page-form[data-slug="${slug}"]`);
  if (!form) {
    form = buildPageForm(page);
    container.append(form);
    setupPageForm(form);
  }

  if (window.pagesContent && typeof window.pagesContent === 'object') {
    window.pagesContent[slug] = page.content || '';
  }

  if (pageSelectionState) {
    pageSelectionState.refresh();
    select.value = slug;
    pageSelectionState.toggleForms(slug);
  }

  document.dispatchEvent(new CustomEvent('marketing-page:created', { detail: page }));
};

const removePageFromInterface = slug => {
  const select = document.getElementById('pageContentSelect');
  const container = document.getElementById('pageFormsContainer');
  const normalized = (slug || '').trim();
  if (!select || !container || !normalized) {
    return;
  }

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

  const refresh = () => {
    forms = container
      ? Array.from(container.querySelectorAll('.page-form'))
      : Array.from(document.querySelectorAll('.page-form'));
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

  select.addEventListener('change', () => {
    toggleForms(select.value);
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

      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.page) {
        const errorMessage = payload.error || 'Die Seite konnte nicht erstellt werden.';
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

let previewAssetsPromise;

const ensurePreviewAssets = () => {
  if (previewAssetsPromise) {
    return previewAssetsPromise;
  }
  const uikitCss = withBase('/css/uikit.min.css');
  const uikitJs = withBase('/js/uikit.min.js');
  const uikitIconsJs = withBase('/js/uikit-icons.min.js');
  const landingCss = withBase('/css/landing.css');
  const landingTopbarCss = withBase('/css/topbar.landing.css');
  const landingOnboardingCss = withBase('/css/onboarding.css');

  ensureStylesheetLoaded('preview-uikit-css', uikitCss);
  ensureStylesheetLoaded('preview-landing-css', landingCss, {
    media: 'print',
    dataset: { previewAsset: 'landing' }
  });
  ensureStylesheetLoaded('preview-landing-topbar-css', landingTopbarCss, {
    media: 'print',
    dataset: { previewAsset: 'landing' }
  });
  ensureStylesheetLoaded('preview-landing-onboarding-css', landingOnboardingCss, {
    media: 'print',
    dataset: { previewAsset: 'landing' }
  });

  const scripts = [];
  if (!window.UIkit) {
    scripts.push(ensureScriptLoaded('preview-uikit-js', uikitJs));
  }
  scripts.push(ensureScriptLoaded('preview-uikit-icons-js', uikitIconsJs));
  previewAssetsPromise = Promise.all(scripts).then(() => undefined);
  return previewAssetsPromise;
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

export async function showPreview() {
  const activeForm = document.querySelector('.page-form:not(.uk-hidden)');
  const editor = activeForm ? ensurePageEditorInitialized(activeForm) : null;
  if (!editor) return;
  const html = sanitize($(editor).trumbowyg('html'));
  const target = document.getElementById('preview-content');
  const isLandingPreview = activeForm?.dataset.landing === 'true';
  if (target) {
    target.innerHTML = html;
    if (isLandingPreview) {
      applyLandingPreviewStyling(target);
    } else {
      resetLandingPreviewStyling(target);
    }
    await ensurePreviewAssets();
    setLandingPreviewMedia(isLandingPreview);
    if (window.UIkit && typeof window.UIkit.update === 'function') {
      window.UIkit.update(target, 'mutation');
    }
  }
  if (window.UIkit) {
    bindPreviewModal();
    window.UIkit.modal('#preview-modal').show();
  }
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

    const typeLabel = document.createElement('span');
    typeLabel.className = `uk-label ${getTypeClass(node.type)}`;
    typeLabel.textContent = getTypeLabel(node.type);
    meta.appendChild(typeLabel);

    if (node.language) {
      const language = document.createElement('span');
      language.className = 'uk-text-meta uk-margin-small-left';
      language.textContent = node.language;
      meta.appendChild(language);
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

function bindPageTreeInteractions(container) {
  if (!container || container.dataset.treeBound === '1') {
    return;
  }
  container.dataset.treeBound = '1';

  container.addEventListener('click', event => {
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
  initPageEditors();
  initPageSelection();
  initPageCreation();
  initPageTree();
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPagesModule);
} else {
  initPagesModule();
}
