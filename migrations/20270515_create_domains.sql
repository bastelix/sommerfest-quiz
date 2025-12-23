-- Create a dedicated table for admin-managed domains with optional namespace assignment
CREATE TABLE IF NOT EXISTS domains (
    id SERIAL PRIMARY KEY,
    host TEXT NOT NULL,
    normalized_host TEXT NOT NULL UNIQUE,
    namespace TEXT,
    label TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE OR REPLACE FUNCTION set_domains_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_domains_updated_at ON domains;
CREATE TRIGGER trg_domains_updated_at
BEFORE UPDATE ON domains
FOR EACH ROW
EXECUTE FUNCTION set_domains_updated_at();

-- Seed from legacy marketing domains and start page mappings
INSERT INTO domains (host, normalized_host, namespace, label, is_active)
SELECT host, normalized_host, NULL, label, TRUE
FROM marketing_domains
ON CONFLICT (normalized_host) DO UPDATE SET
    host = EXCLUDED.host,
    label = COALESCE(EXCLUDED.label, domains.label);

INSERT INTO domains (host, normalized_host, namespace, label, is_active)
SELECT
    domain AS host,
    domain AS normalized_host,
    CASE
        WHEN start_page LIKE '%:%' THEN lower(trim(split_part(start_page, ':', 1)))
        ELSE NULL
    END AS namespace,
    NULL AS label,
    TRUE
FROM domain_start_pages
ON CONFLICT (normalized_host) DO UPDATE SET
    namespace = COALESCE(EXCLUDED.namespace, domains.namespace);
