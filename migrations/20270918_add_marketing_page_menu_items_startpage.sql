ALTER TABLE marketing_page_menu_items
    ADD COLUMN IF NOT EXISTS is_startpage BOOLEAN NOT NULL DEFAULT FALSE;
