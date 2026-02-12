/**
 * Footer Block Editor – Enhanced 3-column editor
 * Phase 1: Layout persistence
 * Phase 2: Drag-and-drop reordering (UIKit sortable)
 * Phase 3: Live preview panel
 * Phase 4: TipTap rich text editor for text/html blocks
 * Phase 5: Inline editing in columns
 */

import { Editor } from './vendor/tiptap/core.esm.js';
import StarterKit from './vendor/tiptap/starter-kit.esm.js';

const SLOTS = ['footer_1', 'footer_2', 'footer_3'];

const TYPE_ICONS = {
  menu: { abbr: 'M', cls: 'block-card__icon--menu' },
  text: { abbr: 'T', cls: 'block-card__icon--text' },
  social: { abbr: 'S', cls: 'block-card__icon--social' },
  contact: { abbr: 'C', cls: 'block-card__icon--contact' },
  newsletter: { abbr: 'N', cls: 'block-card__icon--newsletter' },
  html: { abbr: 'H', cls: 'block-card__icon--html' },
};

let blocksBySlot = { footer_1: [], footer_2: [], footer_3: [] };
let currentNamespace = 'default';
let currentLocale = 'de';
let currentLayout = 'equal';
let editingBlockId = null;
let editingSlot = null;
let previewVisible = true;
let activeEditors = [];
let currentlyEditingInline = null;

// ── Initialization ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initializeSelectors();
  initializeLayoutSelector();
  initializePresets();
  initializePreviewToggle();
  setupEventListeners();
  loadAllSlots();
});

function initializeSelectors() {
  const nsSelect = document.querySelector('[data-namespace-select]');
  const localeSelect = document.querySelector('[data-locale-select]');

  if (nsSelect) {
    currentNamespace = nsSelect.value;
    nsSelect.addEventListener('change', (e) => {
      currentNamespace = e.target.value;
      loadAllSlots();
    });
  }
  if (localeSelect) {
    currentLocale = localeSelect.value;
    localeSelect.addEventListener('change', (e) => {
      currentLocale = e.target.value;
      loadAllSlots();
    });
  }
}

function initializeLayoutSelector() {
  const buttons = document.querySelectorAll('[data-layout-selector] .layout-option');
  const serverLayout = window.templateData?.footerLayout || 'equal';
  currentLayout = serverLayout;

  // Set active button from server value
  buttons.forEach((btn) => {
    btn.classList.toggle('active', btn.dataset.layout === serverLayout);
  });

  buttons.forEach((btn) => {
    btn.addEventListener('click', async () => {
      buttons.forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      currentLayout = btn.dataset.layout;
      renderPreview();

      // Persist layout to server
      try {
        await fetch('/admin/footer-blocks/layout', {
          method: 'PUT',
          headers: csrfHeaders(),
          body: JSON.stringify({ namespace: currentNamespace, layout: currentLayout }),
        });
        UIkit.notification('Layout gespeichert', { status: 'success', pos: 'top-right', timeout: 1500 });
      } catch (e) {
        console.error('Error saving layout:', e);
      }
    });
  });
}

function initializePresets() {
  document.querySelectorAll('[data-preset]').forEach((btn) => {
    btn.addEventListener('click', () => applyPreset(btn.dataset.preset));
  });
}

function initializePreviewToggle() {
  const toggle = document.querySelector('[data-preview-toggle]');
  const preview = document.querySelector('[data-footer-preview]');
  if (toggle && preview) {
    toggle.addEventListener('click', () => {
      previewVisible = !previewVisible;
      preview.classList.toggle('preview-hidden', !previewVisible);
    });
  }
}

function setupEventListeners() {
  // Per-column add buttons
  SLOTS.forEach((slot) => {
    const btn = document.querySelector(`[data-add-block-btn="${slot}"]`);
    if (btn) {
      btn.addEventListener('click', () => openBlockEditor(null, slot));
    }
  });

  // Form submit (modal — only for new blocks)
  const form = document.querySelector('[data-block-form]');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      saveBlockFromModal();
    });
  }

  // Type radio change in modal
  document.querySelectorAll('[data-type-grid] input[type="radio"]').forEach((radio) => {
    radio.addEventListener('change', (e) => {
      renderModalContentFields(e.target.value);
    });
  });

  // Modal close cleanup
  if (typeof UIkit !== 'undefined') {
    UIkit.util.on('#blockEditorModal', 'hidden', () => {
      destroyAllEditors();
    });
  }

  // Drag-and-drop: setup after initial load
  setupSortableListeners();
}

// ── Drag-and-Drop (Phase 2) ─────────────────────────────────────
function setupSortableListeners() {
  SLOTS.forEach((slot) => {
    const container = document.querySelector(`[data-blocks-container="${slot}"]`);
    if (container && typeof UIkit !== 'undefined') {
      UIkit.util.on(container, 'moved', () => persistSlotOrder(slot));
      UIkit.util.on(container, 'added', (e) => handleCrossColumnDrop(e, slot));
    }
  });
}

