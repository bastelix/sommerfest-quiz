DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'questions' AND column_name = 'catalog_id'
    ) THEN
        ALTER TABLE public.questions DROP CONSTRAINT IF EXISTS questions_catalog_id_fkey;
        ALTER TABLE public.questions ADD CONSTRAINT questions_catalog_id_fkey
            FOREIGN KEY (catalog_id) REFERENCES public.catalogs(id) ON DELETE CASCADE;
    END IF;
END$$;
