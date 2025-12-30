import { renderPage, RENDERER_MATRIX } from './block-renderer-matrix.js';
import { initEffects } from '../effects/initEffects.js';

const noop = () => {};

const parseBlocks = value => {
  if (!value) {
    return [];
  }
  if (Array.isArray(value)) {
    return value;
  }
  if (typeof value === 'object') {
    return value.blocks || [];
  }
  return [];
};

const resolvePreviewNamespace = root => {
  if (root?.dataset?.namespace) {
    return root.dataset.namespace;
  }
  const ancestor = root?.closest?.('[data-namespace]');
  if (ancestor?.dataset?.namespace) {
    return ancestor.dataset.namespace;
  }
  if (typeof document !== 'undefined' && document.documentElement?.dataset?.namespace) {
    return document.documentElement.dataset.namespace;
  }
  return 'default';
};

export class PreviewCanvas {
  constructor(root, options = {}) {
    this.root = root;
    this.onSelect = typeof options.onSelect === 'function' ? options.onSelect : noop;
    this.onInlineEdit = typeof options.onInlineEdit === 'function' ? options.onInlineEdit : null;
    this.renderSelectionOnly = options.renderSelectionOnly === true;
    this.intent = ['preview', 'design'].includes(options.intent) ? options.intent : 'edit';
    this.blocks = [];
    this.highlightBlockId = null;
    this.hoverBlockId = null;
    this.blockIds = new Set();
    this.visibleBlocks = [];
    this.activeEdit = null;
    this.cleanupInlineEdit = null;

    this.surface = document.createElement('div');
    this.surface.className = 'page-preview-surface';

    this.root.classList.add('page-preview-canvas');
    this.root.append(this.surface);

    this.handleClick = this.handleClick.bind(this);
    this.handleEditableClick = this.handleEditableClick.bind(this);
    this.root.addEventListener('click', this.handleClick, true);
    this.surface.addEventListener('click', this.handleEditableClick);

    this.cleanupEffects = null;
  }

  destroy() {
    this.root.removeEventListener('click', this.handleClick, true);
    this.surface.removeEventListener('click', this.handleEditableClick);
    this.finishInlineEdit(false);
    if (typeof this.cleanupEffects === 'function') {
      this.cleanupEffects();
      this.cleanupEffects = null;
    }
    this.root.innerHTML = '';
    this.blockIds.clear();
  }

  setBlocks(blocks, highlightBlockId = null) {
    this.blocks = parseBlocks(blocks);
    this.highlightBlockId = highlightBlockId || null;
    const shouldLimitToSelection = this.renderSelectionOnly && this.highlightBlockId;
    this.visibleBlocks = shouldLimitToSelection
      ? this.blocks.filter(block => block?.id === this.highlightBlockId)
      : this.blocks;
    this.blockIds = new Set(this.visibleBlocks.map(block => block?.id).filter(Boolean));
    if (this.hoverBlockId && !this.blockIds.has(this.hoverBlockId)) {
      this.hoverBlockId = null;
    }
    this.render();
  }

  render() {
    if (this.activeEdit) {
      this.finishInlineEdit(false);
    }
    if (typeof this.cleanupEffects === 'function') {
      this.cleanupEffects();
      this.cleanupEffects = null;
    }
    const html = renderPage(Array.isArray(this.visibleBlocks) ? this.visibleBlocks : [], {
      rendererMatrix: RENDERER_MATRIX,
      context: 'preview'
    });
    this.surface.innerHTML = html;
    this.applySelectionHighlight();
    const namespace = resolvePreviewNamespace(this.root);
    const mode = this.intent === 'preview' ? 'preview' : (this.intent === 'design' ? 'design-preview' : 'edit');
    const effects = initEffects(this.surface, { namespace, mode });
    this.cleanupEffects = effects?.destroy || null;
  }

  setIntent(intent = 'edit') {
    const allowed = ['edit', 'preview', 'design'];
    const normalized = allowed.includes(intent) ? intent : 'edit';
    if (this.intent === normalized) {
      return;
    }
    this.intent = normalized;
    if (this.intent !== 'edit') {
      this.finishInlineEdit(false);
      this.setHoverBlock(null);
    }
    this.applySelectionHighlight();
  }

  setHoverBlock(blockId = null) {
    this.hoverBlockId = blockId && this.blockIds.has(blockId) ? blockId : null;
    this.applySelectionHighlight();
  }