async function persistSlotOrder(slot) {
  const container = document.querySelector(`[data-blocks-container="${slot}"]`);
  if (!container) return;

  const cards = container.querySelectorAll('[data-block-card]');
  const orderedIds = Array.from(cards).map((c) => parseInt(c.dataset.blockCard));

  // Update local state
  const allBlocks = SLOTS.flatMap((s) => blocksBySlot[s]);
  blocksBySlot[slot] = orderedIds.map((id) => allBlocks.find((b) => b.id === id)).filter(Boolean);

  try {
    await fetch('/admin/footer-blocks/reorder', {
      method: 'POST',
      headers: csrfHeaders(),
      body: JSON.stringify({
        namespace: currentNamespace,
        slot,
        locale: currentLocale,
        orderedIds,
      }),
    });
    renderPreview();
  } catch (e) {
    console.error('Error reordering blocks:', e);
  }
}

async function handleCrossColumnDrop(e, targetSlot) {
  const movedCard = e.detail?.[1] || e.target?.querySelector?.('[data-block-card]');
  if (!movedCard) {
    // Fallback: re-read all cards in target slot
    await persistSlotOrder(targetSlot);
    return;
  }

  const blockId = parseInt(movedCard.dataset.blockCard);
  if (!blockId) return;

  // Find original block data
  let block = null;
  for (const s of SLOTS) {
    block = blocksBySlot[s].find((b) => b.id === blockId);
    if (block) break;
  }

  if (block && block.slot !== targetSlot) {
    // Move block to new slot via API
    try {
      await fetch(`/admin/footer-blocks/${blockId}`, {
        method: 'PUT',
        headers: csrfHeaders(),
        body: JSON.stringify({
          type: block.type,
          content: block.content,
          position: 0,
          isActive: block.isActive,
          slot: targetSlot,
        }),
      });

      // Remove from old slot
      const oldSlot = block.slot;
      blocksBySlot[oldSlot] = blocksBySlot[oldSlot].filter((b) => b.id !== blockId);
      block.slot = targetSlot;
    } catch (e) {
      console.error('Error moving block:', e);
    }
  }

  await persistSlotOrder(targetSlot);

  // Also reorder the source slots
  for (const s of SLOTS) {
    if (s !== targetSlot) {
      const container = document.querySelector(`[data-blocks-container="${s}"]`);
      if (container) {
        const cards = container.querySelectorAll('[data-block-card]');
        if (cards.length > 0) {
          await persistSlotOrder(s);
        }
      }
    }
  }
}

// ── Load data ──────────────────────────────────────────────────
async function loadAllSlots() {
  const promises = SLOTS.map((slot) => loadSlot(slot));
  await Promise.all(promises);
  renderPreview();
}

async function loadSlot(slot) {
  const container = document.querySelector(`[data-blocks-container="${slot}"]`);
  if (!container) return;

  container.innerHTML =
    '<div class="uk-text-center uk-text-muted uk-padding-small"><span uk-spinner="ratio: 0.5"></span></div>';

  try {
    const response = await fetch(
      `/admin/footer-blocks?namespace=${encodeURIComponent(currentNamespace)}&slot=${encodeURIComponent(slot)}&locale=${encodeURIComponent(currentLocale)}`
    );
    if (!response.ok) throw new Error('Failed to load blocks');

    const data = await response.json();
    blocksBySlot[slot] = data.blocks || [];
    renderSlot(slot);
  } catch (error) {
    console.error(`Error loading ${slot}:`, error);
    container.innerHTML = `<div class="uk-alert uk-alert-danger uk-alert-small">${escapeHtml(error.message)}</div>`;
  }
}

// ── Render ──────────────────────────────────────────────────────
function renderSlot(slot) {
  const container = document.querySelector(`[data-blocks-container="${slot}"]`);
  if (!container) return;

  const blocks = blocksBySlot[slot];
  if (blocks.length === 0) {
    container.innerHTML =
      '<div class="footer-editor-col__empty">Keine Blocks. Klicke "+ Block" um einen hinzuzufuegen.</div>';
    return;
  }

  container.innerHTML = blocks.map((block) => renderBlockCard(block)).join('');
}

