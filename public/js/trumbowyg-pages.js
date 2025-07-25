/* global $, apiFetch, notify */

// Define custom UIkit templates for Trumbowyg
$.extend(true, $.trumbowyg, {
  langs: { de: { template: 'Vorlage' } },
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
              </div>`);
          },
          title: 'UIkit Hero'
        });

        trumbowyg.addBtnDef('uikit-card', {
          fn: function () {
            trumbowyg.execCmd('insertHTML',
              `<div class="uk-card uk-card-default uk-card-body">
                <h3 class="uk-card-title">Karte</h3>
                <p>Inhalt hier</p>
              </div>`);
          },
          title: 'UIkit Card'
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
      editorEl.innerHTML = initial;
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
        ['template'],
        ['fullscreen']
      ],
      plugins: { template: true }
    });
    const saveBtn = form.querySelector('.save-page-btn');
    saveBtn?.addEventListener('click', e => {
      e.preventDefault();
      const html = $(editorEl).trumbowyg('html');
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

export function showPreview() {
  const editor = document.querySelector('.page-editor');
  if (!editor) return;
  const html = $(editor).trumbowyg('html');
  const target = document.getElementById('preview-content');
  if (target) target.innerHTML = html;
  if (window.UIkit) {
    window.UIkit.modal('#preview-modal').show();
  }
}

window.showPreview = showPreview;

document.addEventListener('DOMContentLoaded', initPageEditors);
