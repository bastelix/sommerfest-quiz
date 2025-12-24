ALTER TABLE marketing_page_menu_items
    ADD COLUMN IF NOT EXISTS is_startpage BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS marketing_page_menu_items_start_idx
    ON marketing_page_menu_items(namespace, locale)
    WHERE is_startpage = TRUE;
