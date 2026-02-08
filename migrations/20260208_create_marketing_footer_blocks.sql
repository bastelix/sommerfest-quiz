-- Create footer blocks table for flexible footer content management
CREATE TABLE IF NOT EXISTS marketing_footer_blocks (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    namespace TEXT NOT NULL DEFAULT 'default',
    slot TEXT NOT NULL CHECK (slot IN ('footer_1', 'footer_2', 'footer_3')),
    type TEXT NOT NULL CHECK (type IN ('menu', 'text', 'social', 'contact', 'newsletter', 'html')),
    content JSONB NOT NULL DEFAULT '{}'::jsonb,
    position INTEGER NOT NULL DEFAULT 0,
    locale TEXT NOT NULL DEFAULT 'de',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS marketing_footer_blocks_slot_idx
    ON marketing_footer_blocks(namespace, slot, locale, position, id)
    WHERE is_active = TRUE;

CREATE INDEX IF NOT EXISTS marketing_footer_blocks_type_idx
    ON marketing_footer_blocks(type);

CREATE OR REPLACE FUNCTION update_marketing_footer_blocks_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_marketing_footer_blocks_updated_at ON marketing_footer_blocks;
CREATE TRIGGER trg_marketing_footer_blocks_updated_at
    BEFORE UPDATE ON marketing_footer_blocks
    FOR EACH ROW
    EXECUTE FUNCTION update_marketing_footer_blocks_updated_at();
