# Page Content Model – Phase 1

This document proposes a block-based content structure for the page editor. The focus is on a framework-agnostic JSON model that cleanly separates editor data from UIkit presentation.

## 1. Page JSON schema

```json
{
  "id": "string",                  // unique page identifier (slug or UUID)
  "locale": "string",             // locale code, e.g. "de-DE"
  "title": "string",              // internal page label for editors
  "blocks": [
    {
      "id": "uuid",               // unique per block
      "type": "string",           // block type discriminator
      "data": { /* see block schemas below */ }
    }
  ],
  "meta": {
    "version": "string",          // schema versioning for migrations
    "updatedAt": "ISO-8601 string",
    "updatedBy": "string"         // user id or name
  }
}
```

* Blocks remain ordered via their array position.
* `type` selects a strict data schema; unknown fields are rejected during validation.
* Layout and UIkit class decisions occur outside this JSON (in the preview/render layer).

## 2. Block schemas (examples)

### 2.1 Hero block

```json
{
  "id": "uuid",
  "type": "hero",
  "data": {
    "eyebrow": "string|null",         // short kicker text
    "headline": "html",               // TipTap HTML allowed
    "subheadline": "html|null",       // optional rich text
    "media": {
      "imageId": "string",            // asset reference, not a URL
      "alt": "string",
      "focalPoint": {
        "x": 0.5,                      // 0..1 values for crop guidance
        "y": 0.5
      }
    },
    "cta": {
      "label": "string",
      "href": "string",               // URL or route token
      "style": "primary|secondary"    // semantic intent only
    }
  }
}
```

### 2.2 Text block

```json
{
  "id": "uuid",
  "type": "text",
  "data": {
    "body": "html",                   // TipTap HTML; inline formatting only
    "alignment": "start|center|end"    // semantic alignment hint
  }
}
```

### 2.3 Feature list block

```json
{
  "id": "uuid",
  "type": "feature_list",
  "data": {
    "title": "html|null",             // optional intro
    "items": [
      {
        "id": "uuid",
        "icon": "string|null",       // token mapped to UI kit icon in render layer
        "title": "string",
        "description": "html"
      }
    ],
    "layout": "stacked|grid"          // semantic grouping, not CSS classes
  }
}
```

### 2.4 Testimonial block

```json
{
  "id": "uuid",
  "type": "testimonial",
  "data": {
    "quote": "html",
    "author": {
      "name": "string",
      "role": "string|null",
      "avatarId": "string|null"
    }
  }
}
```

## 3. Why this model is UIkit-safe and future-proof

* **No framework leakage:** Blocks store only semantic data and structured HTML for inline text. No `uk-*` classes, grid definitions, or breakpoint rules appear in content.
* **Render-layer mapping:** Visual choices (e.g., UIkit card vs. panel) are resolved in the preview/render layer via the `type` and semantic fields like `style` or `layout`. This keeps content portable if UIkit is replaced.
* **Strict schemas per block:** Each block’s `data` is explicitly defined and validated, preventing ad-hoc fields that encode layout hacks.
* **Versioned payloads:** The top-level `meta.version` allows migrations without breaking stored pages, ensuring long-term stability.
* **Preview isolation:** Because the editor layer never depends on UIkit CSS, UI changes in the UIkit layer cannot break editor usability; only the preview renderer consumes UIkit.

Next step: implement validation and minimal editor scaffolding that respects these schemas (not part of this phase).
