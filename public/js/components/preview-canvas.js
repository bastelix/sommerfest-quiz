import { renderPage, RENDERER_MATRIX } from './block-renderer-matrix.js';
import { initEffects } from '../effects/initEffects.js';
import { applyNamespaceDesign, resolveNamespaceAppearance } from './namespace-design.js';

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

const resolveGlobalPageContext = (root, override) => {
  if (override && typeof override === 'object') {
    return override;
  }

  if (typeof window !== 'undefined' && window.pageContext && typeof window.pageContext === 'object') {
    return window.pageContext;
  }

  if (root?.dataset?.pageContext) {
    try {
      const parsed = JSON.parse(root.dataset.pageContext);
      if (parsed && typeof parsed === 'object') {
        return parsed;
      }
    } catch (error) {
      // ignore parsing errors and fall back to defaults
    }
  }

  return {};
};

const escapeAttr = str => String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const IFRAME_INLINE_STYLES = `
  html, body {
    margin: 0;
    padding: 0;
    background: transparent;
  }
  body {
    padding: 12px;
  }
  .page-preview-surface [data-block-id] {
    outline: 1px dashed transparent;
    transition: outline-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
  }
  .page-preview-surface [data-block-id]:hover {
    outline-color: var(--border-muted, #cbd2d9);
    background-color: var(--surface-subtle, #f8fafc);
  }
  .page-preview-surface [data-preview-selected="true"] {
    outline-color: var(--brand-primary, #1e87f0);
    box-shadow: 0 0 0 2px var(--brand-primary, #1e87f0), 0 6px 14px rgba(30, 135, 240, 0.15);
    background-color: var(--surface-accent-soft, #f0f7ff);
  }
  .page-preview-surface [data-preview-hover="true"] {
    outline-color: var(--border-muted, #94a2b8);
    box-shadow: 0 0 0 2px var(--border-muted, #cbd2d9);
    background-color: var(--surface-subtle, #f8fafc);
  }
  body[data-preview-intent="preview"] .page-preview-surface [data-block-id],
  body[data-preview-intent="design"] .page-preview-surface [data-block-id] {
    outline-color: transparent;
    box-shadow: none;
    background-color: inherit;
  }
  [data-editable="true"] {
    outline: 1px dashed transparent;
    outline-offset: 2px;
    transition: outline-color 0.15s ease, box-shadow 0.15s ease;
  }
  [data-editable="true"]:hover {
    outline-color: var(--border-muted, #b7c5dc);
    cursor: text;
  }
  [data-editable="true"][data-editing="true"] {
    outline: 2px solid var(--brand-primary, #1e87f0);
    box-shadow: 0 0 0 2px rgba(30, 135, 240, 0.2);
  }
  body[data-preview-intent="preview"] [data-editable="true"],
  body[data-preview-intent="design"] [data-editable="true"] {
    outline: none;
    box-shadow: none;
    pointer-events: none;
    cursor: default;
  }
  .page-preview-surface .site-footer {
    min-height: 180px;
  }
  .page-preview-surface .site-footer .footer-columns {
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    align-content: start;
  }
  .page-preview-surface .preview-status {
    position: relative;
    z-index: 1;
  }
  .page-preview-surface .preview-status .uk-alert {
    margin-bottom: 12px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .page-preview-surface .preview-status .uk-link-text {
    font-weight: 600;
  }
  .page-preview-surface .page-preview-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted, #6b7280);
  }
`;

const collectPreviewStylesheets = () => {
  const links = document.querySelectorAll('link[data-preview-asset="page-preview"]');
  return Array.from(links).map(link => link.href);
};

export class PreviewCanvas {
  constructor(root, options = {}) {
    this.root = root;
    this.onSelect = typeof options.onSelect === 'function' ? options.onSelect : noop;
    this.onInlineEdit = typeof options.onInlineEdit === 'function' ? options.onInlineEdit : null;
    this.renderSelectionOnly = options.renderSelectionOnly === true;
    this.intent = ['preview', 'design'].includes(options.intent) ? options.intent : 'edit';
    this.appearance = options.appearance || null;
    this.pageContext = resolveGlobalPageContext(root, options.pageContext);
    this.blocks = [];
    this.highlightBlockId = null;
    this.hoverBlockId = null;
    this.blockIds = new Set();
    this.visibleBlocks = [];
    this.activeEdit = null;
    this.cleanupInlineEdit = null;
    this.cleanupEffects = null;
    this.iframe = null;
    this.iframeDoc = null;
    this._heightObserver = null;
    this._themeObserver = null;

    this.root.classList.add('page-preview-canvas');

    this._initIframe();

    this.handleClick = this.handleClick.bind(this);
    this.handleEditableClick = this.handleEditableClick.bind(this);

    if (this.iframeDoc) {
      this.iframeDoc.addEventListener('click', this.handleClick, true);
      this.surface.addEventListener('click', this.handleEditableClick);
      this._observeTheme();
    }
  }

