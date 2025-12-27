# Block editor live preview

The block editor remains the single source of truth for page content. Its serialized JSON is read via `getContent()` and passed into the UIkit page renderer in `preview` mode. The renderer emits HTML with `data-block-id` markers, which the preview canvas listens to for selection and hover affordances.

Selection stays in sync through the shared `data-block-id` values:
- Clicking a rendered block calls `selectBlock` on the editor.
- When the editor changes selection, the preview re-renders with `highlightBlockId` so the matching block is emphasized.

No renderer or editor internals are modified; the preview wraps the editor output and uses composition to keep both layers isolated.
