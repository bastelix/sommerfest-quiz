ALTER TABLE project_settings
    ADD COLUMN IF NOT EXISTS navigation_logo_mode TEXT DEFAULT 'text',
    ADD COLUMN IF NOT EXISTS navigation_logo_image TEXT,
    ADD COLUMN IF NOT EXISTS navigation_logo_alt TEXT;
