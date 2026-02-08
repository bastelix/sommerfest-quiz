-- Replace the global UNIQUE constraint on events.slug with a composite
-- UNIQUE constraint on (namespace, slug) so that different namespaces
-- can reuse the same slug independently.

ALTER TABLE events DROP CONSTRAINT IF EXISTS events_slug_key;

CREATE UNIQUE INDEX IF NOT EXISTS idx_events_namespace_slug_unique
    ON events(namespace, slug);
