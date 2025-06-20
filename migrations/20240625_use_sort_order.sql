ALTER TABLE catalogs RENAME COLUMN id TO sort_order;
ALTER TABLE catalogs ALTER COLUMN sort_order TYPE INTEGER USING sort_order::integer;
ALTER TABLE catalogs ADD CONSTRAINT catalogs_sort_order_unique UNIQUE(sort_order);
ALTER TABLE questions ADD COLUMN IF NOT EXISTS sort_order INTEGER UNIQUE;
UPDATE questions SET sort_order = id WHERE sort_order IS NULL;
