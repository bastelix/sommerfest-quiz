ALTER TABLE marketing_page_menu_items
    ADD COLUMN IF NOT EXISTS parent_id INTEGER REFERENCES marketing_page_menu_items(id) ON DELETE CASCADE;

ALTER TABLE marketing_page_menu_items
    ADD COLUMN IF NOT EXISTS layout TEXT NOT NULL DEFAULT 'link';

ALTER TABLE marketing_page_menu_items
    ADD COLUMN IF NOT EXISTS detail_title TEXT;

ALTER TABLE marketing_page_menu_items
    ADD COLUMN IF NOT EXISTS detail_text TEXT;

ALTER TABLE marketing_page_menu_items
    ADD COLUMN IF NOT EXISTS detail_subline TEXT;

ALTER TABLE marketing_page_menu_items
    ADD COLUMN IF NOT EXISTS is_startpage BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS marketing_page_menu_items_parent_idx
    ON marketing_page_menu_items(parent_id);
