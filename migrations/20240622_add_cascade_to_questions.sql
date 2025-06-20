DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'questions' AND column_name = 'catalog_id'
    ) THEN
        ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_catalog_id_fkey;
        ALTER TABLE questions ADD CONSTRAINT questions_catalog_id_fkey
            FOREIGN KEY (catalog_id) REFERENCES catalogs(id) ON DELETE CASCADE;
    END IF;
END$$;
