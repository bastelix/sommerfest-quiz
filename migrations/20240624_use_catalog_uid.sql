ALTER TABLE questions ADD COLUMN IF NOT EXISTS catalog_uid TEXT;
UPDATE questions q
    SET catalog_uid = c.uid
    FROM catalogs c
    WHERE c.id = q.catalog_id OR c.slug = q.catalog_id;
ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_catalog_id_fkey;
ALTER TABLE questions ADD CONSTRAINT questions_catalog_uid_fkey
    FOREIGN KEY (catalog_uid) REFERENCES catalogs(uid) ON DELETE CASCADE;
DROP INDEX IF EXISTS idx_questions_catalog;
CREATE INDEX idx_questions_catalog ON questions(catalog_uid);
ALTER TABLE questions DROP COLUMN IF EXISTS catalog_id;
ALTER TABLE questions ALTER COLUMN catalog_uid SET NOT NULL;
