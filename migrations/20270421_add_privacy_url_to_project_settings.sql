-- Add privacy URL to project settings.
ALTER TABLE project_settings ADD COLUMN IF NOT EXISTS privacy_url TEXT;
