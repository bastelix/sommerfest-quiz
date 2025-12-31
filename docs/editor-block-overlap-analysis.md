# Page editor block overlap investigation

## DOM structure
- The block editor renders a flex container (`[data-editor-root]`) with a control bar and a `.content-editor-body` that holds the block list and the active block editor.
- Blocks in the list are rendered as `<li data-block-row>` children of a `<ul>` inside an `<aside data-block-list>`, all appended directly into the flex container. No absolute positioning is used when building the DOM.

## Layout & positioning
- `.content-editor-body` renders the block list and editor panel as flex siblings with explicit gaps and `min-width` constraints. The original `flex-wrap: wrap` on this container allowed the two columns to drop to a second line under horizontal pressure, causing the editor panel to slide beneath the block list and visually overlap block rows. Blocks themselves remain in normal flow; the overlap comes from the container permitting wrap.
- Row styling for `[data-block-row]` is purely flex + borders; no `position`, `transform`, or negative margins that would remove items from flow.

## Parent height / collapse
- Both `.content-editor-body` and `[data-block-editor]` explicitly set `min-height: 0`, preventing flex overflow/collapse within the editor pane. No ancestor uses `height: 0`, `overflow: hidden`, or `display: contents`.

## Rendering lifecycle
- Each `render()` call clears the root (`this.root.innerHTML = ''`) and destroys rich-text instances before rebuilding the block list, avoiding duplicate block nodes that might stack.

## Preview separation
- The preview pane is created in `ensurePreviewSlots()` and only inserted into the workspace when the editor mode is `preview`/`design`; in `edit` mode the workspace contains only the editor pane. The preview canvas lives in its own sibling container, not overlaying the editor content.

### Outcome
Overlap originates from the container-level layout: the two-column `.content-editor-body` was allowed to wrap, stacking the editor panel under the block list instead of maintaining a single row. Preventing wrap keeps both columns in one line and removes the overlap without changing block components.

## Async height & flex behavior check
- The two-column editor surface (`.content-editor-body`) now enforces a single flex row (`flex-wrap: nowrap`) with explicit gaps and no fixed heights; both flex items (`[data-block-list]`, `[data-block-editor]`) size themselves to their content. There is no `height` constraint or `overflow` rule that would suppress growth when TipTap recalculates text heights or when preview assets load asynchronously.【F:public/css/main.css†L1106-L1147】【F:public/css/main.css†L2192-L2195】
- The surrounding preview workspace uses `align-items: stretch` by default, only relaxing to `flex-start` in preview mode; containers also set `min-width: 0` to avoid flex overflow. With wrapping disabled on `.content-editor-body`, there is no need for additional stretch enforcement to prevent overlap.【F:public/css/main.css†L2239-L2268】
- No delayed measurement hooks (ResizeObserver/requestAnimationFrame) exist around the block list or editor pane, so there is no missing reflow trigger after images, fonts, or TipTap extensions change height; the editor rebuilds DOM synchronously during `render()` before preview sync occurs.【F:public/js/components/block-content-editor.js†L1430-L1468】【F:public/js/tiptap-pages.js†L480-L574】

### Root cause & minimal fix
Root cause: the editor container permitted wrapping, causing the two columns to stack and overlap when horizontal space tightened. Fix: force `.content-editor-body` to `flex-wrap: nowrap` so the block list and editor panel remain side-by-side in a single row at all viewport widths.
