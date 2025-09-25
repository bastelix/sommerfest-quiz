CREATE TABLE IF NOT EXISTS domain_start_pages (
    domain TEXT PRIMARY KEY,
    start_page TEXT NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE FUNCTION trg_domain_start_pages_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_domain_start_pages_update ON domain_start_pages;
CREATE TRIGGER trg_domain_start_pages_update
    BEFORE UPDATE ON domain_start_pages
    FOR EACH ROW
    EXECUTE FUNCTION trg_domain_start_pages_updated_at();
