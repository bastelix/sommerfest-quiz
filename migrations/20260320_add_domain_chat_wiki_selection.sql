-- Allow domains to include marketing wiki articles in the AI index
CREATE TABLE IF NOT EXISTS domain_chat_wiki_articles (
    domain TEXT NOT NULL,
    article_id INTEGER NOT NULL REFERENCES marketing_page_wiki_articles(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(domain, article_id)
);

CREATE INDEX IF NOT EXISTS domain_chat_wiki_articles_domain_idx
    ON domain_chat_wiki_articles(domain);
