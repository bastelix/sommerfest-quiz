-- Create namespace-level project settings storage.
CREATE TABLE IF NOT EXISTS project_settings (
    namespace TEXT PRIMARY KEY,
    cookie_consent_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    cookie_storage_key TEXT,
    cookie_banner_text TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