function renderBlockCard(block) {
  const icon = TYPE_ICONS[block.type] || { abbr: '?', cls: '' };
  const title = getBlockTitle(block);
  const meta = getBlockSummary(block);
  const inactiveClass = block.isActive ? '' : ' block-card--inactive';

  return `
    <div class="block-card${inactiveClass}" data-block-card="${block.id}">
      <div class="block-card__summary">
        <div class="block-card__drag" title="Ziehen zum Verschieben">
          <svg width="12" height="16" viewBox="0 0 12 16"><circle cx="3" cy="2" r="1.5" fill="currentColor"/><circle cx="9" cy="2" r="1.5" fill="currentColor"/><circle cx="3" cy="8" r="1.5" fill="currentColor"/><circle cx="9" cy="8" r="1.5" fill="currentColor"/><circle cx="3" cy="14" r="1.5" fill="currentColor"/><circle cx="9" cy="14" r="1.5" fill="currentColor"/></svg>
        </div>
        <div class="block-card__icon ${icon.cls}">${icon.abbr}</div>
        <div class="block-card__info">
          <div class="block-card__title">${escapeHtml(title)}</div>
          <div class="block-card__meta">${escapeHtml(meta)}</div>
        </div>
        <div class="block-card__actions">
          <button type="button" data-edit-inline="${block.id}" title="Bearbeiten">
            <span uk-icon="icon: pencil; ratio: 0.7"></span>
          </button>
          <button type="button" class="btn-delete" data-delete-block="${block.id}" title="Loeschen">
            <span uk-icon="icon: trash; ratio: 0.7"></span>
          </button>
        </div>
      </div>
      <div class="block-card__edit-area" data-block-edit="${block.id}" hidden></div>
    </div>`;
}

// Attach event listeners via delegation after render
document.addEventListener('click', (e) => {
  const editBtn = e.target.closest('[data-edit-inline]');
  if (editBtn) {
    e.preventDefault();
    const blockId = parseInt(editBtn.dataset.editInline);
    toggleInlineEditor(blockId);
    return;
  }

  const deleteBtn = e.target.closest('[data-delete-block]');
  if (deleteBtn) {
    e.preventDefault();
    const blockId = parseInt(deleteBtn.dataset.deleteBlock);
    deleteBlock(blockId);
    return;
  }
});

function getBlockTitle(block) {
  switch (block.type) {
    case 'menu': return block.content.title || 'Menu';
    case 'text': return block.content.title || 'Text';
    case 'social': return block.content.title || 'Social Media';
    case 'contact': return block.content.title || 'Kontakt';
    case 'newsletter': return block.content.title || 'Newsletter';
    case 'html': return block.content.title || 'Custom HTML';
    default: return 'Block';
  }
}

function getBlockSummary(block) {
  switch (block.type) {
    case 'menu': {
      const menuDef = (window.templateData?.menuDefinitions || []).find(
        (m) => m.id === block.content.menuId
      );
      return menuDef ? menuDef.label : 'Kein Menu gewaehlt';
    }
    case 'text': {
      const text = block.content.text || '';
      const plain = text.replace(/<[^>]*>/g, '');
      return plain.substring(0, 50) + (plain.length > 50 ? '...' : '');
    }
    case 'social': {
      const platforms = Object.entries(block.content.links || {})
        .filter(([, v]) => v)
        .map(([k]) => k);
      return platforms.length > 0 ? platforms.join(', ') : 'Keine Links';
    }
    case 'contact':
      return [block.content.email, block.content.phone].filter(Boolean).join(', ') || 'Keine Angaben';
    case 'newsletter':
      return block.content.description || 'Newsletter-Anmeldung';
    case 'html':
      return 'Eigener HTML-Inhalt';
    default:
      return '';
  }
}

// ── Inline Editing (Phase 5) ────────────────────────────────────
function toggleInlineEditor(blockId) {
  // Close any other open inline editor
  if (currentlyEditingInline && currentlyEditingInline !== blockId) {
    closeInlineEditor(currentlyEditingInline);
  }

  const editArea = document.querySelector(`[data-block-edit="${blockId}"]`);
  const card = document.querySelector(`[data-block-card="${blockId}"]`);
  if (!editArea || !card) return;

  if (!editArea.hidden) {
    closeInlineEditor(blockId);
    return;
  }

  // Find block data
  let block = null;
  for (const s of SLOTS) {
    block = blocksBySlot[s].find((b) => b.id === blockId);
    if (block) break;
  }
  if (!block) return;

  currentlyEditingInline = blockId;
  card.classList.add('block-card--editing');
  editArea.hidden = false;
  populateInlineEditor(block, editArea);
}

function closeInlineEditor(blockId) {
  const editArea = document.querySelector(`[data-block-edit="${blockId}"]`);
  const card = document.querySelector(`[data-block-card="${blockId}"]`);
  if (editArea) editArea.hidden = true;
  if (card) card.classList.remove('block-card--editing');
  destroyAllEditors();
  if (currentlyEditingInline === blockId) currentlyEditingInline = null;
}