  scrollToBlock(blockId) {
    if (!blockId || !this.blockIds.has(blockId)) {
      return;
    }

    const selector = `[data-block-id="${typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(blockId) : blockId}"]`;
    const target = this.surface.querySelector(selector);
    if (!target) {
      return;
    }

    const surfaceRect = this.surface.getBoundingClientRect();
    const targetRect = target.getBoundingClientRect();
    const offset = targetRect.top - surfaceRect.top + this.root.scrollTop - 8;

    this.root.scrollTo({
      top: offset < 0 ? 0 : offset,
      behavior: 'smooth'
    });

    this.setHoverBlock(blockId);
  }

  handleClick(event) {
    if (event.target.closest('[data-editable="true"]')) {
      return;
    }
    const target = event.target.closest('[data-block-id]');
    if (!target) {
      return;
    }
    const blockId = target.getAttribute('data-block-id');
    if (!blockId || !this.blockIds.has(blockId)) {
      return;
    }
    event.preventDefault();
    this.onSelect(blockId);
  }

  handleEditableClick(event) {
    const target = event.target.closest('[data-editable="true"]');
    if (!target || !this.surface.contains(target)) {
      return;
    }
    if (this.intent !== 'edit') {
      return;
    }
    const previewPane = this.root.closest('[data-preview-pane]');
    if (previewPane?.dataset.previewIntent === 'design') {
      return;
    }
    const blockId = target.getAttribute('data-block-id');
    const fieldPath = target.getAttribute('data-field-path');
    if (!blockId || !fieldPath || !this.blockIds.has(blockId) || !this.onInlineEdit) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    const type = target.getAttribute('data-editable-type') === 'richtext' ? 'richtext' : 'text';
    this.startInlineEdit(target, { blockId, fieldPath, type });
    this.onSelect(blockId);
  }

  startInlineEdit(element, meta) {
    this.finishInlineEdit(false);
    const editableElement = element;
    const originalHtml = editableElement.innerHTML;
    editableElement.dataset.editing = 'true';
    editableElement.setAttribute('contenteditable', 'true');
    editableElement.setAttribute('spellcheck', 'true');

    const onBlur = () => this.finishInlineEdit(true);
    const onKeydown = event => {
      if (event.key === 'Escape') {
        event.preventDefault();
        this.finishInlineEdit(false);
      }
      if (event.key === 'Enter' && !(meta.type === 'richtext' && event.shiftKey)) {
        event.preventDefault();
        this.finishInlineEdit(true);
      }
    };
    const swallowClick = evt => evt.stopPropagation();

    editableElement.addEventListener('blur', onBlur);
    editableElement.addEventListener('keydown', onKeydown);
    editableElement.addEventListener('click', swallowClick);

    this.cleanupInlineEdit = () => {
      editableElement.removeEventListener('blur', onBlur);
      editableElement.removeEventListener('keydown', onKeydown);
      editableElement.removeEventListener('click', swallowClick);
    };

    const selection = typeof window !== 'undefined' ? window.getSelection?.() : null;
    if (selection && typeof selection.removeAllRanges === 'function' && editableElement.firstChild) {
      const range = document.createRange();
      range.selectNodeContents(editableElement);
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    }
    editableElement.focus();
    this.activeEdit = { element: editableElement, originalHtml, meta };
  }

  finishInlineEdit(commit = false) {
    if (!this.activeEdit) {
      return;
    }
    const { element, originalHtml, meta } = this.activeEdit;
    if (this.cleanupInlineEdit) {
      this.cleanupInlineEdit();
      this.cleanupInlineEdit = null;
    }
    element.removeAttribute('contenteditable');
    element.removeAttribute('spellcheck');
    element.removeAttribute('data-editing');
    const value = meta.type === 'richtext'
      ? (element.innerHTML || '').trim()
      : (element.textContent || '').trim();
    let updated = false;
    if (commit && this.onInlineEdit) {
      updated = this.onInlineEdit({ ...meta, value });
    }
    if (!updated) {
      element.innerHTML = originalHtml;
    }
    this.activeEdit = null;
  }

  applySelectionHighlight() {
    const shouldHighlight = this.intent === 'edit';
    const selectable = this.surface.querySelectorAll('[data-block-id]');
    selectable.forEach(el => {
      el.removeAttribute('data-preview-selected');
      el.removeAttribute('data-preview-hover');
      if (!shouldHighlight || el.classList.contains('page-preview-empty')) {
        return;
      }
      const value = el.getAttribute('data-block-id');
      if (this.hoverBlockId && value === this.hoverBlockId) {
        el.setAttribute('data-preview-hover', 'true');
      }
      if (this.highlightBlockId && value === this.highlightBlockId) {
        el.setAttribute('data-preview-selected', 'true');
      }
    });
  }
}

export default PreviewCanvas;
