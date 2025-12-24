ALTER TABLE pages ADD COLUMN IF NOT EXISTS is_startpage BOOLEAN NOT NULL DEFAULT FALSE;

UPDATE pages
SET is_startpage = TRUE
FROM marketing_page_menu_items AS m
WHERE m.page_id = pages.id
  AND m.is_startpage = TRUE;

CREATE UNIQUE INDEX IF NOT EXISTS pages_namespace_startpage_idx
    ON pages (namespace)
    WHERE is_startpage = TRUE;
