-- Add namespace-wide effects policy fields
ALTER TABLE config ADD COLUMN IF NOT EXISTS effects_profile TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS slider_profile TEXT;
