-- Create menu definitions for marketing pages
CREATE TABLE IF NOT EXISTS marketing_menus (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    namespace TEXT NOT NULL DEFAULT 'default',
    label TEXT NOT NULL,
    locale TEXT NOT NULL DEFAULT 'de',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS marketing_menu_items (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    menu_id INTEGER NOT NULL REFERENCES marketing_menus(id) ON DELETE CASCADE,
    parent_id INTEGER REFERENCES marketing_menu_items(id) ON DELETE CASCADE,
    namespace TEXT NOT NULL DEFAULT 'default',
    label TEXT NOT NULL,
    href TEXT NOT NULL,
    icon TEXT,
    position INTEGER NOT NULL DEFAULT 0,
    is_external BOOLEAN NOT NULL DEFAULT FALSE,
    locale TEXT NOT NULL DEFAULT 'de',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    layout TEXT NOT NULL DEFAULT 'link',
    detail_title TEXT,
    detail_text TEXT,
    detail_subline TEXT,
    is_startpage BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS marketing_menu_assignments (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    menu_id INTEGER NOT NULL REFERENCES marketing_menus(id) ON DELETE CASCADE,
    page_id INTEGER REFERENCES pages(id) ON DELETE CASCADE,
    namespace TEXT NOT NULL DEFAULT 'default',
    slot TEXT NOT NULL,
    locale TEXT NOT NULL DEFAULT 'de',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS marketing_menu_items_menu_idx
    ON marketing_menu_items(menu_id, namespace, locale, position, id);

CREATE INDEX IF NOT EXISTS marketing_menu_items_parent_idx
    ON marketing_menu_items(parent_id);

CREATE INDEX IF NOT EXISTS marketing_menu_items_start_idx
    ON marketing_menu_items(namespace, locale)
    WHERE is_startpage = TRUE;

CREATE INDEX IF NOT EXISTS marketing_menu_assignments_menu_idx
    ON marketing_menu_assignments(menu_id);

CREATE INDEX IF NOT EXISTS marketing_menu_assignments_page_idx
    ON marketing_menu_assignments(page_id, namespace, slot, locale);

CREATE UNIQUE INDEX IF NOT EXISTS marketing_menu_assignments_page_unique_idx
    ON marketing_menu_assignments(namespace, page_id, slot, locale)
    WHERE page_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS marketing_menu_assignments_global_unique_idx
    ON marketing_menu_assignments(namespace, slot, locale)
    WHERE page_id IS NULL;

CREATE OR REPLACE FUNCTION update_marketing_menus_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION update_marketing_menu_items_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION update_marketing_menu_assignments_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_marketing_menus_updated_at ON marketing_menus;
CREATE TRIGGER trg_marketing_menus_updated_at
    BEFORE UPDATE ON marketing_menus
    FOR EACH ROW
    EXECUTE FUNCTION update_marketing_menus_updated_at();

DROP TRIGGER IF EXISTS trg_marketing_menu_items_updated_at ON marketing_menu_items;
CREATE TRIGGER trg_marketing_menu_items_updated_at
    BEFORE UPDATE ON marketing_menu_items
    FOR EACH ROW
    EXECUTE FUNCTION update_marketing_menu_items_updated_at();

DROP TRIGGER IF EXISTS trg_marketing_menu_assignments_updated_at ON marketing_menu_assignments;
CREATE TRIGGER trg_marketing_menu_assignments_updated_at
    BEFORE UPDATE ON marketing_menu_assignments
    FOR EACH ROW
    EXECUTE FUNCTION update_marketing_menu_assignments_updated_at();