  _initIframe() {
    this.iframe = document.createElement('iframe');
    this.iframe.className = 'page-preview-iframe';
    this.iframe.setAttribute('title', 'Live-Vorschau');
    this.iframe.setAttribute('frameborder', '0');
    this.iframe.setAttribute('scrolling', 'no');
    this.root.append(this.iframe);

    const doc = this.iframe.contentDocument || this.iframe.contentWindow?.document;
    if (!doc) {
      return;
    }

    const stylesheets = collectPreviewStylesheets();
    const linkTags = stylesheets.map(href => `<link rel="stylesheet" href="${escapeAttr(href)}">`).join('\n');

    doc.open();
    doc.write(
      `<!DOCTYPE html><html><head><meta charset="utf-8">\n${linkTags}\n<style>${IFRAME_INLINE_STYLES}</style></head>` +
      `<body class="marketing-scope cms-page-render marketing-page" data-preview-intent="${escapeAttr(this.intent)}">` +
      `<div class="page-preview-surface"></div></body></html>`
    );
    doc.close();

    this.iframeDoc = doc;
    this.surface = doc.querySelector('.page-preview-surface');

    this._setupHeightSync();
  }

  _syncThemeToIframe() {
    if (!this.iframeDoc?.documentElement) {
      return;
    }
    const theme = this.root.dataset.theme || '';
    const html = this.iframeDoc.documentElement;
    const body = this.iframeDoc.body;
    if (theme) {
      html.dataset.theme = theme;
      if (body) {
        body.dataset.theme = theme;
      }
    } else {
      delete html.dataset.theme;
      if (body) {
        delete body.dataset.theme;
      }
    }
    const hc = this.root.classList.contains('high-contrast');
    html.classList.toggle('high-contrast', hc);
    if (body) {
      body.classList.toggle('high-contrast', hc);
    }
  }

  _observeTheme() {
    this._syncThemeToIframe();
    this._themeObserver = new MutationObserver(() => this._syncThemeToIframe());
    this._themeObserver.observe(this.root, { attributes: true, attributeFilter: ['data-theme', 'class'] });
  }

  _setupHeightSync() {
    if (!this.iframeDoc?.body) {
      return;
    }

    const syncHeight = () => {
      if (!this.iframe || !this.iframeDoc) {
        return;
      }
      const height = this.iframeDoc.documentElement?.scrollHeight || this.iframeDoc.body?.scrollHeight || 0;
      if (height > 0) {
        this.iframe.style.height = height + 'px';
      }
    };

    const IframeResizeObserver = this.iframe.contentWindow?.ResizeObserver;
    if (IframeResizeObserver) {
      this._heightObserver = new IframeResizeObserver(syncHeight);
      this._heightObserver.observe(this.iframeDoc.body);
    }

    this._syncIframeHeight = syncHeight;
    syncHeight();
  }

  destroy() {
    if (this.iframeDoc) {
      this.iframeDoc.removeEventListener('click', this.handleClick, true);
      if (this.surface) {
        this.surface.removeEventListener('click', this.handleEditableClick);
      }
    }
    this.finishInlineEdit(false);
    if (typeof this.cleanupEffects === 'function') {
      this.cleanupEffects();
      this.cleanupEffects = null;
    }
    if (this._heightObserver) {
      this._heightObserver.disconnect();
      this._heightObserver = null;
    }
    if (this._themeObserver) {
      this._themeObserver.disconnect();
      this._themeObserver = null;
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
    const namespace = resolvePreviewNamespace(this.root);
    const appearance = resolveNamespaceAppearance(namespace, this.appearance || window.pageAppearance || {});
    applyNamespaceDesign(this.surface, namespace, appearance);

    const html = renderPage(Array.isArray(this.visibleBlocks) ? this.visibleBlocks : [], {
      rendererMatrix: RENDERER_MATRIX,
      context: 'preview',
      appearance,
      page: this.pageContext,
    });
    this.surface.innerHTML = html;
    this.applySelectionHighlight();
    const mode = this.intent === 'preview' ? 'preview' : (this.intent === 'design' ? 'design-preview' : 'edit');
    const effects = initEffects(this.surface, { namespace, mode });
    this.cleanupEffects = effects?.destroy || null;

    if (this._syncIframeHeight) {
      requestAnimationFrame(() => this._syncIframeHeight());
    }
  }

  setIntent(intent = 'edit') {
    const allowed = ['edit', 'preview', 'design'];
    const normalized = allowed.includes(intent) ? intent : 'edit';
    if (this.intent === normalized) {
      return;
    }
    this.intent = normalized;

    if (this.iframeDoc?.body) {
      this.iframeDoc.body.dataset.previewIntent = normalized;
    }

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

    let top = 0;
    let el = target;
    const boundary = this.iframeDoc?.body || null;
    while (el && el !== boundary) {
      top += el.offsetTop;
      el = el.offsetParent;
    }

    this.root.scrollTo({
      top: Math.max(0, top - 8),
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

    const iframeWindow = this.iframe?.contentWindow;
    const selection = iframeWindow?.getSelection?.() || window.getSelection?.();
    if (selection && typeof selection.removeAllRanges === 'function' && editableElement.firstChild) {
      const ownerDoc = editableElement.ownerDocument || document;
      const range = ownerDoc.createRange();
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
