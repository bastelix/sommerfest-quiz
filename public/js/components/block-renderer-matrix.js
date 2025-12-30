import { ACTIVE_BLOCK_TYPES, BLOCK_TYPES, DEPRECATED_BLOCK_MAP, SECTION_APPEARANCE_PRESETS, getBlockVariants, normalizeBlockContract, normalizeBlockVariant, validateBlockContract } from './block-contract.js';
import { RENDERER_MATRIX as BASE_RENDERER_MATRIX, escapeAttribute, escapeHtml } from './block-renderer-matrix-data.js';

const RENDERER_MATRIX = { ...BASE_RENDERER_MATRIX, proof: BASE_RENDERER_MATRIX.proof };

const CONTEXTS = {
  FRONTEND: 'frontend',
  PREVIEW: 'preview'
};

function resolveContext(context) {
  return context === CONTEXTS.PREVIEW ? CONTEXTS.PREVIEW : CONTEXTS.FRONTEND;
}

function resolveRendererMatrix(rendererMatrix) {
  if (rendererMatrix && typeof rendererMatrix === 'object') {
    return rendererMatrix;
  }
  return RENDERER_MATRIX;
}

function applyLegacyMapping(block) {
  const mapping = DEPRECATED_BLOCK_MAP[block.type];
  if (!mapping) {
    return block;
  }
  return { ...block, type: mapping.replacement.type, variant: mapping.replacement.variant };
}

function assertRenderable(block, rendererMatrix) {
  const normalized = applyLegacyMapping(normalizeBlockContract(block));
  const validation = validateBlockContract(normalized);
  if (!validation.valid) {
    throw new Error(validation.reason || 'Block contract validation failed');
  }
  const variants = rendererMatrix[normalized.type];
  if (!variants) {
    throw new Error(`No renderer registered for type: ${normalized.type}`);
  }
  const normalizedVariant = normalizeBlockVariant(normalized.type, normalized.variant);
  const renderer = variants[normalizedVariant];
  if (!renderer) {
    const allowedVariants = getBlockVariants(normalized.type).join(', ');
    throw new Error(`Unsupported variant for ${normalized.type}: ${normalized.variant}. Allowed: ${allowedVariants}`);
  }
  return renderer;
}

export function renderBlockStrict(block, options = {}) {
  const rendererMatrix = resolveRendererMatrix(options.rendererMatrix);
  const renderer = assertRenderable(block, rendererMatrix);
  const context = resolveContext(options.context);
  const html = renderer(block, { context });
  return withPreviewStatus(block, html, context);
}

function renderBlockError(block, error) {
  const blockId = block && typeof block === 'object' && 'id' in block ? escapeAttribute(block.id) : 'unknown';
  const type = block && typeof block === 'object' && 'type' in block ? escapeAttribute(block.type) : 'unknown';
  const variant = block && typeof block === 'object' && 'variant' in block ? escapeAttribute(block.variant) : 'unknown';
  const reason = error && error.message ? escapeHtml(error.message) : 'Unknown rendering error';
  const appearance = SECTION_APPEARANCE_PRESETS.includes(block?.sectionAppearance)
    ? escapeAttribute(block.sectionAppearance)
    : 'default';

  return `<section data-block-id="${blockId}" data-block-type="${type}" data-block-variant="${variant}" data-appearance="${appearance}" class="section uk-section block-render-error"><!-- render_error: ${reason} --></section>`;
}

export function renderBlockSafe(block, options = {}) {
  try {
    return renderBlockStrict(block, options);
  } catch (error) {
    return renderBlockError(block, error);
  }
}

export function renderBlock(block, options = {}) {
  return renderBlockSafe(block, options);
}

export function renderPage(blocks = [], options = {}) {
  return (Array.isArray(blocks) ? blocks : [])
    .map(block => renderBlockSafe(block, options))
    .join('\n');
}

export function listSupportedBlocks(rendererMatrix = RENDERER_MATRIX) {
  const resolvedMatrix = resolveRendererMatrix(rendererMatrix);
  return BLOCK_TYPES.reduce((accumulator, type) => {
    const variants = Object.keys(resolvedMatrix[type] || {});
    if (variants.length > 0 && (!DEPRECATED_BLOCK_MAP[type] || BLOCK_TYPES.includes(type))) {
      accumulator[type] = variants;
    }
    return accumulator;
  }, {});
}

export function listSelectableBlocks(rendererMatrix = RENDERER_MATRIX) {
  const resolvedMatrix = resolveRendererMatrix(rendererMatrix);
  return ACTIVE_BLOCK_TYPES.reduce((accumulator, type) => {
    const variants = Object.keys(resolvedMatrix[type] || {});
    if (variants.length > 0) {
      accumulator[type] = variants;
    }
    return accumulator;
  }, {});
}

export { RENDERER_MATRIX };

const PREVIEW_STATUS = {
  INCOMPLETE: 'INCOMPLETE',
  LEGACY: 'LEGACY'
};

function resolvePreviewStatus(block) {
  const rawStatus = block?.meta?.status || block?.status;
  if (!rawStatus) {
    return null;
  }

  const normalized = String(rawStatus).toUpperCase();
  if (normalized === PREVIEW_STATUS.INCOMPLETE) {
    return PREVIEW_STATUS.INCOMPLETE;
  }
  if (normalized === PREVIEW_STATUS.LEGACY) {
    return PREVIEW_STATUS.LEGACY;
  }
  return null;
}

function buildStatusNotice(status, block) {
  if (status === PREVIEW_STATUS.INCOMPLETE) {
    return buildNoticeTemplate({
      alertClass: 'uk-alert-warning',
      icon: '⚠️',
      text: 'Dieser Abschnitt ist noch unvollständig.',
      actionLabel: 'Inhalt ergänzen',
      blockId: block?.id
    });
  }

  if (status === PREVIEW_STATUS.LEGACY) {
    return buildNoticeTemplate({
      alertClass: 'uk-alert-muted',
      icon: '🕘',
      text: 'Dieser Abschnitt nutzt ein veraltetes Layout.',
      actionLabel: 'Layout aktualisieren',
      blockId: block?.id
    });
  }

  return '';
}

function buildNoticeTemplate({ alertClass, icon, text, actionLabel, blockId }) {
  const action = blockId
    ? `<a class="uk-link-text uk-margin-small-left" href="#" data-preview-status-action="select" data-block-id="${escapeAttribute(blockId)}">${actionLabel}</a>`
    : '';

  return `<div class="preview-status"><div class="uk-alert ${alertClass} uk-padding-small uk-margin-remove-top uk-margin-small-bottom" data-uk-alert><span class="preview-status__icon" aria-hidden="true">${icon}</span><span class="uk-margin-small-left">${text}</span>${action ? `<span class="uk-margin-small-left">${action}</span>` : ''}</div></div>`;
}

function insertNoticeIntoSection(html, notice) {
  if (!notice) {
    return html;
  }

  const sectionStart = html.indexOf('>');
  if (sectionStart === -1) {
    return `${notice}${html}`;
  }

  return `${html.slice(0, sectionStart + 1)}${notice}${html.slice(sectionStart + 1)}`;
}

function withPreviewStatus(block, html, context) {
  if (context !== CONTEXTS.PREVIEW) {
    return html;
  }

  const status = resolvePreviewStatus(block);
  if (!status) {
    return html;
  }

  const notice = buildStatusNotice(status, block);
  return insertNoticeIntoSection(html, notice);
}
