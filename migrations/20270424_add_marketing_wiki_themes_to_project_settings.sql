ALTER TABLE project_settings
    ADD COLUMN IF NOT EXISTS marketing_wiki_themes JSONB DEFAULT '{}'::jsonb;
