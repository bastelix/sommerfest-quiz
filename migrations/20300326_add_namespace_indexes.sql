-- Add dedicated indexes on namespace columns for list queries.
-- Composite unique indexes (namespace, slug) exist but don't help
-- when filtering by namespace alone.

CREATE INDEX IF NOT EXISTS idx_pages_namespace ON pages (namespace);
CREATE INDEX IF NOT EXISTS idx_events_namespace ON events (namespace);
CREATE INDEX IF NOT EXISTS idx_tickets_namespace ON tickets (namespace);
CREATE INDEX IF NOT EXISTS idx_marketing_footer_blocks_namespace ON marketing_footer_blocks (namespace);
CREATE INDEX IF NOT EXISTS idx_marketing_menus_namespace ON marketing_menus (namespace);
CREATE INDEX IF NOT EXISTS idx_marketing_menu_assignments_namespace ON marketing_menu_assignments (namespace);
CREATE INDEX IF NOT EXISTS idx_marketing_page_wiki_articles_page_id ON marketing_page_wiki_articles (page_id);
