ALTER TABLE pages ADD COLUMN IF NOT EXISTS startpage_domain TEXT;

UPDATE pages
SET startpage_domain = NULL
WHERE startpage_domain = '';

DROP INDEX IF EXISTS pages_namespace_startpage_idx;

CREATE UNIQUE INDEX IF NOT EXISTS pages_namespace_domain_startpage_idx
    ON pages (namespace, COALESCE(startpage_domain, ''))
    WHERE is_startpage = TRUE;
