-- Add ticket submission configuration to project_settings
ALTER TABLE project_settings
ADD COLUMN IF NOT EXISTS ticket_public_submission BOOLEAN NOT NULL DEFAULT TRUE;
