-- Demo page for CMS public API (calhelp namespace)

-- Ensure namespace exists
INSERT INTO namespaces (namespace, label, is_active)
VALUES ('calhelp', 'calHelp', TRUE)
ON CONFLICT (namespace) DO NOTHING;

-- Insert or update demo page
INSERT INTO pages (namespace, slug, title, content, type, status, language, is_startpage, content_source)
VALUES (
    'calhelp',
    'api-demo',
    'API Demo (Draft)',
    $CONTENT${
  "id": "api-demo",
  "meta": {
    "source": "api:v1",
    "notes": "Demo page created for testing the public CMS API.",
    "reviewed": false
  },
  "blocks": [
    {
      "id": "block-intro",
      "type": "rich_text",
      "variant": "prose",
      "data": {
        "body": "<h1>API Demo</h1><p>Diese Seite wurde automatisch angelegt, um <code>/api/v1/…</code> zu testen.</p><p>Status: <strong>Draft</strong></p>",
        "alignment": "start"
      },
      "tokens": {
        "background": "default",
        "spacing": "normal",
        "width": "normal"
      }
    }
  ]
}$CONTENT$,
    'marketing',
    'draft',
    'de',
    FALSE,
    'api:v1'
)
ON CONFLICT (namespace, slug) DO UPDATE SET
    title = EXCLUDED.title,
    content = EXCLUDED.content,
    type = EXCLUDED.type,
    status = EXCLUDED.status,
    language = EXCLUDED.language,
    is_startpage = EXCLUDED.is_startpage,
    content_source = EXCLUDED.content_source,
    updated_at = CURRENT_TIMESTAMP;
