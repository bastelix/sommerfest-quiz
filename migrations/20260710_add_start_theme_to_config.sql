-- Allow events to define their default theme
ALTER TABLE config ADD COLUMN IF NOT EXISTS startTheme TEXT;
