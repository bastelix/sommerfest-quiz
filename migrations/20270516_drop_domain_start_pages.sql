-- Retire legacy domain start page storage in favor of the domains table
DROP TRIGGER IF EXISTS trg_domain_start_pages_update ON domain_start_pages;
DROP FUNCTION IF EXISTS trg_domain_start_pages_updated_at();
DROP TABLE IF EXISTS domain_start_pages;
