# Block Contract Compliance Audit

## Scope
Audit of block contract adherence across editor (`public/js/components/block-content-editor.js`) and renderer surfaces (`public/js/components/page-renderer.js`, `public/js/components/block-renderer-matrix.js`). Source of truth: `public/js/components/block-contract.schema.json` and supporting helpers.

## Part 1 – Block Contract Adherence (FAIL)
- **Contract bypass in editor:** Blocks are created and normalized without importing the shared contract; `type` is accepted as any string and unvalidated (`setContent`/`normalizeBlock`). `variant` is never present, making the required field optional in practice, and tokens are ignored. Free-form fields like `layout` are persisted even though they are not in the schema.【F:public/js/components/block-content-editor.js†L4-L85】【F:public/js/components/block-content-editor.js†L160-L205】
- **Renderer divergence:** `page-renderer.js` dispatches on a local map of raw strings, not the contract enum or renderer matrix, and silently returns an empty string for unknown types, creating implicit defaults and hiding errors.【F:public/js/components/page-renderer.js†L170-L190】
- **Contract-only coverage:** `block-renderer-matrix.js` is the only surface that validates against the contract, enforcing both type and variant; other surfaces are not bound to it.【F:public/js/components/block-renderer-matrix.js†L1-L113】
- **Schema requirements:** The schema mandates required `id`, `type`, `variant`, `data`, and enumerated variants per block type, plus constrained tokens; these requirements are not enforced by the editor or `page-renderer` paths.【F:public/js/components/block-contract.schema.json†L8-L160】

## Part 2 – Editor Compliance (FAIL)
- **Unsupported block types and missing variants:** The editor exposes a `text` block not present in the contract and omits variants for all blocks, so every persisted block violates the contract’s required `variant` field.【F:public/js/components/block-content-editor.js†L4-L85】
- **Unvalidated persistence:** `getContent` serializes state without schema validation, allowing arbitrary shapes and tokens outside the allowed set to be saved.【F:public/js/components/block-content-editor.js†L206-L213】
- **Implicit defaults:** Default factories inject editor-specific fields (`layout`, `cta.style`, `alignment`) that are not part of the contract or renderer matrix, creating drift and hidden assumptions.【F:public/js/components/block-content-editor.js†L29-L85】
- **Manual entry risks:** Rich text fields and inputs write directly into `data` without contract-aware guards, allowing manual entry of types, variants, or tokens via crafted input JSON passed to `setContent`, which bypasses validation and normalizes unknown types to `null` silently.【F:public/js/components/block-content-editor.js†L160-L205】

## Part 3 – Renderer Compliance (PARTIAL FAIL)
- **Renderer matrix (pass):** `block-renderer-matrix.js` uses the renderer matrix plus contract validation, rejecting unknown types/variants with explicit errors.【F:public/js/components/block-renderer-matrix.js†L64-L113】
- **Legacy renderer (fail):** `page-renderer.js` dispatches on raw type strings, has no variant awareness, and falls back to empty output on unknown types, masking contract violations and enabling implicit defaults. It also infers layout from `data.layout`, duplicating editor-specific knowledge instead of using the renderer matrix.【F:public/js/components/page-renderer.js†L111-L190】
- **Error handling gap:** Unknown variants are never checked in `page-renderer.js`; failures are silent rather than explicit.【F:public/js/components/page-renderer.js†L170-L190】

## Part 4 – Contract Drift Detection
- **Schema vs editor drift:** Editor exposes `text` blocks and `layout`/`cta.style` fields absent from the schema, and never sets required `variant` or tokens, ensuring persisted content does not match the contract.【F:public/js/components/block-content-editor.js†L29-L85】【F:public/js/components/block-contract.schema.json†L8-L160】
- **Renderer split-brain:** `page-renderer.js` supports only a subset of contract types and ignores variants, while `block-renderer-matrix.js` expects full contract adherence, indicating multiple dispatch paths with conflicting assumptions.【F:public/js/components/page-renderer.js†L170-L190】【F:public/js/components/block-renderer-matrix.js†L64-L113】

## Part 5 – Findings & Recommendations

| Section | Status | Severity | Finding | Remediation |
| --- | --- | --- | --- | --- |
| Contract adherence | FAIL | **CRITICAL** | Editor and `page-renderer` never validate against the shared contract; `variant` is omitted and free-form fields persist, violating schema requirements. | Route all block creation/loading through the contract helpers (`validateBlockContract`, schema) and require `variant`/tokens on every block before persistence. Reject or normalize inputs to schema-compliant shapes. |
| Editor compliance | FAIL | **CRITICAL** | Editor offers non-contract `text` type and adds layout/CTA style defaults; saving bypasses JSON Schema validation. | Limit selectable types/variants to those from the contract/renderer matrix, remove editor-only fields from persisted data, and validate blocks against the schema before serialization. |
| Renderer compliance | PARTIAL FAIL | **CRITICAL** | `page-renderer.js` uses raw string dispatch with silent fallbacks and infers layout from data, ignoring variants and renderer matrix. | Consolidate rendering through the deterministic renderer matrix and surface explicit errors for unknown types/variants instead of returning empty output. |
| Contract drift | FAIL | **WARNING** | Duplicate block definitions (`text` vs `rich_text`) and editor-only fields (`layout`, `cta.style`) risk ongoing divergence between schema, editor, and renderer. | Align schema, TypeScript definitions, editor options, and renderer matrix so all block shapes/variants originate from the single contract source and remove legacy/duplicate paths. |

