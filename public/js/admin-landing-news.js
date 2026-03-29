import { Editor, Mark, Node as TiptapNode, mergeAttributes, markPasteRule } from './vendor/tiptap/core.esm.js';
import StarterKit from './vendor/tiptap/starter-kit.esm.js';

// ── Link extension ───────────────────────────────────────────────────────

const LinkMark = Mark.create({
  name: 'link',

  priority: 1000,

  keepOnSplit: false,

  addOptions() {
    return {
      HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer nofollow' },
    };
  },

  addAttributes() {
    return {
      href: { default: null },
      target: { default: this.options.HTMLAttributes.target },
      rel: { default: this.options.HTMLAttributes.rel },
    };
  },

  parseHTML() {
    return [{ tag: 'a[href]:not([href *= "javascript:" i])' }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['a', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0];
  },

  addCommands() {
    return {
      setLink: attributes => ({ chain }) => {
        return chain().setMark(this.name, attributes).run();
      },
      unsetLink: () => ({ chain }) => {
        return chain().unsetMark(this.name, { extendEmptyMarkRange: true }).run();
      },
      toggleLink: attributes => ({ chain, editor }) => {
        if (editor.isActive(this.name)) {
          return chain().unsetMark(this.name, { extendEmptyMarkRange: true }).run();
        }
        return chain().setMark(this.name, attributes).run();
      },
    };
  },

  addPasteRules() {
    return [
      markPasteRule({
        find: /https?:\/\/[^\s<>]+/g,
        type: this.type,
        getAttributes: match => ({ href: match[0] }),
      }),
    ];
  },
});

// ── Image extension ──────────────────────────────────────────────────────

const ImageNode = TiptapNode.create({
  name: 'image',

  group: 'block',

  atom: true,

  addAttributes() {
    return {
      src: { default: null },
      alt: { default: null },
      title: { default: null },
    };
  },

  parseHTML() {
    return [{ tag: 'img[src]' }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['img', mergeAttributes(HTMLAttributes)];
  },

  addCommands() {
    return {
      setImage: attributes => ({ commands }) => {
        return commands.insertContent({ type: this.name, attrs: attributes });
      },
    };
  },
});

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

  toolbar.appendChild(sep());

  // Link
  toolbar.appendChild(makeBtn(
    '🔗 Link', 'Link einfügen / entfernen',
    () => {
      if (editor.isActive('link')) {
        editor.chain().focus().unsetLink().run();
        return;
      }
      const href = prompt('URL eingeben:');
      if (href) {
        editor.chain().focus().setLink({ href }).run();
      }
    },
    () => editor.isActive('link')
  ));

  toolbar.appendChild(sep());

  // Image
  toolbar.appendChild(makeBtn(
    '🖼 Bild', 'Bild einfügen',
    () => {
      const src = prompt('Bild-URL eingeben:');
      if (src) {
        const alt = prompt('Alternativtext (optional):') || '';
        editor.chain().focus().setImage({ src, alt }).run();
      }
    },
    () => false
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
      LinkMark,
      ImageNode,
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

// ── Advanced options toggle ───────────────────────────────────────────────

function initAdvancedToggle() {
  const toggleBtn = document.getElementById('toggleAdvanced');
  const panel = document.getElementById('advancedOptions');
  const icon = document.getElementById('toggleAdvancedIcon');

  if (!toggleBtn || !panel) return;

  function updateIcon() {
    if (icon) {
      icon.textContent = panel.style.display === 'none' ? '\u25B6' : '\u25BC';
    }
  }

  toggleBtn.addEventListener('click', () => {
    panel.style.display = panel.style.display === 'none' ? '' : 'none';
    updateIcon();
  });

  updateIcon();
}

// ── Auto-fill published_at on publish toggle ─────────────────────────────

function initAutoPublishDate() {
  const checkbox = document.getElementById('newsIsPublished');
  const dateInput = document.getElementById('newsPublishedAt');

  if (!checkbox || !dateInput) return;

  checkbox.addEventListener('change', () => {
    if (checkbox.checked && !dateInput.value) {
      const now = new Date();
      const pad = n => String(n).padStart(2, '0');
      dateInput.value =
        now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) +
        'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    }
  });
}

// ── Auto-generate slug on submit if empty ────────────────────────────────

function initAutoSlugOnSubmit() {
  const form = document.querySelector('form[data-is-edit]');
  const titleInput = document.getElementById('newsTitle');
  const slugInput = document.getElementById('newsSlug');

  if (!form || !titleInput || !slugInput) return;

  form.addEventListener('submit', () => {
    if (!slugInput.value.trim() && titleInput.value.trim()) {
      slugInput.value = slugify(titleInput.value);
    }
  });
}

// ── Media picker ─────────────────────────────────────────────────────────

function initMediaPicker() {
  const imageInput = document.getElementById('newsImageUrl');
  const selectBtn = document.getElementById('newsMediaSelect');
  const uploadInput = document.getElementById('newsMediaUpload');
  const removeBtn = document.getElementById('newsImageRemove');
  const previewWrap = document.getElementById('newsImagePreview');
  const previewImg = document.getElementById('newsImagePreviewImg');
  const modalEl = document.getElementById('newsMediaModal');

  if (!imageInput || !modalEl) return;

  const form = document.querySelector('form[data-is-edit]');
  const basePath = form?.dataset.basePath || window.basePath || '';
  const namespace = form?.dataset.namespace || '';
  const apiFetch = window.apiFetch || ((path, opts) => fetch(basePath + path, opts));

  const gridEl = modalEl.querySelector('[data-role="media-grid"]');
  const emptyEl = modalEl.querySelector('[data-role="media-empty"]');
  const loadingEl = modalEl.querySelector('[data-role="media-loading"]');
  const searchInput = modalEl.querySelector('[data-role="media-search"]');
  const scopeSelect = modalEl.querySelector('[data-role="media-scope"]');

  const modal = typeof UIkit !== 'undefined' ? UIkit.modal(modalEl) : null;
  let searchTimer;

  // ── Preview helpers ──

  function updatePreview(url) {
    if (previewWrap && previewImg) {
      if (url) {
        previewImg.src = url;
        previewWrap.style.display = '';
      } else {
        previewImg.src = '';
        previewWrap.style.display = 'none';
      }
    }
  }

  imageInput.addEventListener('input', () => updatePreview(imageInput.value));

  if (removeBtn) {
    removeBtn.addEventListener('click', () => {
      imageInput.value = '';
      updatePreview('');
    });
  }

  // ── Select from value ──

  function selectImage(path) {
    imageInput.value = path;
    updatePreview(path);
    if (modal) modal.hide();
  }

  // ── Render media grid ──

  function isImage(name) {
    return /\.(png|jpe?g|webp|svg|gif)$/i.test(name);
  }

  function renderGrid(files) {
    if (!gridEl) return;
    gridEl.innerHTML = '';

    const images = (Array.isArray(files) ? files : []).filter(f => isImage(f.name || f.path || ''));

    if (images.length === 0) {
      if (emptyEl) emptyEl.hidden = false;
      return;
    }
    if (emptyEl) emptyEl.hidden = true;

    images.forEach(file => {
      const path = file.path || file.url || '';
      const name = file.name || path.split('/').pop();

      const col = document.createElement('div');
      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'uk-card uk-card-default uk-card-small uk-card-hover uk-padding-small uk-width-1-1';
      card.style.cssText = 'cursor:pointer; border:none; text-align:center;';
      card.dataset.path = path;

      const img = document.createElement('img');
      img.src = basePath + path;
      img.alt = name;
      img.style.cssText = 'max-height:80px; max-width:100%; object-fit:contain; display:block; margin:0 auto 4px;';
      img.loading = 'lazy';

      const label = document.createElement('div');
      label.className = 'uk-text-truncate uk-text-small';
      label.textContent = name;

      card.append(img, label);
      col.append(card);
      gridEl.append(col);
    });
  }

  // ── Load media from API ──

  async function loadMedia(search = '') {
    if (!gridEl) return;
    if (loadingEl) loadingEl.hidden = false;
    if (emptyEl) emptyEl.hidden = true;
    gridEl.innerHTML = '';

    const scope = scopeSelect ? scopeSelect.value : 'project';
    const params = new URLSearchParams({ scope, perPage: '200' });
    if (namespace && scope === 'project') params.set('namespace', namespace);
    if (search) params.set('search', search);

    try {
      const res = await apiFetch(`/admin/media/files?${params}`, {
        headers: { Accept: 'application/json' }
      });
      if (!res.ok) throw new Error('load failed');
      const data = await res.json().catch(() => ({}));
      renderGrid(Array.isArray(data.files) ? data.files : []);
    } catch (err) {
      console.error('Media load error:', err);
      renderGrid([]);
    } finally {
      if (loadingEl) loadingEl.hidden = true;
    }
  }

  // ── Grid click → select ──

  if (gridEl) {
    gridEl.addEventListener('click', e => {
      const card = e.target.closest('button[data-path]');
      if (card) selectImage(card.dataset.path);
    });
  }

  // ── Search with debounce ──

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => loadMedia(searchInput.value.trim()), 250);
    });
  }

  // ── Scope change ──

  if (scopeSelect) {
    scopeSelect.addEventListener('change', () => {
      const search = searchInput ? searchInput.value.trim() : '';
      loadMedia(search);
    });
  }

  // ── Open modal ──

  if (selectBtn) {
    selectBtn.addEventListener('click', () => {
      if (modal) {
        if (searchInput) searchInput.value = '';
        loadMedia('');
        modal.show();
      }
    });
  }

  // ── Direct upload ──

  if (uploadInput) {
    uploadInput.addEventListener('change', async () => {
      const file = uploadInput.files?.[0];
      if (!file) return;

      const formData = new FormData();
      formData.append('file', file);
      formData.append('scope', 'project');
      if (namespace) formData.append('namespace', namespace);

      // Add CSRF token
      const token = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || window.csrfToken || '';
      if (token) formData.append('_token', token);

      try {
        const res = await apiFetch('/admin/media/upload', {
          method: 'POST',
          body: formData,
        });
        if (!res.ok) {
          const err = await res.json().catch(() => ({}));
          throw new Error(err.error || 'Upload fehlgeschlagen');
        }
        const data = await res.json().catch(() => ({}));
        const path = data.path || data.url || '';
        if (path) {
          selectImage(path);
        }
      } catch (err) {
        console.error('Upload error:', err);
        const notify = typeof window.notify === 'function' ? window.notify : msg => window.alert(msg);
        notify(err.message || 'Upload fehlgeschlagen', 'danger');
      } finally {
        uploadInput.value = '';
      }
    });
  }
}

// ── Init ──────────────────────────────────────────────────────────────────

initSlugSuggestion();
initContentEditor();
initAdvancedToggle();
initAutoPublishDate();
initAutoSlugOnSubmit();
initMediaPicker();
