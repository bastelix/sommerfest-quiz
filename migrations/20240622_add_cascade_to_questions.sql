ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_catalog_id_fkey;
ALTER TABLE questions ADD CONSTRAINT questions_catalog_id_fkey
    FOREIGN KEY (catalog_id) REFERENCES catalogs(id) ON DELETE CASCADE;
