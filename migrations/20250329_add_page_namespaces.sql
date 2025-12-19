ALTER TABLE pages
    ADD COLUMN namespace TEXT NOT NULL DEFAULT 'default',
    ADD COLUMN type TEXT,
    ADD COLUMN parent_id INTEGER REFERENCES pages(id) ON DELETE SET NULL,
    ADD COLUMN status TEXT,
    ADD COLUMN language TEXT,
    ADD COLUMN content_source TEXT;

ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_slug_key;
DROP INDEX IF EXISTS idx_pages_slug_unique;

CREATE UNIQUE INDEX IF NOT EXISTS idx_pages_namespace_slug_unique ON pages(namespace, slug);
