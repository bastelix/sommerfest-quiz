-- Allow selecting a theme for the live dashboard
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_theme TEXT;
