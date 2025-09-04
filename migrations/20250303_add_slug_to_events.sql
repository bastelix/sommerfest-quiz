ALTER TABLE events ADD COLUMN slug TEXT;
UPDATE events SET slug = uid WHERE slug IS NULL OR slug = '';
ALTER TABLE events ALTER COLUMN slug SET NOT NULL;
ALTER TABLE events ADD CONSTRAINT events_slug_key UNIQUE (slug);
