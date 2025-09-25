ALTER TABLE page_seo_config ADD COLUMN IF NOT EXISTS domain TEXT;
ALTER TABLE page_seo_config_history ADD COLUMN IF NOT EXISTS domain TEXT;

-- Drop existing unique constraint on slug to allow per-domain uniqueness.
ALTER TABLE page_seo_config DROP CONSTRAINT IF EXISTS page_seo_config_slug_key;
DROP INDEX IF EXISTS idx_page_seo_config_slug;

-- Ensure existing rows have a deterministic domain value for uniqueness checks.
UPDATE page_seo_config SET domain = COALESCE(domain, '') WHERE domain IS NULL;

-- Enforce uniqueness on the combination of domain and slug (treat NULL as empty string).
CREATE UNIQUE INDEX IF NOT EXISTS idx_page_seo_config_domain_slug
    ON page_seo_config (COALESCE(domain, ''), slug);
