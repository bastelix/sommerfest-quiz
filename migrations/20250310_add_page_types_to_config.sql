-- Add page_types column to config table
ALTER TABLE config ADD COLUMN IF NOT EXISTS page_types JSONB;
