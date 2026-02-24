import { Editor } from './vendor/tiptap/core.esm.js';
import StarterKit from './vendor/tiptap/starter-kit.esm.js';

// ── Slug generation ────────────────────────────────────────────────────────

function slugify(text) {
  return text
    .toString()
    .toLowerCase()
    .replace(/ä/g, 'ae')
    .replace(/ö/g, 'oe')
    .replace(/ü/g, 'ue')
    .replace(/ß/g, 'ss')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function initSlugSuggestion() {
  const form = document.querySelector('form[data-is-edit]');
  const titleInput = document.getElementById('newsTitle');
  const slugInput = document.getElementById('newsSlug');
  const suggestBtn = document.getElementById('newsSlugSuggest');

  if (!titleInput || !slugInput) return;

  const isEdit = form?.dataset.isEdit === 'true';
  let lastSuggested = '';

  function suggest() {
    const proposed = slugify(titleInput.value);
    slugInput.value = proposed;
    lastSuggested = proposed;
  }

  suggestBtn?.addEventListener('click', suggest);

  if (!isEdit) {
    // Auto-fill on create: update as long as user hasn't manually deviated
    titleInput.addEventListener('input', () => {
      if (slugInput.value === '' || slugInput.value === lastSuggested) {
        suggest();
      }
    });
  }
}

// ── Toolbar helpers ────────────────────────────────────────────────────────

function buildToolbar(editor) {
  const toolbar = document.createElement('div');
  toolbar.className = 'news-editor-toolbar';
  toolbar.setAttribute('aria-label', 'Formatierung');

  const buttons = [];

  function makeBtn(label, title, action, activeCheck) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.title = title;
    btn.textContent = label;
    btn.className = 'news-editor-btn';
    btn.addEventListener('mousedown', e => {
      e.preventDefault();
      action();
      updateActive();
    });
    buttons.push({ btn, activeCheck });
    return btn;
  }

  function sep() {
    const s = document.createElement('span');
    s.className = 'news-editor-btn-sep';
    s.setAttribute('aria-hidden', 'true');
    return s;
  }

  function updateActive() {
    for (const { btn, activeCheck } of buttons) {
      btn.classList.toggle('is-active', activeCheck());
    }
  }

  // Headings H1–H5
  for (let level = 1; level <= 5; level++) {
    toolbar.appendChild(makeBtn(
      `H${level}`,
      `Überschrift ${level}`,
      () => editor.chain().focus().toggleHeading({ level }).run(),
      () => editor.isActive('heading', { level })
    ));
  }

  toolbar.appendChild(sep());

  // Bold
  toolbar.appendChild(makeBtn(
    'B', 'Fett',
    () => editor.chain().focus().toggleBold().run(),
    () => editor.isActive('bold')
  ));

  // Italic
  const italicBtn = makeBtn(
    'I', 'Kursiv',
    () => editor.chain().focus().toggleItalic().run(),
    () => editor.isActive('italic')
  );
  italicBtn.style.fontStyle = 'italic';
  toolbar.appendChild(italicBtn);

  toolbar.appendChild(sep());

  // Bullet list
  toolbar.appendChild(makeBtn(
    '• Liste', 'Aufzählung',
    () => editor.chain().focus().toggleBulletList().run(),
    () => editor.isActive('bulletList')
  ));

  // Ordered list
  toolbar.appendChild(makeBtn(
    '1. Liste', 'Nummerierte Liste',
    () => editor.chain().focus().toggleOrderedList().run(),
    () => editor.isActive('orderedList')
  ));

  // Update active state on editor selection change
  editor.on('selectionUpdate', updateActive);
  editor.on('update', updateActive);

  return toolbar;
}

// ── Rich text editor ───────────────────────────────────────────────────────

function initContentEditor() {
  const editorContainer = document.getElementById('newsContentEditor');
  const hiddenTextarea = document.getElementById('newsContent');

  if (!editorContainer || !hiddenTextarea) return;

  const editorArea = document.createElement('div');
  editorArea.className = 'news-editor-area';
  editorContainer.appendChild(editorArea);

  const editor = new Editor({
    element: editorArea,
    content: hiddenTextarea.value || '',
    extensions: [
      StarterKit.configure({
        heading: { levels: [1, 2, 3, 4, 5] },
      }),
    ],
    editorProps: {
      attributes: {
        class: 'news-editor-richtext',
        spellcheck: 'true',
      },
    },
    onUpdate: ({ editor: e }) => {
      hiddenTextarea.value = e.getHTML();
    },
  });

  const toolbar = buildToolbar(editor);
  editorContainer.insertBefore(toolbar, editorArea);

  // Ensure hidden textarea is populated on submit (for empty editor)
  hiddenTextarea.closest('form')?.addEventListener('submit', () => {
    hiddenTextarea.value = editor.getHTML();
  });
}

// ── Init ──────────────────────────────────────────────────────────────────

initSlugSuggestion();
initContentEditor();
