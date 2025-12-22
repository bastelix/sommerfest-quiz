-- Add localized privacy URLs to project settings.
ALTER TABLE project_settings ADD COLUMN IF NOT EXISTS privacy_url_de TEXT;
ALTER TABLE project_settings ADD COLUMN IF NOT EXISTS privacy_url_en TEXT;

UPDATE project_settings
SET privacy_url_de = privacy_url
WHERE privacy_url_de IS NULL AND privacy_url IS NOT NULL;

UPDATE project_settings
SET privacy_url_en = privacy_url
WHERE privacy_url_en IS NULL AND privacy_url IS NOT NULL;
