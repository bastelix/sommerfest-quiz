/* global $, apiFetch, notify */

export function initPageEditors() {
  document.querySelectorAll('.page-form').forEach(form => {
    const slug = form.dataset.slug;
    const input = form.querySelector('input[name="content"]');
    const editorEl = form.querySelector('.page-editor');
    $(editorEl).trumbowyg();
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

document.addEventListener('DOMContentLoaded', initPageEditors);
