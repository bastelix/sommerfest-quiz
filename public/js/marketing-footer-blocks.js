/**
 * Footer Block Editor – Unified 3-column editor
 * Manages footer content blocks across all three slots simultaneously.
 */

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

// ── Initialization ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initializeSelectors();
  initializeLayoutSelector();
  initializePresets();
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
  buttons.forEach((btn) => {
    btn.addEventListener('click', () => {
      buttons.forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      currentLayout = btn.dataset.layout;
    });
  });
}

function initializePresets() {
  document.querySelectorAll('[data-preset]').forEach((btn) => {
    btn.addEventListener('click', () => applyPreset(btn.dataset.preset));
  });
}

function setupEventListeners() {
  // Per-column add buttons
  SLOTS.forEach((slot) => {
    const btn = document.querySelector(`[data-add-block-btn="${slot}"]`);
    if (btn) {
      btn.addEventListener('click', () => openBlockEditor(null, slot));
    }
  });

  // Form submit
  const form = document.querySelector('[data-block-form]');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      saveBlock();
    });
  }

  // Type radio change
  document.querySelectorAll('[data-type-grid] input[type="radio"]').forEach((radio) => {
    radio.addEventListener('change', (e) => {
      renderContentFields(e.target.value);
    });
  });
}

// ── Load data ──────────────────────────────────────────────────
async function loadAllSlots() {
  const promises = SLOTS.map((slot) => loadSlot(slot));
  await Promise.all(promises);
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
    container.innerHTML = `<div class="uk-alert uk-alert-danger uk-alert-small">${error.message}</div>`;
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
      <div class="block-card__icon ${icon.cls}">${icon.abbr}</div>
      <div class="block-card__info">
        <div class="block-card__title">${escapeHtml(title)}</div>
        <div class="block-card__meta">${escapeHtml(meta)}</div>
      </div>
      <div class="block-card__actions">
        <button type="button" onclick="editBlock(${block.id}, '${block.slot || ''}')" title="Bearbeiten">
          <span uk-icon="icon: pencil; ratio: 0.7"></span>
        </button>
        <button type="button" class="btn-delete" onclick="deleteBlock(${block.id}, '${block.slot || ''}')" title="Loeschen">
          <span uk-icon="icon: trash; ratio: 0.7"></span>
        </button>
      </div>
    </div>`;
}

function getBlockTitle(block) {
  switch (block.type) {
    case 'menu':
      return block.content.title || 'Menu';
    case 'text':
      return block.content.title || 'Text';
    case 'social':
      return block.content.title || 'Social Media';
    case 'contact':
      return block.content.title || 'Kontakt';
    case 'newsletter':
      return block.content.title || 'Newsletter';
    case 'html':
      return block.content.title || 'Custom HTML';
    default:
      return 'Block';
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
      return text.replace(/<[^>]*>/g, '').substring(0, 50) + (text.length > 50 ? '...' : '');
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

// ── Block editor modal ──────────────────────────────────────────
function openBlockEditor(blockId = null, slot = null) {
  editingBlockId = blockId;
  editingSlot = slot;
  const form = document.querySelector('[data-block-form]');
  const titleEl = document.querySelector('[data-modal-title]');

  if (blockId) {
    // Find the block across all slots
    let block = null;
    for (const s of SLOTS) {
      block = blocksBySlot[s].find((b) => b.id === blockId);
      if (block) {
        editingSlot = s;
        break;
      }
    }
    if (!block) return;

    titleEl.textContent = 'Block bearbeiten';
    form.querySelector('[data-block-id]').value = block.id;
    form.querySelector('[data-target-slot]').value = editingSlot;

    // Set type radio
    const radio = form.querySelector(`input[name="type"][value="${block.type}"]`);
    if (radio) radio.checked = true;

    form.querySelector('[data-active-checkbox]').checked = block.isActive;
    renderContentFields(block.type, block.content);
  } else {
    titleEl.textContent = 'Block hinzufuegen';
    form.reset();
    form.querySelector('[data-target-slot]').value = slot || 'footer_1';
    form.querySelector('[data-active-checkbox]').checked = true;
    const firstRadio = form.querySelector('input[name="type"][value="menu"]');
    if (firstRadio) firstRadio.checked = true;
    renderContentFields('menu');
  }

  UIkit.modal('#blockEditorModal').show();
}

function renderContentFields(type, existingContent = {}) {
  const container = document.querySelector('[data-content-fields]');
  if (!container) return;

  let html = '';

  switch (type) {
    case 'menu':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Titel</label>
          <input type="text" name="content_title" class="uk-input" value="${escapeAttr(existingContent.title || '')}" placeholder="z.B. Navigation, Links, Produkte">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Menu</label>
          <select name="content_menuId" class="uk-select" required>
            <option value="">Menu waehlen...</option>
            ${(window.templateData?.menuDefinitions || [])
              .map(
                (menu) =>
                  `<option value="${menu.id}" ${existingContent.menuId == menu.id ? 'selected' : ''}>${escapeHtml(menu.label)} (${escapeHtml(menu.locale)})</option>`
              )
              .join('')}
          </select>
        </div>`;
      break;

    case 'text':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Titel</label>
          <input type="text" name="content_title" class="uk-input" value="${escapeAttr(existingContent.title || '')}" placeholder="z.B. Ueber uns">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Text</label>
          <textarea name="content_text" class="uk-textarea" rows="5" required>${escapeHtml(existingContent.text || '')}</textarea>
          <div class="uk-text-meta">HTML wird unterstuetzt</div>
        </div>`;
      break;

    case 'social':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Titel</label>
          <input type="text" name="content_title" class="uk-input" value="${escapeAttr(existingContent.title || '')}" placeholder="Folge uns">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Facebook</label>
          <input type="url" name="content_facebook" class="uk-input" value="${escapeAttr(existingContent.links?.facebook || '')}" placeholder="https://facebook.com/...">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Twitter / X</label>
          <input type="url" name="content_twitter" class="uk-input" value="${escapeAttr(existingContent.links?.twitter || '')}" placeholder="https://twitter.com/...">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">LinkedIn</label>
          <input type="url" name="content_linkedin" class="uk-input" value="${escapeAttr(existingContent.links?.linkedin || '')}" placeholder="https://linkedin.com/...">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Instagram</label>
          <input type="url" name="content_instagram" class="uk-input" value="${escapeAttr(existingContent.links?.instagram || '')}" placeholder="https://instagram.com/...">
        </div>`;
      break;

    case 'contact':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Titel</label>
          <input type="text" name="content_title" class="uk-input" value="${escapeAttr(existingContent.title || '')}" placeholder="Kontakt">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">E-Mail</label>
          <input type="email" name="content_email" class="uk-input" value="${escapeAttr(existingContent.email || '')}" placeholder="info@example.com">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Telefon</label>
          <input type="tel" name="content_phone" class="uk-input" value="${escapeAttr(existingContent.phone || '')}" placeholder="+49 123 456789">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Adresse</label>
          <textarea name="content_address" class="uk-textarea" rows="3">${escapeHtml(existingContent.address || '')}</textarea>
        </div>`;
      break;

    case 'newsletter':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Titel</label>
          <input type="text" name="content_title" class="uk-input" value="${escapeAttr(existingContent.title || '')}" placeholder="Newsletter">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Beschreibung</label>
          <textarea name="content_description" class="uk-textarea" rows="2">${escapeHtml(existingContent.description || '')}</textarea>
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Button-Text</label>
          <input type="text" name="content_buttonText" class="uk-input" value="${escapeAttr(existingContent.buttonText || '')}" placeholder="Abonnieren">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Ziel-URL</label>
          <input type="url" name="content_actionUrl" class="uk-input" value="${escapeAttr(existingContent.actionUrl || '')}" placeholder="/newsletter/subscribe">
        </div>`;
      break;

    case 'html':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Titel (intern)</label>
          <input type="text" name="content_title" class="uk-input" value="${escapeAttr(existingContent.title || '')}" placeholder="Custom Block">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">HTML-Inhalt</label>
          <textarea name="content_html" class="uk-textarea" rows="10" required>${escapeHtml(existingContent.html || '')}</textarea>
          <div class="uk-text-meta">HTML wird direkt gerendert</div>
        </div>`;
      break;
  }

  container.innerHTML = html;
}

// ── Save / Delete ──────────────────────────────────────────────
async function saveBlock() {
  const form = document.querySelector('[data-block-form]');
  const formData = new FormData(form);
  const type = formData.get('type');
  const targetSlot = formData.get('targetSlot') || editingSlot || 'footer_1';

  const content = extractContentFromForm(type, formData);

  const payload = {
    type,
    content,
    isActive: formData.get('isActive') === 'on',
    position: blocksBySlot[targetSlot]?.length || 0,
  };

  if (!editingBlockId) {
    payload.namespace = currentNamespace;
    payload.slot = targetSlot;
    payload.locale = currentLocale;
  }

  try {
    let response;
    if (editingBlockId) {
      response = await fetch(`/admin/footer-blocks/${editingBlockId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
    } else {
      response = await fetch('/admin/footer-blocks', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
    }

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Speichern fehlgeschlagen');
    }

    UIkit.modal('#blockEditorModal').hide();
    await loadSlot(targetSlot);
    UIkit.notification('Block gespeichert', { status: 'success', pos: 'top-right' });
  } catch (error) {
    console.error('Error saving block:', error);
    UIkit.notification(error.message, { status: 'danger', pos: 'top-right' });
  }
}

