# Page editor bootstrap investigation

## Bootstrap entry point
The marketing page editor initializes from `initPagesModule` in `public/js/tiptap-pages.js`, which wires up the theme toggle, page editors, selection toggles, page tree, and related UI concerns. The module is executed on `DOMContentLoaded` (or immediately if the document is already ready).【F:public/js/tiptap-pages.js†L3781-L3798】

## Synchronous exception during initialization
When the block editor is enabled (`PAGE_EDITOR_MODE === 'blocks'`), `ensurePageEditorInitialized` constructs a `BlockContentEditor` instance for the current form. The constructor immediately calls `setContent`, which normalizes each block through `sanitizeBlock`. `sanitizeBlock` enforces the SectionStyle contract; missing required fields such as `layout` in `meta.sectionStyle` triggers `validateBlockContract` to return `valid: false`, which then throws an error. The error bubbles back to the `new BlockContentEditor(...)` call. Although `ensurePageEditorInitialized` wraps the instantiation in a `try`/`catch`, the thrown error prevents the editor instance from being stored and the initialization function exits early.【F:public/js/tiptap-pages.js†L1692-L1707】【F:public/js/components/block-content-editor.js†L1105-L1155】【F:public/js/components/block-contract.js†L1050-L1076】

## Coupling of page tree and editor bootstrap
`initPagesModule` executes `initPageEditors()` before `initPageTree()`. A synchronous exception during editor setup stops the remainder of the module from running, so the page tree never mounts when block initialization fails. This means a single invalid page payload can prevent both the editor shell and the navigation tree from rendering.【F:public/js/tiptap-pages.js†L3781-L3792】

## Legacy pages missing `sectionStyle.layout`
The new SectionStyle contract requires `layout`. `validateSectionStyle` returns `false` when the layout is absent, which causes `validateBlockContract` to fail and `sanitizeBlock` to throw. Legacy pages that persisted `meta.sectionStyle` without a layout now trigger this runtime exception during bootstrap, blocking the entire editor UI instead of isolating the issue to the affected page.【F:public/js/components/block-contract.js†L1050-L1076】【F:public/js/components/block-content-editor.js†L1146-L1153】

## Minimal fix strategy (conceptual)
- Keep the strict SectionStyle contract—do not reintroduce defaults or relax validation.
- Isolate validation failures per page: capture schema/normalization errors when creating the `BlockContentEditor` and surface them on that page’s form (e.g., show a validation panel and keep the form selectable), but allow `initPagesModule` to continue to the page tree bootstrap.
- Ensure `initPagesModule` guards each bootstrap step so one invalid page cannot stop the tree or shell from mounting; render invalid pages in the tree with an explicit error state so editors can repair or migrate them.
