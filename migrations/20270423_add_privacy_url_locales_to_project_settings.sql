ALTER TABLE project_settings
    ADD COLUMN IF NOT EXISTS privacy_url_de TEXT,
    ADD COLUMN IF NOT EXISTS privacy_url_en TEXT;
