/* global $, apiFetch, notify */

// Define custom UIkit templates for Trumbowyg
const sanitize = str => {
  if (window.DOMPurify) {
    return window.DOMPurify.sanitize(str);
  }
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
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

export function initPageEditors() {
  document.querySelectorAll('.page-form').forEach(form => {
    const slug = form.dataset.slug;
    const input = form.querySelector('input[name="content"]');
    const editorEl = form.querySelector('.page-editor');
    const initial = editorEl.dataset.content;
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
  if (target) target.innerHTML = html;
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
