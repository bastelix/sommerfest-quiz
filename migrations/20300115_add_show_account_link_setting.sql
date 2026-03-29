-- Add show_account_link toggle to project_settings for the marketing topbar
ALTER TABLE project_settings ADD COLUMN IF NOT EXISTS show_account_link BOOLEAN NOT NULL DEFAULT FALSE;
