-- Ensure the calServer marketing page exists in its own namespace and is used as the startpage
INSERT INTO pages (namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source, startpage_domain, is_startpage)
SELECT
    'calserver' AS namespace,
    slug,
    title,
    content,
    type,
    parent_id,
    sort_order,
    status,
    COALESCE(NULLIF(language, ''), 'de') AS language,
    content_source,
    NULL AS startpage_domain,
    1 AS is_startpage
FROM pages
WHERE namespace = 'default'
  AND slug = 'calserver'
ON CONFLICT(namespace, slug) DO UPDATE SET
    title = excluded.title,
    content = excluded.content,
    type = excluded.type,
    parent_id = excluded.parent_id,
    sort_order = excluded.sort_order,
    status = excluded.status,
    language = excluded.language,
    content_source = excluded.content_source,
    startpage_domain = excluded.startpage_domain,
    is_startpage = excluded.is_startpage;
