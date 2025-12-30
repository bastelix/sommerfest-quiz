import { renderPage as renderWithMatrix } from './block-renderer-matrix.js';

// Thin compatibility wrapper that forwards all rendering to the shared
// block renderer matrix so preview and frontend stay in sync.
export const renderPage = (blocks = [], options = {}) => renderWithMatrix(blocks, options);

export const PageRenderer = { renderPage };