function extractContentFromForm(type, formData) {
  const content = {};

  switch (type) {
    case 'menu':
      content.title = formData.get('content_title') || '';
      content.menuId = parseInt(formData.get('content_menuId')) || null;
      break;
    case 'text':
      content.title = formData.get('content_title') || '';
      content.text = formData.get('content_text') || '';
      break;
    case 'social':
      content.title = formData.get('content_title') || '';
      content.links = {
        facebook: formData.get('content_facebook') || '',
        twitter: formData.get('content_twitter') || '',
        linkedin: formData.get('content_linkedin') || '',
        instagram: formData.get('content_instagram') || '',
      };
      break;
    case 'contact':
      content.title = formData.get('content_title') || '';
      content.email = formData.get('content_email') || '';
      content.phone = formData.get('content_phone') || '';
      content.address = formData.get('content_address') || '';
      break;
    case 'newsletter':
      content.title = formData.get('content_title') || '';
      content.description = formData.get('content_description') || '';
      content.buttonText = formData.get('content_buttonText') || '';
      content.actionUrl = formData.get('content_actionUrl') || '';
      break;
    case 'html':
      content.title = formData.get('content_title') || '';
      content.html = formData.get('content_html') || '';
      break;
  }

  return content;
}

// ── Presets ─────────────────────────────────────────────────────
async function applyPreset(preset) {
  const hasBlocks = SLOTS.some((s) => blocksBySlot[s].length > 0);
  if (hasBlocks) {
    if (!confirm('Vorhandene Blocks werden durch die Vorlage ersetzt. Fortfahren?')) return;

    // Delete all existing blocks
    for (const slot of SLOTS) {
      for (const block of blocksBySlot[slot]) {
        try {
          await fetch(`/admin/footer-blocks/${block.id}`, { method: 'DELETE' });
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
        headers: { 'Content-Type': 'application/json' },
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

  // Set layout
  const layoutBtns = document.querySelectorAll('[data-layout-selector] .layout-option');
  const presetLayout = preset === 'contact-focus' ? 'brand-left' : preset === 'minimal' ? 'centered' : 'equal';
  layoutBtns.forEach((btn) => {
    btn.classList.toggle('active', btn.dataset.layout === presetLayout);
  });
  currentLayout = presetLayout;

  await loadAllSlots();
  UIkit.notification('Vorlage angewendet', { status: 'success', pos: 'top-right' });
}

function getPresetBlocks(preset) {
  switch (preset) {
    case 'business':
      return [
        { slot: 'footer_1', type: 'text', position: 0, content: { title: 'Unternehmen', text: 'Kurze Beschreibung Ihres Unternehmens. Bearbeiten Sie diesen Text im Footer-Editor.' } },
        { slot: 'footer_1', type: 'social', position: 1, content: { title: '', links: { facebook: '', twitter: '', linkedin: '', instagram: '' } } },
        { slot: 'footer_2', type: 'menu', position: 0, content: { title: 'Navigation', menuId: null } },
        { slot: 'footer_3', type: 'contact', position: 0, content: { title: 'Kontakt', email: 'info@example.com', phone: '+49 123 456789', address: 'Musterstrasse 1\n12345 Musterstadt' } },
      ];
    case 'minimal':
      return [
        { slot: 'footer_1', type: 'text', position: 0, content: { title: '', text: '&copy; 2026 Ihr Unternehmen. Alle Rechte vorbehalten.' } },
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

// ── Global handlers ────────────────────────────────────────────
window.editBlock = (id, slot) => {
  openBlockEditor(id, slot);
};

window.deleteBlock = async (id, slot) => {
  if (!confirm('Block wirklich loeschen?')) return;

  try {
    const response = await fetch(`/admin/footer-blocks/${id}`, { method: 'DELETE' });
    if (!response.ok) throw new Error('Loeschen fehlgeschlagen');

    // Reload the affected slot
    const targetSlot = slot || SLOTS.find((s) => blocksBySlot[s].some((b) => b.id === id)) || 'footer_1';
    await loadSlot(targetSlot);
    UIkit.notification('Block geloescht', { status: 'success', pos: 'top-right' });
  } catch (error) {
    console.error('Error deleting block:', error);
    UIkit.notification(error.message, { status: 'danger', pos: 'top-right' });
  }
};

// ── Helpers ─────────────────────────────────────────────────────
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function escapeAttr(str) {
  return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
