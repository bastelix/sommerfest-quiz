-- Create table for storing mail provider configurations managed via the admin UI
CREATE TABLE IF NOT EXISTS mail_providers (
    id SERIAL PRIMARY KEY,
    provider_name VARCHAR(64) NOT NULL UNIQUE,
    api_key TEXT,
    list_id TEXT,
    smtp_host TEXT,
    smtp_user TEXT,
    smtp_pass TEXT,
    smtp_port INTEGER,
    smtp_encryption VARCHAR(16),
    active BOOLEAN NOT NULL DEFAULT FALSE,
    settings JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE OR REPLACE FUNCTION set_mail_providers_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_mail_providers_updated_at ON mail_providers;
CREATE TRIGGER trg_mail_providers_updated_at
BEFORE UPDATE ON mail_providers
FOR EACH ROW
EXECUTE FUNCTION set_mail_providers_updated_at();
