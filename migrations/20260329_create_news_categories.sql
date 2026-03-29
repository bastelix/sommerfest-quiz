-- News categories (namespace-scoped)
CREATE TABLE IF NOT EXISTS news_categories (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    namespace TEXT NOT NULL,
    slug TEXT NOT NULL,
    name TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS news_categories_ns_slug_idx
    ON news_categories(namespace, slug);

CREATE INDEX IF NOT EXISTS news_categories_ns_sort_idx
    ON news_categories(namespace, sort_order, name);

-- Pivot table: landing_news ↔ news_categories
CREATE TABLE IF NOT EXISTS news_article_category (
    article_id INTEGER NOT NULL REFERENCES landing_news(id) ON DELETE CASCADE,
    category_id INTEGER NOT NULL REFERENCES news_categories(id) ON DELETE CASCADE,
    PRIMARY KEY (article_id, category_id)
);

CREATE INDEX IF NOT EXISTS news_article_category_cat_idx
    ON news_article_category(category_id);
