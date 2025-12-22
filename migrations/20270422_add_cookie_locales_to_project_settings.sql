-- Add localized cookie banner copy and vendor flags to project settings.
ALTER TABLE project_settings ADD COLUMN IF NOT EXISTS cookie_banner_text_de TEXT;
ALTER TABLE project_settings ADD COLUMN IF NOT EXISTS cookie_banner_text_en TEXT;
ALTER TABLE project_settings ADD COLUMN IF NOT EXISTS cookie_vendor_flags JSONB DEFAULT '{}'::jsonb;

UPDATE project_settings
SET cookie_banner_text_de = cookie_banner_text
WHERE cookie_banner_text_de IS NULL AND cookie_banner_text IS NOT NULL;
