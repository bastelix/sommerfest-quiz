/* global $, apiFetch, notify */

// Define custom UIkit templates for Trumbowyg
const DOMPURIFY_PAGE_EDITOR_CONFIG = {
  ADD_TAGS: ['video', 'source'],
  ADD_ATTR: ['autoplay', 'muted', 'loop', 'playsinline', 'preload', 'src', 'type', 'aria-label']
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

let landingStylesPromise;

function ensureLandingEditorStyles() {
  if (document.getElementById('landing-editor-styles')) {
    return Promise.resolve();
  }
  if (landingStylesPromise) {
    return landingStylesPromise;
  }
  const base = window.basePath || '';
  const requests = LANDING_STYLE_FILES.map(path => {
    const url = base.replace(/\/$/, '') + path;
    return fetch(url).then(resp => (resp.ok ? resp.text() : '')).catch(() => '');
  });
  landingStylesPromise = Promise.all(requests).then(chunks => {
    const scoped = chunks
      .map(chunk => scopeLandingCss(chunk, '.landing-editor'))
      .filter(Boolean)
      .join('\n');
    if (scoped) {
      const styleEl = document.createElement('style');
      styleEl.id = 'landing-editor-styles';
      styleEl.textContent = scoped;
      document.head.appendChild(styleEl);
    }
    if (!document.getElementById('landing-editor-font')) {
      const fontLink = document.createElement('link');
      fontLink.id = 'landing-editor-font';
      fontLink.rel = 'stylesheet';
      fontLink.href = 'https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap';
      document.head.appendChild(fontLink);
    }
  }).catch(err => {
    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('Failed to load landing editor styles', err);
    }
  });
  return landingStylesPromise;
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
      apiFetch(`/admin/pages/${encodeURIComponent(slug)}`, {
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

      apiFetch(`/admin/pages/${encodeURIComponent(slug)}`, { method: 'DELETE' })
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
      const response = await apiFetch('/admin/pages', {
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

export function showPreview() {
  const activeForm = document.querySelector('.page-form:not(.uk-hidden)');
  const editor = activeForm ? ensurePageEditorInitialized(activeForm) : null;
  if (!editor) return;
  const html = sanitize($(editor).trumbowyg('html'));
  const target = document.getElementById('preview-content');
  if (target) {
    target.innerHTML = html;
    if (activeForm?.dataset.landing === 'true') {
      applyLandingStyling(target);
    } else {
      resetLandingStyling(target);
    }
  }
  if (window.UIkit) {
    window.UIkit.modal('#preview-modal').show();
  }
}

window.showPreview = showPreview;
const initPagesModule = () => {
  initPageEditors();
  initPageSelection();
  initPageCreation();
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPagesModule);
} else {
  initPagesModule();
}
