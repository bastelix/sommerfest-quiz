import { renderBlockSafe } from './block-renderer-matrix.js';

export function renderPage(blocks = []) {
  return (Array.isArray(blocks) ? blocks : [])
    .map(block => renderBlockSafe(block))
    .join('\n');
}

export const PageRenderer = { renderPage };
