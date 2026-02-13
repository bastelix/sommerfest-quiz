-- Ensure project_settings table exists before altering it.
-- This migration sorts before 20270420_create_project_settings.sql, so the table
-- must be created here to avoid "relation does not exist" errors on fresh databases.
CREATE TABLE IF NOT EXISTS project_settings (
    namespace TEXT PRIMARY KEY,
    cookie_consent_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    cookie_storage_key TEXT,
    cookie_banner_text TEXT,
    show_language_toggle BOOLEAN NOT NULL DEFAULT TRUE,
    show_theme_toggle BOOLEAN NOT NULL DEFAULT TRUE,
    show_contrast_toggle BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Add footer_layout to project_settings for persisting the selected footer column layout
ALTER TABLE project_settings ADD COLUMN IF NOT EXISTS footer_layout TEXT NOT NULL DEFAULT 'equal';
