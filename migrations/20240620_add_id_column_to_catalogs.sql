ALTER TABLE catalogs ADD COLUMN IF NOT EXISTS id TEXT;
UPDATE catalogs SET id = regexp_replace(file, '\\.json$', '') WHERE id IS NULL OR id = '';
ALTER TABLE catalogs ALTER COLUMN id SET NOT NULL;
ALTER TABLE catalogs ADD CONSTRAINT catalogs_id_unique UNIQUE(id);
