import { ACTIVE_BLOCK_TYPES, BLOCK_TYPES, DEPRECATED_BLOCK_MAP, getBlockVariants, normalizeBlockVariant, validateBlockContract } from './block-contract.js';
import { RENDERER_MATRIX, escapeAttribute, escapeHtml } from './block-renderer-matrix-data.js';

function applyLegacyMapping(block) {
  const mapping = DEPRECATED_BLOCK_MAP[block.type];
  if (!mapping) {
    return block;
  }
  return { ...block, type: mapping.replacement.type, variant: mapping.replacement.variant };
}

function assertRenderable(block) {
  const normalized = applyLegacyMapping(block);
  const validation = validateBlockContract(normalized);
  if (!validation.valid) {
    throw new Error(validation.reason || 'Block contract validation failed');
  }
  const variants = RENDERER_MATRIX[normalized.type];
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

export function renderBlockStrict(block) {
  const renderer = assertRenderable(block);
  return renderer(block);
}

function renderBlockError(block, error) {
  const blockId = block && typeof block === 'object' && 'id' in block ? escapeAttribute(block.id) : 'unknown';
  const type = block && typeof block === 'object' && 'type' in block ? escapeAttribute(block.type) : 'unknown';
  const variant = block && typeof block === 'object' && 'variant' in block ? escapeAttribute(block.variant) : 'unknown';
  const reason = error && error.message ? escapeHtml(error.message) : 'Unknown rendering error';

  return `<section data-block-id="${blockId}" data-block-type="${type}" data-block-variant="${variant}" class="block-render-error"><!-- render_error: ${reason} --></section>`;
}

export function renderBlockSafe(block) {
  try {
    return renderBlockStrict(block);
  } catch (error) {
    return renderBlockError(block, error);
  }
}

export function renderBlock(block) {
  return renderBlockSafe(block);
}

export function renderPage(blocks = []) {
  return (Array.isArray(blocks) ? blocks : [])
    .map(block => renderBlockSafe(block))
    .join('\n');
}

export function listSupportedBlocks() {
  return BLOCK_TYPES.reduce((accumulator, type) => {
    const variants = Object.keys(RENDERER_MATRIX[type] || {});
    if (variants.length > 0 && (!DEPRECATED_BLOCK_MAP[type] || BLOCK_TYPES.includes(type))) {
      accumulator[type] = variants;
    }
    return accumulator;
  }, {});
}

export function listSelectableBlocks() {
  return ACTIVE_BLOCK_TYPES.reduce((accumulator, type) => {
    const variants = Object.keys(RENDERER_MATRIX[type] || {});
    if (variants.length > 0) {
      accumulator[type] = variants;
    }
    return accumulator;
  }, {});
}

export { RENDERER_MATRIX };
