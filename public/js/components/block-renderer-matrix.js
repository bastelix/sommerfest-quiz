import { ACTIVE_BLOCK_TYPES, BLOCK_TYPES, DEPRECATED_BLOCK_MAP, getBlockVariants, normalizeBlockContract, normalizeBlockVariant, validateBlockContract } from './block-contract.js';
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
  return renderer(block, { context });
}

function renderBlockError(block, error) {
  const blockId = block && typeof block === 'object' && 'id' in block ? escapeAttribute(block.id) : 'unknown';
  const type = block && typeof block === 'object' && 'type' in block ? escapeAttribute(block.type) : 'unknown';
  const variant = block && typeof block === 'object' && 'variant' in block ? escapeAttribute(block.variant) : 'unknown';
  const reason = error && error.message ? escapeHtml(error.message) : 'Unknown rendering error';

  return `<section data-block-id="${blockId}" data-block-type="${type}" data-block-variant="${variant}" class="block-render-error"><!-- render_error: ${reason} --></section>`;
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
