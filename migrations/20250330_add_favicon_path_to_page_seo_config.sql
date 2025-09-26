ALTER TABLE page_seo_config ADD COLUMN IF NOT EXISTS favicon_path TEXT;
ALTER TABLE page_seo_config_history ADD COLUMN IF NOT EXISTS favicon_path TEXT;
