# Page JSON transfer format

Manual export/import of block-based pages uses a deterministic JSON file with the following structure:

```json
{
  "meta": {
    "namespace": "marketing",
    "slug": "calserver",
    "title": "CalServer",
    "exportedAt": "2024-03-31T12:00:00+00:00",
    "schemaVersion": "block-contract-v1"
  },
  "blocks": [
    { "id": "...", "type": "hero", "variant": "default", "data": { /* ... */ } }
  ]
}
```

Key rules:

- `schemaVersion` must match the current block contract (`block-contract-v1`).
- `meta.slug` has to match the target page when importing; imports never create new pages.
- `meta.namespace` is ignored during import. The namespace is derived from the request query string so that exports can be reused across namespaces.
- Only the `blocks` array is written during import; page identity, permissions, and relations remain unchanged.
- Block payloads must validate against `public/js/components/block-contract.schema.json`.
- Exports are read-only and do not alter content.

Files are downloaded as `content/{namespace}/{slug}.page.json` via `/admin/pages/{slug}/export` and uploaded back to `/admin/pages/{slug}/import` with the matching `namespace` query parameter.
