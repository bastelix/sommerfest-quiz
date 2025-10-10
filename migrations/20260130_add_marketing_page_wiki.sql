-- Create tables for marketing page wiki feature
CREATE TABLE IF NOT EXISTS marketing_page_wiki_settings (
    page_id INTEGER PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    menu_label VARCHAR(64),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS marketing_page_wiki_articles (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    slug TEXT NOT NULL,
    locale TEXT NOT NULL DEFAULT 'de',
    title TEXT NOT NULL,
    excerpt TEXT,
    editor_json JSONB,
    content_md TEXT NOT NULL,
    content_html TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published','archived')),
    sort_index INTEGER NOT NULL DEFAULT 0,
    published_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(page_id, locale, slug)
);

CREATE INDEX IF NOT EXISTS marketing_page_wiki_articles_page_locale_status_idx
    ON marketing_page_wiki_articles(page_id, locale, status, sort_index, published_at DESC);

CREATE TABLE IF NOT EXISTS marketing_page_wiki_versions (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    article_id INTEGER NOT NULL REFERENCES marketing_page_wiki_articles(id) ON DELETE CASCADE,
    editor_json JSONB,
    content_md TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by UUID
);

CREATE INDEX IF NOT EXISTS marketing_page_wiki_versions_article_idx
    ON marketing_page_wiki_versions(article_id, created_at DESC);

CREATE OR REPLACE FUNCTION update_marketing_page_wiki_articles_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_marketing_page_wiki_articles_updated_at ON marketing_page_wiki_articles;
CREATE TRIGGER trg_marketing_page_wiki_articles_updated_at
    BEFORE UPDATE ON marketing_page_wiki_articles
    FOR EACH ROW
    EXECUTE FUNCTION update_marketing_page_wiki_articles_updated_at();

CREATE OR REPLACE FUNCTION update_marketing_page_wiki_settings_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_marketing_page_wiki_settings_updated_at ON marketing_page_wiki_settings;
CREATE TRIGGER trg_marketing_page_wiki_settings_updated_at
    BEFORE UPDATE ON marketing_page_wiki_settings
    FOR EACH ROW
    EXECUTE FUNCTION update_marketing_page_wiki_settings_updated_at();
