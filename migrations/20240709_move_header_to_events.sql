INSERT INTO events(uid,name,description)
SELECT '1', header, subheader FROM config LIMIT 1;
ALTER TABLE config DROP COLUMN IF EXISTS header;
ALTER TABLE config DROP COLUMN IF EXISTS subheader;