function populateInlineEditor(block, container) {
  destroyAllEditors();
  const content = block.content || {};

  let fieldsHtml = buildContentFieldsHtml(block.type, content);

  container.innerHTML = `
    ${fieldsHtml}
    <div class="uk-margin-small-top">
      <label class="uk-form-label" style="font-size:0.75rem">
        <input type="checkbox" class="uk-checkbox" data-inline-active ${block.isActive ? 'checked' : ''}>
        Aktiv
      </label>
    </div>
    <div class="inline-edit-actions">
      <button type="button" class="uk-button uk-button-primary uk-button-small" data-inline-save="${block.id}">
        Speichern
      </button>
      <button type="button" class="uk-button uk-button-default uk-button-small" data-inline-cancel="${block.id}">
        Abbrechen
      </button>
    </div>
  `;

  // Initialize TipTap for text/html fields
  initializeRichEditors(container, block.type, content);

  // Save/Cancel buttons
  container.querySelector(`[data-inline-save="${block.id}"]`)?.addEventListener('click', () => {
    saveInlineBlock(block, container);
  });
  container.querySelector(`[data-inline-cancel="${block.id}"]`)?.addEventListener('click', () => {
    closeInlineEditor(block.id);
  });
}

async function saveInlineBlock(block, container) {
  const type = block.type;
  const content = extractContentFromContainer(type, container);
  const isActive = container.querySelector('[data-inline-active]')?.checked ?? true;

  const payload = {
    type,
    content,
    isActive,
    position: block.position || 0,
  };

  try {
    const response = await fetch(`/admin/footer-blocks/${block.id}`, {
      method: 'PUT',
      headers: csrfHeaders(),
      body: JSON.stringify(payload),
    });

    if (!response.ok) {
      const text = await response.text();
      const error = text ? JSON.parse(text) : {};
      throw new Error(error.error || `Speichern fehlgeschlagen (${response.status})`);
    }

    closeInlineEditor(block.id);
    const slot = block.slot || SLOTS.find((s) => blocksBySlot[s].some((b) => b.id === block.id));
    await loadSlot(slot);
    renderPreview();
    UIkit.notification('Block gespeichert', { status: 'success', pos: 'top-right', timeout: 1500 });
  } catch (error) {
    console.error('Error saving block:', error);
    UIkit.notification(error.message, { status: 'danger', pos: 'top-right' });
  }
}

function extractContentFromContainer(type, container) {
  const content = {};
  const val = (name) => container.querySelector(`[name="${name}"]`)?.value || '';

  switch (type) {
    case 'menu':
      content.title = val('content_title');
      content.menuId = parseInt(val('content_menuId')) || null;
      break;
    case 'text':
      content.title = val('content_title');
      content.text = val('content_text');
      break;
    case 'social':
      content.title = val('content_title');
      content.links = {
        facebook: val('content_facebook'),
        twitter: val('content_twitter'),
        linkedin: val('content_linkedin'),
        instagram: val('content_instagram'),
      };
      break;
    case 'contact':
      content.title = val('content_title');
      content.email = val('content_email');
      content.phone = val('content_phone');
      content.address = val('content_address');
      break;
    case 'newsletter':
      content.title = val('content_title');
      content.description = val('content_description');
      content.buttonText = val('content_buttonText');
      content.actionUrl = val('content_actionUrl');
      break;
    case 'html':
      content.title = val('content_title');
      content.html = val('content_html');
      break;
  }

  return content;
}

// ── Block editor modal (for NEW blocks only) ────────────────────
function openBlockEditor(blockId = null, slot = null) {
  editingBlockId = null;
  editingSlot = slot;
  const form = document.querySelector('[data-block-form]');
  const titleEl = document.querySelector('[data-modal-title]');

  titleEl.textContent = 'Block hinzufuegen';
  form.reset();
  form.querySelector('[data-target-slot]').value = slot || 'footer_1';
  form.querySelector('[data-active-checkbox]').checked = true;
  const firstRadio = form.querySelector('input[name="type"][value="menu"]');
  if (firstRadio) firstRadio.checked = true;
  renderModalContentFields('menu');

  UIkit.modal('#blockEditorModal').show();
}

function renderModalContentFields(type, existingContent = {}) {
  const container = document.querySelector('[data-content-fields]');
  if (!container) return;

  destroyAllEditors();
  container.innerHTML = buildContentFieldsHtml(type, existingContent);
  initializeRichEditors(container, type, existingContent);
}

