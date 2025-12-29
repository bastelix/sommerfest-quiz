-- Registry for wildcard TLS zones managed by the application
CREATE TABLE IF NOT EXISTS certificate_zones (
    zone TEXT PRIMARY KEY,
    provider TEXT NOT NULL DEFAULT 'hetzner',
    wildcard_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    status TEXT NOT NULL DEFAULT 'pending',
    last_issued_at TIMESTAMPTZ,
    last_error TEXT
);

-- Track zones directly on managed domains
ALTER TABLE domains ADD COLUMN IF NOT EXISTS zone TEXT;
UPDATE domains SET zone = normalized_host WHERE zone IS NULL;
ALTER TABLE domains ALTER COLUMN zone SET NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_domains_zone_normalized ON domains (normalized_host, zone);

CREATE INDEX IF NOT EXISTS idx_certificate_zones_status ON certificate_zones (status);
