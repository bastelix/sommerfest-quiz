-- Add dedicated indexes on namespace columns for list queries.
-- Composite unique indexes (namespace, slug) exist but don't help
-- when filtering by namespace alone.

CREATE INDEX IF NOT EXISTS idx_pages_namespace ON pages (namespace);
CREATE INDEX IF NOT EXISTS idx_events_namespace ON events (namespace);
CREATE INDEX IF NOT EXISTS idx_landing_news_namespace ON landing_news (namespace);
CREATE INDEX IF NOT EXISTS idx_tickets_namespace ON tickets (namespace);
CREATE INDEX IF NOT EXISTS idx_cms_footer_blocks_namespace ON cms_footer_blocks (namespace);
CREATE INDEX IF NOT EXISTS idx_cms_menus_namespace ON cms_menus (namespace);
CREATE INDEX IF NOT EXISTS idx_cms_menu_assignments_namespace ON cms_menu_assignments (namespace);
CREATE INDEX IF NOT EXISTS idx_cms_page_wiki_articles_page_id ON cms_page_wiki_articles (page_id);
