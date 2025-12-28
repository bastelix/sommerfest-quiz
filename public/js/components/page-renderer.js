import { renderBlock } from './block-renderer-matrix.js';

export function renderPage(blocks = []) {
  return (Array.isArray(blocks) ? blocks : [])
    .map(block => renderBlock(block))
    .join('\n');
}

export const PageRenderer = { renderPage };
