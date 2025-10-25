-- Add curated team name filter settings to event config
ALTER TABLE config ADD COLUMN IF NOT EXISTS random_name_domains TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS random_name_tones TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS random_name_buffer INTEGER DEFAULT 0;
UPDATE config SET random_name_buffer = COALESCE(random_name_buffer, 0);
