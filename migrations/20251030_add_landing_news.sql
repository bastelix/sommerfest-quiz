-- Create landing page news table for marketing updates
CREATE TABLE IF NOT EXISTS landing_news (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    excerpt TEXT,
    content TEXT NOT NULL,
    published_at TIMESTAMPTZ,
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS landing_news_page_slug_idx
    ON landing_news(page_id, slug);

CREATE INDEX IF NOT EXISTS landing_news_page_published_idx
    ON landing_news(page_id, is_published, published_at DESC, id DESC);

CREATE INDEX IF NOT EXISTS landing_news_published_idx
    ON landing_news(is_published, published_at DESC, id DESC);

CREATE OR REPLACE FUNCTION update_landing_news_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_landing_news_updated_at ON landing_news;
CREATE TRIGGER trg_landing_news_updated_at
    BEFORE UPDATE ON landing_news
    FOR EACH ROW
    EXECUTE FUNCTION update_landing_news_updated_at();
