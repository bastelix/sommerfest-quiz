# Block Editor Architecture (Phase: Block Editing Only)

This document defines the editor layer for working with the block-based page content model described in `docs/page_content_model.md`. Rendering and styling are explicitly out of scope; this focuses on how editors manipulate structured data.

## Goals and Non-Goals
- **Goals:** enable listing, selecting, reordering, and editing blocks while preserving IDs and schema fidelity. Provide minimal HTML structure for an editor UI without layout or visual design. Clarify where TipTap is permitted.
- **Non-Goals:** no UIkit usage, no production preview rendering, no layout encoding in content, and no merging of editor and preview concerns.

## Editor Architecture

### Block listing and selection
- The editor loads `page.blocks` (ordered array) and renders a read-only list of block rows showing type and a short label (e.g., `data.headline` text content) for quick scanning.
- Each block row has a **selection toggle** that sets `editorState.selectedBlockId`. Selection is single-block to keep editing focused; multi-select can be added later without changing the model.
- Keyboard navigation (Up/Down) cycles through rows; Enter toggles selection. Selection state is stored outside the block data to avoid contaminating content with UI state.

### Reordering
- Blocks remain ordered by their array index. Reordering uses drag handles or up/down controls that swap array positions.
- Moves operate on a cloned array to preserve immutability in state management. After reordering, the block `id` values stay untouched; only array order changes. Undo/redo stacks record the previous array order.

### Editing block data
- Each block type maps to a dedicated editor form that validates against its schema from `docs/page_content_model.md`.
- Editors use structured inputs for non-rich fields (text inputs, selects, asset pickers). No CSS or preview logic is embedded.
- Only inline rich-text fields defined as `html` in the schema use TipTap instances. All other fields use plain form elements to keep semantics clear.
- Validation runs per block on change and before saving, rejecting extra fields and enforcing required keys. Errors are shown in editor controls, not in content data.

### TipTap usage boundaries
- **Use TipTap:** fields declared as `html` in the model, such as `hero.data.headline`, `hero.data.subheadline`, `text.data.body`, `feature_list.data.title`, `feature_list.items[].description`, and `testimonial.data.quote`.
- **Do not use TipTap:** plain strings like CTA labels, URLs, icon tokens, alignment/layout enums, IDs, and asset references. These use standard form controls.

### Block ID preservation
- Each block carries a stable `id` (UUID). Editing never regenerates IDs unless explicitly duplicating a block.
- When duplicating, the editor copies the source block data but assigns a new `id`; nested collection items (e.g., `feature_list.items`) also receive new IDs to avoid collisions.
- Reordering, selection, and validation never mutate IDs. The save pipeline uses these IDs to map updates and support future migration/versioning.

## Minimal DOM Structure (unstyled)

The following HTML-only skeleton separates editor controls, block editing, and future preview.

```html
<div data-editor-root>
  <!-- Editor controls: page-level actions, add block -->
  <header data-editor-controls>
    <button type="button" data-action="add-block">Add block</button>
    <select data-action="insert-block-type">
      <option value="hero">Hero</option>
      <option value="text">Text</option>
      <option value="feature_list">Feature list</option>
      <option value="testimonial">Testimonial</option>
    </select>
  </header>

  <!-- Block list and selection -->
  <aside data-block-list>
    <ul>
      <li data-block-row data-block-id="{block.id}" aria-selected="false">
        <button type="button" data-action="select-block">Select</button>
        <span data-block-label>{block.type}</span>
        <button type="button" data-action="move-up">↑</button>
        <button type="button" data-action="move-down">↓</button>
      </li>
      <!-- repeat for each block -->
    </ul>
  </aside>

  <!-- Block editor panel (single selection) -->
  <section data-block-editor data-selected-block-id="{editorState.selectedBlockId}">
    <!-- Example: hero block form -->
    <form data-block-type="hero">
      <input name="eyebrow" type="text" />
      <div data-field="headline" data-richtext><!-- TipTap instance mounts here --></div>
      <div data-field="subheadline" data-richtext><!-- TipTap instance mounts here --></div>
      <input name="media.imageId" type="text" />
      <input name="media.alt" type="text" />
      <input name="media.focalPoint.x" type="number" step="0.01" min="0" max="1" />
      <input name="media.focalPoint.y" type="number" step="0.01" min="0" max="1" />
      <input name="cta.label" type="text" />
      <input name="cta.href" type="url" />
      <select name="cta.style">
        <option value="primary">Primary</option>
        <option value="secondary">Secondary</option>
      </select>
    </form>
    <!-- Additional forms render based on selected block type; TipTap only in html fields. -->
  </section>

  <!-- Future preview placeholder (kept separate, empty for now) -->
  <section data-preview-placeholder aria-hidden="true"></section>
</div>
```

## Separation of Concerns
- **Editor controls:** page-level actions (add, duplicate, delete), block list, selection, and movement. Lives in `data-editor-controls` and `data-block-list` regions. No rendering logic.
- **Block editing:** schema-driven forms per block type, mounted in `data-block-editor`. TipTap is isolated to `data-richtext` mounts for `html` fields.
- **Preview rendering:** intentionally empty in this phase; `data-preview-placeholder` reserves the hook for later without mixing concerns.

## Data Flow Summary
1. Load page JSON (per `docs/page_content_model.md`) into editor state.
2. Render block list from `blocks` array; selection sets `selectedBlockId`.
3. On reorder, swap array items; persist with IDs unchanged.
4. Editing uses controlled inputs bound to the selected block’s `data`; validation enforces schema per block type.
5. Save returns the same JSON shape with updated `blocks` order and data, preserving all block IDs and `meta` fields.