async function saveBlockFromModal() {
  const form = document.querySelector('[data-block-form]');
  const formData = new FormData(form);
  const type = formData.get('type');
  const targetSlot = formData.get('targetSlot') || editingSlot || 'footer_1';

  const container = document.querySelector('[data-content-fields]');
  const content = extractContentFromContainer(type, container);

  const payload = {
    type,
    content,
    isActive: formData.get('isActive') === 'on',
    position: blocksBySlot[targetSlot]?.length || 0,
    namespace: currentNamespace,
    slot: targetSlot,
    locale: currentLocale,
  };

  try {
    const response = await fetch('/admin/footer-blocks', {
      method: 'POST',
      headers: csrfHeaders(),
      body: JSON.stringify(payload),
    });

    if (!response.ok) {
      const text = await response.text();
      const error = text ? JSON.parse(text) : {};
      throw new Error(error.error || `Speichern fehlgeschlagen (${response.status})`);
    }

    UIkit.modal('#blockEditorModal').hide();
    await loadSlot(targetSlot);
    renderPreview();
    UIkit.notification('Block erstellt', { status: 'success', pos: 'top-right' });
  } catch (error) {
    console.error('Error saving block:', error);
    UIkit.notification(error.message, { status: 'danger', pos: 'top-right' });
  }
}

// ── Content field builder (shared by modal + inline) ────────────
function buildContentFieldsHtml(type, existingContent = {}) {
  switch (type) {
    case 'menu':
      return `
        <div class="uk-margin-small">
          <label class="uk-form-label">Titel</label>
          <input type="text" name="content_title" class="uk-input uk-form-small" value="${escapeAttr(existingContent.title || '')}" placeholder="z.B. Navigation, Links">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">Menu</label>
          <select name="content_menuId" class="uk-select uk-form-small">
            <option value="">Menu waehlen...</option>
            ${(window.templateData?.menuDefinitions || [])
              .map(
                (menu) =>
                  `<option value="${menu.id}" ${existingContent.menuId == menu.id ? 'selected' : ''}>${escapeHtml(menu.label)} (${escapeHtml(menu.locale)})</option>`
              )
              .join('')}
          </select>
        </div>`;

    case 'text':
      return `
        <div class="uk-margin-small">
          <label class="uk-form-label">Titel</label>
          <input type="text" name="content_title" class="uk-input uk-form-small" value="${escapeAttr(existingContent.title || '')}" placeholder="z.B. Ueber uns">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">Text</label>
          <div data-richtext-editor="text"></div>
          <input type="hidden" name="content_text" value="${escapeAttr(existingContent.text || '')}">
        </div>`;

    case 'social':
      return `
        <div class="uk-margin-small">
          <label class="uk-form-label">Titel</label>
          <input type="text" name="content_title" class="uk-input uk-form-small" value="${escapeAttr(existingContent.title || '')}" placeholder="Folge uns">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">Facebook</label>
          <input type="url" name="content_facebook" class="uk-input uk-form-small" value="${escapeAttr(existingContent.links?.facebook || '')}" placeholder="https://facebook.com/...">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">Twitter / X</label>
          <input type="url" name="content_twitter" class="uk-input uk-form-small" value="${escapeAttr(existingContent.links?.twitter || '')}" placeholder="https://twitter.com/...">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">LinkedIn</label>
          <input type="url" name="content_linkedin" class="uk-input uk-form-small" value="${escapeAttr(existingContent.links?.linkedin || '')}" placeholder="https://linkedin.com/...">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">Instagram</label>
          <input type="url" name="content_instagram" class="uk-input uk-form-small" value="${escapeAttr(existingContent.links?.instagram || '')}" placeholder="https://instagram.com/...">
        </div>`;

    case 'contact':
      return `
        <div class="uk-margin-small">
          <label class="uk-form-label">Titel</label>
          <input type="text" name="content_title" class="uk-input uk-form-small" value="${escapeAttr(existingContent.title || '')}" placeholder="Kontakt">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">E-Mail</label>
          <input type="email" name="content_email" class="uk-input uk-form-small" value="${escapeAttr(existingContent.email || '')}" placeholder="info@example.com">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">Telefon</label>
          <input type="tel" name="content_phone" class="uk-input uk-form-small" value="${escapeAttr(existingContent.phone || '')}" placeholder="+49 123 456789">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">Adresse</label>
          <textarea name="content_address" class="uk-textarea uk-form-small" rows="2">${escapeHtml(existingContent.address || '')}</textarea>
        </div>`;

    case 'newsletter':
      return `
        <div class="uk-margin-small">
          <label class="uk-form-label">Titel</label>
          <input type="text" name="content_title" class="uk-input uk-form-small" value="${escapeAttr(existingContent.title || '')}" placeholder="Newsletter">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">Beschreibung</label>
          <textarea name="content_description" class="uk-textarea uk-form-small" rows="2">${escapeHtml(existingContent.description || '')}</textarea>
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">Button-Text</label>
          <input type="text" name="content_buttonText" class="uk-input uk-form-small" value="${escapeAttr(existingContent.buttonText || '')}" placeholder="Abonnieren">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">Ziel-URL</label>
          <input type="url" name="content_actionUrl" class="uk-input uk-form-small" value="${escapeAttr(existingContent.actionUrl || '')}" placeholder="/newsletter/subscribe">
        </div>`;

    case 'html':
      return `
        <div class="uk-margin-small">
          <label class="uk-form-label">Titel (intern)</label>
          <input type="text" name="content_title" class="uk-input uk-form-small" value="${escapeAttr(existingContent.title || '')}" placeholder="Custom Block">
        </div>
        <div class="uk-margin-small">
          <label class="uk-form-label">HTML-Inhalt</label>
          <div class="html-source-toggle">
            <button type="button" class="active" data-html-mode="visual">Visual</button>
            <button type="button" data-html-mode="source">Quellcode</button>
          </div>
          <div data-richtext-editor="html"></div>
          <textarea name="content_html" class="uk-textarea uk-form-small" rows="8" style="display:none">${escapeHtml(existingContent.html || '')}</textarea>
        </div>`;

    default:
      return '';
  }
}

