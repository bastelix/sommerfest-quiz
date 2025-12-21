-- Create menu item definitions for marketing pages
CREATE TABLE IF NOT EXISTS marketing_page_menu_items (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    namespace TEXT NOT NULL DEFAULT 'default',
    label TEXT NOT NULL,
    href TEXT NOT NULL,
    icon TEXT,
    position INTEGER NOT NULL DEFAULT 0,
    is_external BOOLEAN NOT NULL DEFAULT FALSE,
    locale TEXT NOT NULL DEFAULT 'de',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS marketing_page_menu_items_page_locale_idx
    ON marketing_page_menu_items(page_id, namespace, locale, position, id);

CREATE OR REPLACE FUNCTION update_marketing_page_menu_items_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_marketing_page_menu_items_updated_at ON marketing_page_menu_items;
CREATE TRIGGER trg_marketing_page_menu_items_updated_at
    BEFORE UPDATE ON marketing_page_menu_items
    FOR EACH ROW
    EXECUTE FUNCTION update_marketing_page_menu_items_updated_at();
