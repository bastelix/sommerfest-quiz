import { renderBlockSafe } from './block-renderer-matrix.js';

const PREVIEW_CONTEXT = 'preview';

function resolveContext(context) {
  return context === PREVIEW_CONTEXT ? PREVIEW_CONTEXT : 'frontend';
}

export function renderPage(blocks = [], options = {}) {
  const context = resolveContext(options.context);
  const renderOptions = { ...options, context };
  return (Array.isArray(blocks) ? blocks : [])
    .map(block => renderBlockSafe(block, renderOptions))
    .join('\n');
}

export const PageRenderer = { renderPage };
