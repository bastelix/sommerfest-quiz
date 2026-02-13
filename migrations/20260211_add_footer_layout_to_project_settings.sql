-- Add footer_layout to project_settings for persisting the selected footer column layout
ALTER TABLE project_settings ADD COLUMN IF NOT EXISTS footer_layout TEXT NOT NULL DEFAULT 'equal';
