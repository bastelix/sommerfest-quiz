-- Add a sortable order column for page tree rendering.
ALTER TABLE pages
    ADD COLUMN IF NOT EXISTS sort_order INTEGER NOT NULL DEFAULT 0;

UPDATE pages
   SET sort_order = id
 WHERE sort_order = 0;
