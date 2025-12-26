ALTER TABLE project_settings
    ADD COLUMN IF NOT EXISTS header_logo_mode VARCHAR(20) NOT NULL DEFAULT 'text',
    ADD COLUMN IF NOT EXISTS header_logo_path VARCHAR(500),
    ADD COLUMN IF NOT EXISTS header_logo_alt VARCHAR(255);
