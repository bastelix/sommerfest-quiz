# Page Renderer (UIkit-ready, editor-agnostic)

The renderer converts the editor's block JSON into UIkit 3 markup without coupling to editor logic. It stays deterministic and stateless: `renderPage` only consumes the provided block array and options, never reading from DOM state or mutating input objects.

## API

```js
import { renderPage } from '/js/components/page-renderer.js';

const html = renderPage(blocks, {
  mode: 'frontend' | 'preview',
  highlightBlockId: 'optional-block-id',
  resolveAssetUrl: id => `/uploads/${id}` // optional asset resolver
});
```

### Block-specific renderers

Each block type is handled by a dedicated function that emits UIkit markup and the required `data-block-id` attribute:

- `renderHero(block, context)`
- `renderText(block, context)`
- `renderFeatureList(block, context)`
- `renderTestimonial(block, context)`

## Usage examples

### Preview rendering inside the editor

```js
import { renderPage } from '/js/components/page-renderer.js';

const previewRoot = document.querySelector('[data-preview]');
previewRoot.innerHTML = renderPage(page.blocks, {
  mode: 'preview',
  highlightBlockId: editorState.selectedBlockId,
  resolveAssetUrl: assetId => assetService.getUrl(assetId)
});
```

* Blocks are wrapped with light outlines for context.
* The selected block can be highlighted without changing editor code.

### Frontend rendering

```js
import { renderPage } from '/js/components/page-renderer.js';

document.querySelector('#page-root').innerHTML = renderPage(page.blocks, {
  mode: 'frontend',
  resolveAssetUrl: assetId => cdn.buildUrl(assetId)
});
```

* Outputs clean UIkit markup only.
* No editor CSS or state is required.

## Isolation

- **No editor dependency:** The renderer lives in `public/js/components/page-renderer.js` and exposes pure functions. It does not import TipTap or editor modules.
- **UIkit confined to output:** UIkit classes are added only to the generated HTML; stored block JSON remains framework-agnostic.
- **Stateless + deterministic:** Functions return strings derived from inputs. Highlighting and outlines are controlled via `options`, avoiding global state.
