import { renderPage } from './page-renderer.js';

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
    this.blocks = [];
    this.highlightBlockId = null;
    this.blockIds = new Set();

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
    this.blockIds = new Set(this.blocks.map(block => block?.id).filter(Boolean));
    this.render();
  }

  render() {
    const html = renderPage(this.blocks, {
      mode: 'preview',
      highlightBlockId: this.highlightBlockId
    });
    this.surface.innerHTML = html;
    this.applySelectionHighlight();
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
      if (el.classList.contains('page-preview-empty')) {
        return;
      }
      const value = el.getAttribute('data-block-id');
      if (this.highlightBlockId && value === this.highlightBlockId) {
        el.setAttribute('data-preview-selected', 'true');
      }
    });
  }
}

export default PreviewCanvas;