// ── TipTap Rich Text Editor (Phase 4) ───────────────────────────
function initializeRichEditors(container, type, existingContent = {}) {
  if (type === 'text') {
    const editorEl = container.querySelector('[data-richtext-editor="text"]');
    const hiddenInput = container.querySelector('[name="content_text"]');
    if (editorEl && hiddenInput) {
      createRichEditor(editorEl, existingContent.text || '', (html) => {
        hiddenInput.value = html;
      });
    }
  }

  if (type === 'html') {
    const editorEl = container.querySelector('[data-richtext-editor="html"]');
    const textarea = container.querySelector('[name="content_html"]');
    if (editorEl && textarea) {
      const editor = createRichEditor(editorEl, existingContent.html || '', (html) => {
        textarea.value = html;
      });

      // Source toggle
      const toggleBtns = container.querySelectorAll('[data-html-mode]');
      toggleBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
          toggleBtns.forEach((b) => b.classList.remove('active'));
          btn.classList.add('active');
          const mode = btn.dataset.htmlMode;

          if (mode === 'source') {
            textarea.value = editor.getHTML();
            editorEl.style.display = 'none';
            textarea.style.display = '';
          } else {
            editor.commands.setContent(textarea.value);
            editorEl.style.display = '';
            textarea.style.display = 'none';
          }
        });
      });
    }
  }
}

function createRichEditor(container, initialValue, onUpdate) {
  // Build toolbar + editor wrapper
  const wrapper = document.createElement('div');
  wrapper.className = 'footer-richtext-container';

  const toolbar = document.createElement('div');
  toolbar.className = 'footer-richtext-toolbar';
  toolbar.innerHTML = `
    <button type="button" data-cmd="bold" title="Fett"><b>B</b></button>
    <button type="button" data-cmd="italic" title="Kursiv"><i>I</i></button>
    <button type="button" data-cmd="bulletList" title="Aufzaehlung">&#8226;</button>
    <button type="button" data-cmd="orderedList" title="Nummerierung">1.</button>
    <button type="button" data-cmd="heading" title="Ueberschrift">H</button>
  `;

  const editorContent = document.createElement('div');

  wrapper.appendChild(toolbar);
  wrapper.appendChild(editorContent);
  container.appendChild(wrapper);

  const editor = new Editor({
    element: editorContent,
    content: initialValue || '',
    extensions: [StarterKit],
    editorProps: {
      attributes: {
        class: 'footer-richtext-editor',
        spellcheck: 'true',
      },
    },
    onUpdate: ({ editor: ed }) => {
      onUpdate(ed.getHTML());
      updateToolbarState(toolbar, ed);
    },
  });

  // Toolbar commands
  toolbar.querySelectorAll('[data-cmd]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const cmd = btn.dataset.cmd;
      switch (cmd) {
        case 'bold': editor.chain().focus().toggleBold().run(); break;
        case 'italic': editor.chain().focus().toggleItalic().run(); break;
        case 'bulletList': editor.chain().focus().toggleBulletList().run(); break;
        case 'orderedList': editor.chain().focus().toggleOrderedList().run(); break;
        case 'heading': editor.chain().focus().toggleHeading({ level: 4 }).run(); break;
      }
      updateToolbarState(toolbar, editor);
    });
  });

  updateToolbarState(toolbar, editor);
  activeEditors.push(editor);
  return editor;
}

function updateToolbarState(toolbar, editor) {
  toolbar.querySelectorAll('[data-cmd]').forEach((btn) => {
    const cmd = btn.dataset.cmd;
    let active = false;
    switch (cmd) {
      case 'bold': active = editor.isActive('bold'); break;
      case 'italic': active = editor.isActive('italic'); break;
      case 'bulletList': active = editor.isActive('bulletList'); break;
      case 'orderedList': active = editor.isActive('orderedList'); break;
      case 'heading': active = editor.isActive('heading', { level: 4 }); break;
    }
    btn.classList.toggle('is-active', active);
  });
}

