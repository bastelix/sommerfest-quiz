/* global $, apiFetch, notify, UIkit */

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
const basePath = window.basePath || '';
const formsInitialized = new WeakSet();
let pageSelect = null;
let currentSlug = '';
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

const labelForPage = page => {
  if (page && typeof page.title === 'string' && page.title.trim() !== '') {
    return page.title.trim();
  }
  const slug = typeof page.slug === 'string' ? page.slug : '';
  return slug.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
};

const initEditorForForm = form => {
  if (!form || formsInitialized.has(form)) {
    return;
  }

  const slug = form.dataset.slug || '';
  const input = form.querySelector('input[name="content"]');
  const editorEl = form.querySelector('.page-editor');
  if (!slug || !input || !editorEl) {
    return;
  }

  const initial = editorEl.dataset.content || input.value || '';
  if (initial) {
    editorEl.innerHTML = sanitize(initial);
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

  formsInitialized.add(form);
};

export function initPageEditors(scope = document) {
  if (!scope) {
    return;
  }
  if (scope instanceof HTMLElement && scope.classList.contains('page-form')) {
    initEditorForForm(scope);
    return;
  }

  const root = scope.querySelectorAll ? scope : document;
  root.querySelectorAll('.page-form').forEach(initEditorForForm);
}

const getForms = () => Array.from(document.querySelectorAll('.page-form'));

const toggleForms = slug => {
  const forms = getForms();
  if (!forms.length) {
    currentSlug = '';
    return;
  }

  let activeSlug = slug;
  if (!forms.some(form => form.dataset.slug === activeSlug)) {
    activeSlug = forms[0]?.dataset.slug || '';
  }

  forms.forEach(form => {
    form.classList.toggle('uk-hidden', form.dataset.slug !== activeSlug);
  });

  currentSlug = activeSlug;
  if (pageSelect && activeSlug && pageSelect.value !== activeSlug) {
    pageSelect.value = activeSlug;
  }
};

export function initPageSelection() {
  pageSelect = document.getElementById('pageContentSelect');
  if (!pageSelect) {
    return;
  }

  let selected = pageSelect.dataset.selected || pageSelect.value;
  if (!selected && pageSelect.options.length > 0) {
    selected = pageSelect.options[0].value;
  }
  if (selected) {
    pageSelect.value = selected;
  }
  toggleForms(selected);

  pageSelect.addEventListener('change', () => {
    toggleForms(pageSelect.value);
  });
}

const addPageOption = page => {
  if (!pageSelect) {
    return;
  }
  const slug = page.slug;
  if (!slug) {
    return;
  }

  const existing = Array.from(pageSelect.options).some(opt => opt.value === slug);
  if (existing) {
    pageSelect.value = slug;
    return;
  }

  const option = document.createElement('option');
  option.value = slug;
  option.textContent = labelForPage(page);
  pageSelect.appendChild(option);
  pageSelect.value = slug;
};

const createPageForm = page => {
  const container = document.getElementById('pageFormsContainer');
  if (!container) {
    return null;
  }

  const slug = page.slug;
  const content = sanitize(page.content || '');
  const form = document.createElement('form');
  form.className = 'page-form uk-hidden';
  form.dataset.slug = slug;

  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'content';
  input.id = 'page_' + slug;
  input.value = content;

  const editor = document.createElement('div');
  editor.className = 'page-editor';
  editor.dataset.content = content;

  const actions = document.createElement('div');
  actions.className = 'uk-margin-top';

  const saveBtn = document.createElement('button');
  saveBtn.className = 'uk-button uk-button-primary save-page-btn';
  saveBtn.type = 'submit';
  saveBtn.textContent = 'Speichern';

  const preview = document.createElement('a');
  preview.className = 'uk-button uk-button-default preview-link';
  preview.href = `${basePath}/${slug}`;
  preview.target = '_blank';
  preview.textContent = 'Vorschau';

  actions.appendChild(saveBtn);
  actions.appendChild(preview);

  form.appendChild(input);
  form.appendChild(editor);
  form.appendChild(actions);

  container.appendChild(form);
  initPageEditors(form);

  return form;
};

const refreshSelection = slug => {
  if (slug) {
    toggleForms(slug);
  } else {
    toggleForms(currentSlug);
  }
};

const setupCreatePageForm = () => {
  const form = document.getElementById('pageCreateForm');
  if (!form || form.dataset.bound === '1') {
    return;
  }

  const modalEl = document.getElementById('pageCreateModal');
  const modal = modalEl && typeof UIkit !== 'undefined' ? UIkit.modal(modalEl) : null;
  const messageEl = document.getElementById('pageCreateMessage');
  const slugInput = document.getElementById('newPageSlug');
  const titleInput = document.getElementById('newPageTitle');
  const contentInput = document.getElementById('newPageContent');
  const defaultContent = contentInput ? contentInput.value : '';

  const clearMessage = () => {
    if (!messageEl) return;
    messageEl.hidden = true;
    messageEl.textContent = '';
    messageEl.className = 'uk-alert';
  };

  const showMessage = (text, status = 'danger') => {
    if (!messageEl) return;
    messageEl.textContent = text;
    messageEl.className = `uk-alert uk-alert-${status}`;
    messageEl.hidden = false;
  };

  form.addEventListener('submit', async event => {
    event.preventDefault();
    clearMessage();

    const slug = (slugInput?.value || '').trim().toLowerCase();
    const title = (titleInput?.value || '').trim();
    const content = contentInput ? contentInput.value : '';

    if (!slug || !title) {
      showMessage('Slug und Titel werden benötigt.');
      return;
    }

    try {
      const response = await apiFetch('/admin/pages', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ slug, title, content })
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        if (payload && payload.errors) {
          const messages = Object.values(payload.errors).join(' ');
          showMessage(messages || 'Fehler beim Anlegen der Seite.');
        } else {
          showMessage(payload.error || 'Fehler beim Anlegen der Seite.');
        }
        return;
      }

      const page = payload.page || payload;
      if (!page || !page.slug) {
        showMessage('Antwort konnte nicht verarbeitet werden.');
        return;
      }

      addPageOption(page);
      createPageForm(page);
      refreshSelection(page.slug);
      notify('Seite erstellt', 'success');
      form.reset();
      if (contentInput) {
        contentInput.value = defaultContent;
      }
      if (modal) {
        modal.hide();
      }
    } catch (error) {
      showMessage(error?.message || 'Fehler beim Anlegen der Seite.');
    }
  });

  form.dataset.bound = '1';
};

export function showPreview() {
  const editor = document.querySelector('.page-editor');
  if (!editor) return;
  const html = sanitize($(editor).trumbowyg('html'));
  const target = document.getElementById('preview-content');
  if (target) target.innerHTML = html;
  if (window.UIkit) {
    window.UIkit.modal('#preview-modal').show();
  }
}

window.showPreview = showPreview;
const initPagesModule = () => {
  initPageEditors();
  initPageSelection();
  setupCreatePageForm();
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPagesModule);
} else {
  initPagesModule();
}
