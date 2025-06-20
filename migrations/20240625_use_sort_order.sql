DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'catalogs' AND column_name = 'id'
    ) THEN
        ALTER TABLE catalogs RENAME COLUMN id TO sort_order;
    END IF;
END$$;

ALTER TABLE catalogs ALTER COLUMN sort_order TYPE INTEGER USING sort_order::integer;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'catalogs_sort_order_unique'
    ) THEN
        ALTER TABLE catalogs ADD CONSTRAINT catalogs_sort_order_unique UNIQUE(sort_order);
    END IF;
END$$;

ALTER TABLE questions ADD COLUMN IF NOT EXISTS sort_order INTEGER UNIQUE;
UPDATE questions SET sort_order = id WHERE sort_order IS NULL;