function destroyAllEditors() {
  activeEditors.forEach((e) => {
    try { e.destroy(); } catch (err) { /* ignore */ }
  });
  activeEditors = [];
}

// ── Live Preview (Phase 3) ──────────────────────────────────────
function renderPreview() {
  const preview = document.querySelector('[data-footer-preview]');
  if (!preview || !previewVisible) return;

  const hasBlocks = SLOTS.some((s) => blocksBySlot[s].length > 0);
  if (!hasBlocks) {
    preview.innerHTML = '<div class="footer-preview-empty">Noch keine Blocks vorhanden</div>';
    return;
  }

  let columnsHtml = '';
  for (const slot of SLOTS) {
    const blocks = blocksBySlot[slot];
    const activeBlocks = blocks.filter((b) => b.isActive);
    if (activeBlocks.length === 0) continue;

    columnsHtml += '<div class="cms-footer__column">';
    for (const block of activeBlocks) {
      columnsHtml += renderBlockPreview(block);
    }
    columnsHtml += '</div>';
  }

  preview.innerHTML = `
    <div class="cms-footer">
      <div class="cms-footer__section">
        <div class="cms-footer__blocks" data-layout="${currentLayout}">
          ${columnsHtml}
        </div>
      </div>
    </div>`;
}

function renderBlockPreview(block) {
  const c = block.content || {};

  switch (block.type) {
    case 'menu': {
      const menuDef = (window.templateData?.menuDefinitions || []).find(
        (m) => m.id === c.menuId
      );
      const menuName = menuDef ? menuDef.label : 'Menu';
      return `
        <div class="footer-block footer-block--menu">
          ${c.title ? `<h4 class="footer-block__title">${escapeHtml(c.title)}</h4>` : ''}
          <ul class="footer-block__menu" style="list-style:none;padding:0;margin:0">
            <li style="margin-bottom:0.4rem;color:#6b7280;font-size:0.85rem">[${escapeHtml(menuName)}]</li>
          </ul>
        </div>`;
    }

    case 'text':
      return `
        <div class="footer-block footer-block--text">
          ${c.title ? `<h4 class="footer-block__title">${escapeHtml(c.title)}</h4>` : ''}
          <div class="footer-block__text">${c.text || ''}</div>
        </div>`;

    case 'social': {
      const links = c.links || {};
      const icons = ['facebook', 'twitter', 'linkedin', 'instagram'].filter((k) => links[k]);
      return `
        <div class="footer-block footer-block--social">
          ${c.title ? `<h4 class="footer-block__title">${escapeHtml(c.title)}</h4>` : ''}
          <div class="footer-block__social-links">
            ${icons.map((k) => `<a href="#" class="footer-social-link" style="pointer-events:none" title="${escapeAttr(k)}"><span uk-icon="icon: ${k}"></span></a>`).join('')}
            ${icons.length === 0 ? '<span style="color:#adb5bd;font-size:0.8rem">[Social Links]</span>' : ''}
          </div>
        </div>`;
    }

    case 'contact':
      return `
        <div class="footer-block footer-block--contact">
          ${c.title ? `<h4 class="footer-block__title">${escapeHtml(c.title)}</h4>` : ''}
          <div class="footer-block__contact">
            ${c.email ? `<div class="footer-contact-item"><span uk-icon="icon: mail"></span><span>${escapeHtml(c.email)}</span></div>` : ''}
            ${c.phone ? `<div class="footer-contact-item"><span uk-icon="icon: phone"></span><span>${escapeHtml(c.phone)}</span></div>` : ''}
            ${c.address ? `<div class="footer-contact-item"><span uk-icon="icon: location"></span><div>${escapeHtml(c.address).replace(/\n/g, '<br>')}</div></div>` : ''}
          </div>
        </div>`;

    case 'newsletter':
      return `
        <div class="footer-block footer-block--newsletter">
          ${c.title ? `<h4 class="footer-block__title">${escapeHtml(c.title)}</h4>` : ''}
          ${c.description ? `<p class="footer-block__description">${escapeHtml(c.description)}</p>` : ''}
          <div class="footer-newsletter-form">
            <div class="uk-inline uk-width-1-1" style="pointer-events:none">
              <input type="email" class="uk-input" placeholder="Email" disabled>
              <button class="uk-button uk-button-primary" disabled>${escapeHtml(c.buttonText || 'Abonnieren')}</button>
            </div>
          </div>
        </div>`;

    case 'html':
      return `
        <div class="footer-block footer-block--html">${c.html || '<span style="color:#adb5bd">[HTML]</span>'}</div>`;

    default:
      return '';
  }
}

