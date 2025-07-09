ALTER TABLE public.catalogs ADD COLUMN IF NOT EXISTS id TEXT;
UPDATE public.catalogs SET id = regexp_replace(file, '\\.json$', '') WHERE id IS NULL OR id = '';
ALTER TABLE public.catalogs ALTER COLUMN id SET NOT NULL;
ALTER TABLE public.catalogs ADD CONSTRAINT catalogs_id_unique UNIQUE(id);
