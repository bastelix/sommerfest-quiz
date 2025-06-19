ALTER TABLE catalogs ADD COLUMN IF NOT EXISTS slug TEXT;
UPDATE catalogs SET slug = id WHERE slug IS NULL OR slug = '';
ALTER TABLE catalogs ALTER COLUMN slug SET NOT NULL;
ALTER TABLE catalogs ADD CONSTRAINT catalogs_slug_unique UNIQUE(slug);
