/**
 * Footer Block Editor
 * Manages footer content blocks with different types (menu, text, social, contact, newsletter, html)
 */

let currentBlocks = [];
let currentNamespace = 'default';
let currentLocale = 'de';
let currentSlot = 'footer_1';
let editingBlockId = null;

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  initializeSelectors();
  loadBlocks();
  setupEventListeners();
});

function initializeSelectors() {
  const namespaceSelect = document.querySelector('[data-namespace-select]');
  const localeSelect = document.querySelector('[data-locale-select]');
  const slotSelect = document.querySelector('[data-slot-select]');

  if (namespaceSelect) {
    currentNamespace = namespaceSelect.value;
    namespaceSelect.addEventListener('change', (e) => {
      currentNamespace = e.target.value;
      loadBlocks();
    });
  }

  if (localeSelect) {
    currentLocale = localeSelect.value;
    localeSelect.addEventListener('change', (e) => {
      currentLocale = e.target.value;
      loadBlocks();
    });
  }

  if (slotSelect) {
    currentSlot = slotSelect.value;
    slotSelect.addEventListener('change', (e) => {
      currentSlot = e.target.value;
      loadBlocks();
    });
  }
}

function setupEventListeners() {
  // Add block button
  const addBtn = document.querySelector('[data-add-block-btn]');
  if (addBtn) {
    addBtn.addEventListener('click', () => openBlockEditor());
  }

  // Form submit
  const form = document.querySelector('[data-block-form]');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      saveBlock();
    });
  }

  // Type select change
  const typeSelect = document.querySelector('[data-type-select]');
  if (typeSelect) {
    typeSelect.addEventListener('change', (e) => {
      renderContentFields(e.target.value);
    });
  }
}

async function loadBlocks() {
  const container = document.querySelector('[data-blocks-container]');
  if (!container) return;

  container.innerHTML = '<div class="uk-text-center uk-text-muted uk-padding"><span uk-spinner></span> Loading...</div>';

  try {
    const response = await fetch(
      `/admin/footer-blocks?namespace=${encodeURIComponent(currentNamespace)}&slot=${encodeURIComponent(currentSlot)}&locale=${encodeURIComponent(currentLocale)}`
    );

    if (!response.ok) {
      throw new Error('Failed to load blocks');
    }

    const data = await response.json();
    currentBlocks = data.blocks || [];
    renderBlocks();
  } catch (error) {
    console.error('Error loading blocks:', error);
    container.innerHTML = `<div class="uk-alert uk-alert-danger">Error loading blocks: ${error.message}</div>`;
  }
}

function renderBlocks() {
  const container = document.querySelector('[data-blocks-container]');
  if (!container) return;

  if (currentBlocks.length === 0) {
    container.innerHTML = '<div class="uk-text-center uk-text-muted uk-padding">No blocks yet. Click "Add Block" to create one.</div>';
    return;
  }

  container.innerHTML = currentBlocks
    .map(
      (block) => `
    <div class="uk-card uk-card-default uk-card-small uk-margin-small" data-block-id="${block.id}">
      <div class="uk-card-body">
        <div class="uk-grid-small uk-flex-middle" uk-grid>
          <div class="uk-width-expand">
            <div class="uk-flex uk-flex-middle">
              <span class="uk-badge ${block.isActive ? '' : 'uk-badge-danger'}" style="margin-right: 10px;">
                ${block.type.toUpperCase()}
              </span>
              <div>
                <div class="uk-text-bold">${getBlockTitle(block)}</div>
                <div class="uk-text-meta uk-text-small">${getBlockSummary(block)}</div>
              </div>
            </div>
          </div>
          <div class="uk-width-auto">
            <button class="uk-button uk-button-small uk-button-default" onclick="editBlock(${block.id})">
              <span uk-icon="icon: pencil"></span>
            </button>
            <button class="uk-button uk-button-small uk-button-danger" onclick="deleteBlock(${block.id})">
              <span uk-icon="icon: trash"></span>
            </button>
          </div>
        </div>
      </div>
    </div>
  `
    )
    .join('');
}

function getBlockTitle(block) {
  switch (block.type) {
    case 'menu':
      return block.content.title || 'Menu Block';
    case 'text':
      return block.content.title || 'Text Block';
    case 'social':
      return 'Social Media';
    case 'contact':
      return 'Contact Information';
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
    case 'menu':
      return `Menu ID: ${block.content.menuId || 'None'}`;
    case 'text':
      const text = block.content.text || '';
      return text.substring(0, 60) + (text.length > 60 ? '...' : '');
    case 'social':
      const platforms = Object.keys(block.content.links || {});
      return platforms.length > 0 ? `Platforms: ${platforms.join(', ')}` : 'No links';
    case 'contact':
      return [block.content.email, block.content.phone].filter(Boolean).join(', ') || 'No info';
    case 'newsletter':
      return block.content.description || 'Newsletter signup';
    case 'html':
      return 'Custom HTML content';
    default:
      return '';
  }
}

function openBlockEditor(blockId = null) {
  editingBlockId = blockId;
  const modal = UIkit.modal('#blockEditorModal');
  const form = document.querySelector('[data-block-form]');
  const titleEl = document.querySelector('[data-modal-title]');

  if (blockId) {
    const block = currentBlocks.find((b) => b.id === blockId);
    if (!block) return;

    titleEl.textContent = 'Edit Block';
    form.querySelector('[data-block-id]').value = block.id;
    form.querySelector('[data-type-select]').value = block.type;
    form.querySelector('[data-active-checkbox]').checked = block.isActive;

    renderContentFields(block.type, block.content);
  } else {
    titleEl.textContent = 'Add Block';
    form.reset();
    form.querySelector('[data-active-checkbox]').checked = true;
    renderContentFields('menu');
  }

  modal.show();
}

