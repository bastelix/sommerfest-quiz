-- Add dedicated sponsor dashboard modules configuration
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_sponsor_modules TEXT;
