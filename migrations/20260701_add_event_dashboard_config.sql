-- Add configurable dashboard settings and share tokens to event config
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_share_token TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_sponsor_token TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_modules TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_refresh_interval INTEGER;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_share_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_sponsor_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_info_text TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_media_embed TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_visibility_start TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_visibility_end TEXT;