// ── Delete ──────────────────────────────────────────────────────
async function deleteBlock(id) {
  if (!confirm('Block wirklich loeschen?')) return;

  try {
    const response = await fetch(`/admin/footer-blocks/${id}`, { method: 'DELETE', headers: csrfHeaders() });
    if (!response.ok) throw new Error('Loeschen fehlgeschlagen');

    const targetSlot = SLOTS.find((s) => blocksBySlot[s].some((b) => b.id === id)) || 'footer_1';
    await loadSlot(targetSlot);
    renderPreview();
    UIkit.notification('Block geloescht', { status: 'success', pos: 'top-right' });
  } catch (error) {
    console.error('Error deleting block:', error);
    UIkit.notification(error.message, { status: 'danger', pos: 'top-right' });
  }
}

// ── Presets ─────────────────────────────────────────────────────
async function applyPreset(preset) {
  const hasBlocks = SLOTS.some((s) => blocksBySlot[s].length > 0);
  if (hasBlocks) {
    if (!confirm('Vorhandene Blocks werden durch die Vorlage ersetzt. Fortfahren?')) return;

    for (const slot of SLOTS) {
      for (const block of blocksBySlot[slot]) {
        try {
          await fetch(`/admin/footer-blocks/${block.id}`, { method: 'DELETE', headers: csrfHeaders() });
        } catch (e) {
          // continue
        }
      }
    }
  }

  const presetBlocks = getPresetBlocks(preset);
  for (const item of presetBlocks) {
    try {
      await fetch('/admin/footer-blocks', {
        method: 'POST',
        headers: csrfHeaders(),
        body: JSON.stringify({
          namespace: currentNamespace,
          slot: item.slot,
          locale: currentLocale,
          type: item.type,
          content: item.content,
          isActive: true,
          position: item.position,
        }),
      });
    } catch (e) {
      console.error('Error creating preset block:', e);
    }
  }

  // Set and persist layout
  const presetLayout = preset === 'contact-focus' ? 'brand-left' : preset === 'minimal' ? 'centered' : 'equal';
  const layoutBtns = document.querySelectorAll('[data-layout-selector] .layout-option');
  layoutBtns.forEach((btn) => {
    btn.classList.toggle('active', btn.dataset.layout === presetLayout);
  });
  currentLayout = presetLayout;

  try {
    await fetch('/admin/footer-blocks/layout', {
      method: 'PUT',
      headers: csrfHeaders(),
      body: JSON.stringify({ namespace: currentNamespace, layout: currentLayout }),
    });
  } catch (e) {
    console.error('Error saving preset layout:', e);
  }

  await loadAllSlots();
  UIkit.notification('Vorlage angewendet', { status: 'success', pos: 'top-right' });
}

function getPresetBlocks(preset) {
  switch (preset) {
    case 'business':
      return [
        { slot: 'footer_1', type: 'text', position: 0, content: { title: 'Unternehmen', text: '<p>Kurze Beschreibung Ihres Unternehmens. Bearbeiten Sie diesen Text im Footer-Editor.</p>' } },
        { slot: 'footer_1', type: 'social', position: 1, content: { title: '', links: { facebook: '', twitter: '', linkedin: '', instagram: '' } } },
        { slot: 'footer_2', type: 'menu', position: 0, content: { title: 'Navigation', menuId: null } },
        { slot: 'footer_3', type: 'contact', position: 0, content: { title: 'Kontakt', email: 'info@example.com', phone: '+49 123 456789', address: 'Musterstrasse 1\n12345 Musterstadt' } },
      ];
    case 'minimal':
      return [
        { slot: 'footer_1', type: 'text', position: 0, content: { title: '', text: '<p>&copy; 2026 Ihr Unternehmen. Alle Rechte vorbehalten.</p>' } },
      ];
    case 'contact-focus':
      return [
        { slot: 'footer_1', type: 'contact', position: 0, content: { title: 'Kontakt', email: 'info@example.com', phone: '+49 123 456789', address: 'Musterstrasse 1\n12345 Musterstadt' } },
        { slot: 'footer_1', type: 'social', position: 1, content: { title: 'Social Media', links: { facebook: '', twitter: '', linkedin: '', instagram: '' } } },
        { slot: 'footer_2', type: 'menu', position: 0, content: { title: 'Links', menuId: null } },
        { slot: 'footer_3', type: 'newsletter', position: 0, content: { title: 'Newsletter', description: 'Bleiben Sie informiert', buttonText: 'Abonnieren', actionUrl: '/newsletter/subscribe' } },
      ];
    default:
      return [];
  }
}

// ── Helpers ─────────────────────────────────────────────────────
function csrfHeaders() {
  return {
    'Content-Type': 'application/json',
    'X-CSRF-Token': window.csrfToken || window.templateData?.csrfToken || '',
  };
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function escapeAttr(str) {
  return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
