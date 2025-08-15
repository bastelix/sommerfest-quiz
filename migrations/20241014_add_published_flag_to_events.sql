DO $$
BEGIN
   IF NOT EXISTS (
      SELECT 1
      FROM information_schema.columns
      WHERE table_name='events'
        AND column_name='published'
   ) THEN
      ALTER TABLE events ADD COLUMN published BOOLEAN DEFAULT FALSE;
      UPDATE events SET published = TRUE;
   END IF;
END $$;

ALTER TABLE events ALTER COLUMN published SET DEFAULT FALSE;
UPDATE events SET published = TRUE WHERE published IS NULL;
