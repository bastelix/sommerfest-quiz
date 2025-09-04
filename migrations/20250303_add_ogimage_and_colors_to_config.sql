-- Add ogImagePath and colors columns to config table
ALTER TABLE config ADD COLUMN IF NOT EXISTS ogImagePath TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS colors TEXT;
