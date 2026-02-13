-- Ensure project_settings table exists before migrations that alter it.
-- The canonical CREATE TABLE lives in 20270420_create_project_settings.sql,
-- but 20260211 attempts to ALTER the table and runs first (filename sort order).
-- This migration creates the table early so the ALTER succeeds on fresh databases.
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