function renderContentFields(type, existingContent = {}) {
  const container = document.querySelector('[data-content-fields]');
  if (!container) return;

  let html = '';

  switch (type) {
    case 'menu':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Title</label>
          <input type="text" name="content_title" class="uk-input" value="${existingContent.title || ''}" placeholder="Column Title">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Menu ID</label>
          <select name="content_menuId" class="uk-select" required>
            <option value="">Select a menu...</option>
            ${window.menuDefinitions
              ? window.menuDefinitions
                  .map(
                    (menu) =>
                      `<option value="${menu.id}" ${existingContent.menuId == menu.id ? 'selected' : ''}>${menu.label} (${menu.locale})</option>`
                  )
                  .join('')
              : ''}
          </select>
        </div>
      `;
      break;

    case 'text':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Title</label>
          <input type="text" name="content_title" class="uk-input" value="${existingContent.title || ''}" placeholder="Block Title">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Text Content</label>
          <textarea name="content_text" class="uk-textarea" rows="5" required>${existingContent.text || ''}</textarea>
          <div class="uk-text-meta">Supports HTML</div>
        </div>
      `;
      break;

    case 'social':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Title</label>
          <input type="text" name="content_title" class="uk-input" value="${existingContent.title || ''}" placeholder="Follow Us">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Facebook URL</label>
          <input type="url" name="content_facebook" class="uk-input" value="${existingContent.links?.facebook || ''}" placeholder="https://facebook.com/...">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Twitter/X URL</label>
          <input type="url" name="content_twitter" class="uk-input" value="${existingContent.links?.twitter || ''}" placeholder="https://twitter.com/...">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">LinkedIn URL</label>
          <input type="url" name="content_linkedin" class="uk-input" value="${existingContent.links?.linkedin || ''}" placeholder="https://linkedin.com/...">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Instagram URL</label>
          <input type="url" name="content_instagram" class="uk-input" value="${existingContent.links?.instagram || ''}" placeholder="https://instagram.com/...">
        </div>
      `;
      break;

    case 'contact':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Title</label>
          <input type="text" name="content_title" class="uk-input" value="${existingContent.title || ''}" placeholder="Contact Us">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Email</label>
          <input type="email" name="content_email" class="uk-input" value="${existingContent.email || ''}" placeholder="info@example.com">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Phone</label>
          <input type="tel" name="content_phone" class="uk-input" value="${existingContent.phone || ''}" placeholder="+49 123 456789">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Address</label>
          <textarea name="content_address" class="uk-textarea" rows="3">${existingContent.address || ''}</textarea>
        </div>
      `;
      break;

    case 'newsletter':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Title</label>
          <input type="text" name="content_title" class="uk-input" value="${existingContent.title || ''}" placeholder="Newsletter">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Description</label>
          <textarea name="content_description" class="uk-textarea" rows="2">${existingContent.description || ''}</textarea>
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Button Text</label>
          <input type="text" name="content_buttonText" class="uk-input" value="${existingContent.buttonText || ''}" placeholder="Subscribe">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">Action URL</label>
          <input type="url" name="content_actionUrl" class="uk-input" value="${existingContent.actionUrl || ''}" placeholder="/newsletter/subscribe">
        </div>
      `;
      break;

    case 'html':
      html = `
        <div class="uk-margin">
          <label class="uk-form-label">Title (for reference)</label>
          <input type="text" name="content_title" class="uk-input" value="${existingContent.title || ''}" placeholder="Custom Block">
        </div>
        <div class="uk-margin">
          <label class="uk-form-label">HTML Content</label>
          <textarea name="content_html" class="uk-textarea" rows="10" required>${existingContent.html || ''}</textarea>
          <div class="uk-text-meta">Raw HTML will be rendered as-is</div>
        </div>
      `;
      break;
  }

  container.innerHTML = html;
}

async function saveBlock() {
  const form = document.querySelector('[data-block-form]');
  const formData = new FormData(form);
  const type = formData.get('type');

  const content = extractContentFromForm(type, formData);

  const payload = {
    type,
    content,
    isActive: formData.get('isActive') === 'on',
    position: currentBlocks.length,
  };

  if (!editingBlockId) {
    payload.namespace = currentNamespace;
    payload.slot = currentSlot;
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
      throw new Error(error.error || 'Failed to save block');
    }

    UIkit.modal('#blockEditorModal').hide();
    loadBlocks();
    UIkit.notification('Block saved successfully', { status: 'success' });
  } catch (error) {
    console.error('Error saving block:', error);
    UIkit.notification(error.message, { status: 'danger' });
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

window.editBlock = (id) => {
  openBlockEditor(id);
};

window.deleteBlock = async (id) => {
  if (!confirm('Are you sure you want to delete this block?')) {
    return;
  }

  try {
    const response = await fetch(`/admin/footer-blocks/${id}`, {
      method: 'DELETE',
    });

    if (!response.ok) {
      throw new Error('Failed to delete block');
    }

    loadBlocks();
    UIkit.notification('Block deleted successfully', { status: 'success' });
  } catch (error) {
    console.error('Error deleting block:', error);
    UIkit.notification(error.message, { status: 'danger' });
  }
};

// Expose menu definitions from template to JavaScript
if (window.templateData?.menuDefinitions) {
  window.menuDefinitions = window.templateData.menuDefinitions;
}
