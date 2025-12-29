import { renderPage } from './page-renderer.js';
import { RENDERER_MATRIX } from './block-renderer-matrix.js';

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

export class PreviewCanvas {
  constructor(root, options = {}) {
    this.root = root;
    this.onSelect = typeof options.onSelect === 'function' ? options.onSelect : noop;
    this.renderSelectionOnly = options.renderSelectionOnly === true;
    this.blocks = [];
    this.highlightBlockId = null;
    this.hoverBlockId = null;
    this.blockIds = new Set();
    this.visibleBlocks = [];

    this.surface = document.createElement('div');
    this.surface.className = 'page-preview-surface';

    this.root.classList.add('page-preview-canvas');
    this.root.append(this.surface);

    this.handleClick = this.handleClick.bind(this);
    this.root.addEventListener('click', this.handleClick, true);
  }

  destroy() {
    this.root.removeEventListener('click', this.handleClick, true);
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
    const html = renderPage(Array.isArray(this.visibleBlocks) ? this.visibleBlocks : [], {
      rendererMatrix: RENDERER_MATRIX,
      context: 'preview'
    });
    this.surface.innerHTML = html;
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

  applySelectionHighlight() {
    const selectable = this.surface.querySelectorAll('[data-block-id]');
    selectable.forEach(el => {
      el.removeAttribute('data-preview-selected');
      el.removeAttribute('data-preview-hover');
      if (el.classList.contains('page-preview-empty')) {
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
