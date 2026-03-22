ALTER TABLE project_settings
    ADD COLUMN IF NOT EXISTS header_topbar_style VARCHAR(20) NOT NULL DEFAULT 'auto';
