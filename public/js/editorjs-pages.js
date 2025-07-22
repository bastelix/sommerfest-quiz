/* global EditorJS, Header, List, apiFetch, notify */

function htmlToEditorData(html) {
  const parser = new DOMParser();
  const doc = parser.parseFromString(html, 'text/html');
  const blocks = [];
  doc.body.childNodes.forEach(node => {
    if (node.nodeType !== 1) return;
    const tag = node.tagName.toUpperCase();
    if (tag === 'P') {
      blocks.push({ type: 'paragraph', data: { text: node.innerHTML } });
    } else if (/H[1-6]/.test(tag)) {
      blocks.push({ type: 'header', data: { text: node.textContent, level: Number(tag[1]) } });
    } else if (tag === 'UL' || tag === 'OL') {
      const style = tag === 'OL' ? 'ordered' : 'unordered';
      const items = Array.from(node.children)
        .filter(n => n.tagName === 'LI')
        .map(li => li.innerHTML);
      blocks.push({ type: 'list', data: { style, items } });
    }
  });
  return { blocks };
}

function editorDataToHtml(data) {
  return (data.blocks || []).map(block => {
    switch (block.type) {
      case 'paragraph':
        return `<p>${block.data.text}</p>`;
      case 'header':
        const lvl = block.data.level || 1;
        return `<h${lvl}>${block.data.text}</h${lvl}>`;
      case 'list':
        const tag = block.data.style === 'ordered' ? 'ol' : 'ul';
        const items = (block.data.items || []).map(item => `<li>${item}</li>`).join('');
        return `<${tag}>${items}</${tag}>`;
      default:
        return '';
    }
  }).join('');
}

export function initPageEditors() {
  document.querySelectorAll('.page-form').forEach(form => {
    const slug = form.dataset.slug;
    const input = form.querySelector('input[name="content"]');
    const editorEl = form.querySelector('.editorjs');
    const data = htmlToEditorData(editorEl.innerHTML.trim());
    editorEl.innerHTML = '';
    const editor = new EditorJS({
      holder: editorEl,
      data,
      tools: {
        header: Header,
        list: List
      }
    });
    const saveBtn = form.querySelector('.save-page-btn');
    saveBtn?.addEventListener('click', e => {
      e.preventDefault();
      editor.save().then(output => {
        const html = editorDataToHtml(output);
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
  });
}
document.addEventListener('DOMContentLoaded', initPageEditors);
