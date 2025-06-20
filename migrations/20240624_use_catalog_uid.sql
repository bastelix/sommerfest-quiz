ALTER TABLE questions ADD COLUMN IF NOT EXISTS catalog_uid TEXT;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'questions' AND column_name = 'catalog_id'
    ) THEN
        EXECUTE $upd$
            UPDATE questions q
            SET catalog_uid = c.uid
            FROM catalogs c
            WHERE c.id = q.catalog_id OR c.slug = q.catalog_id
        $upd$;
        ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_catalog_id_fkey;
        ALTER TABLE questions DROP COLUMN IF EXISTS catalog_id;
    END IF;
END$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'questions_catalog_uid_fkey'
    ) THEN
        ALTER TABLE questions ADD CONSTRAINT questions_catalog_uid_fkey
            FOREIGN KEY (catalog_uid) REFERENCES catalogs(uid) ON DELETE CASCADE;
    END IF;
END$$;

DROP INDEX IF EXISTS idx_questions_catalog;
CREATE INDEX IF NOT EXISTS idx_questions_catalog ON questions(catalog_uid);

ALTER TABLE questions ALTER COLUMN catalog_uid SET NOT NULL;
