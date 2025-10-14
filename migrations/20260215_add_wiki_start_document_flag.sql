-- Add start document flag to marketing page wiki articles
ALTER TABLE marketing_page_wiki_articles
    ADD COLUMN IF NOT EXISTS is_start_document BOOLEAN NOT NULL DEFAULT FALSE;

CREATE UNIQUE INDEX IF NOT EXISTS marketing_page_wiki_articles_start_doc_idx
    ON marketing_page_wiki_articles(page_id, locale)
    WHERE is_start_document;
