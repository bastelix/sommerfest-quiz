-- Add locale and strategy columns for AI-generated team names
ALTER TABLE config ADD COLUMN IF NOT EXISTS random_name_locale TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS random_name_strategy TEXT;

UPDATE config
SET random_name_locale = NULL
WHERE random_name_locale IS NOT NULL AND trim(random_name_locale) = '';

UPDATE config
SET random_name_strategy = 'ai'
WHERE random_name_strategy IS NULL OR trim(random_name_strategy) = '';
