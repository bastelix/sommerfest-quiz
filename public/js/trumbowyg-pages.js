/* global $, apiFetch, notify */

// Define custom UIkit templates for Trumbowyg
const sanitize = str => {
  const value = typeof str === 'string' ? str : String(str ?? '');
  if (window.DOMPurify && typeof window.DOMPurify.sanitize === 'function') {
    return window.DOMPurify.sanitize(value);
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

export function initPageEditors() {
  document.querySelectorAll('.page-form').forEach(form => {
    const slug = form.dataset.slug;
    const input = form.querySelector('input[name="content"]');
    const editorEl = form.querySelector('.page-editor');
    const isLandingPage = form.dataset.landing === 'true';
    const initial = editorEl.dataset.content;
    if (initial) {
      editorEl.innerHTML = sanitize(initial);
    }
    if (isLandingPage) {
      applyLandingStyling(editorEl);
    }
    $(editorEl).trumbowyg({
      lang: 'de',
      btns: [
        ['viewHTML'],
        ['formatting'],
        ['bold', 'italic', 'underline'],
        ['link'],
        ['insertImage'],
        ['unorderedList', 'orderedList'],
        ['variable'],
        ['template'],
        ['fullscreen']
      ],
      plugins: { template: true, variable: true }
    });
    const saveBtn = form.querySelector('.save-page-btn');
    saveBtn?.addEventListener('click', e => {
      e.preventDefault();
      const html = sanitize($(editorEl).trumbowyg('html'));
      input.value = html;
      apiFetch('/admin/pages/' + slug, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ content: html })
      }).then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Seite gespeichert', 'success');
      }).catch(() => notify('Fehler beim Speichern', 'danger'));
    });
  });
}

export function initPageSelection() {
  const select = document.getElementById('pageContentSelect');
  if (!select) {
    return;
  }

  const forms = Array.from(document.querySelectorAll('.page-form'));
  if (!forms.length) {
    return;
  }

  const toggleForms = slug => {
    let activeSlug = slug;
    if (!forms.some(form => form.dataset.slug === activeSlug)) {
      activeSlug = forms[0]?.dataset.slug || '';
    }
    forms.forEach(form => {
      form.classList.toggle('uk-hidden', form.dataset.slug !== activeSlug);
    });
  };

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
}

export function showPreview() {
  const editor = document.querySelector('.page-editor');
  if (!editor) return;
  const html = sanitize($(editor).trumbowyg('html'));
  const target = document.getElementById('preview-content');
  if (target) {
    target.innerHTML = html;
    if (editor.classList.contains('landing-editor')) {
      applyLandingStyling(target);
    } else {
      target.classList.remove('landing-editor', 'dark-mode');
      target.removeAttribute('data-theme');
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
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPagesModule);
} else {
  initPagesModule();
}
