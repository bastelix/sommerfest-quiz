DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE table_name = 'catalogs'
            AND constraint_name = 'catalogs_sort_order_unique'
    ) THEN
        ALTER TABLE public.catalogs DROP CONSTRAINT catalogs_sort_order_unique;
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE table_name = 'catalogs'
            AND constraint_name = 'catalogs_unique_sort_order'
    ) THEN
        ALTER TABLE public.catalogs DROP CONSTRAINT catalogs_unique_sort_order;
    END IF;
END$$;

ALTER TABLE public.catalogs
    ADD CONSTRAINT catalogs_unique_sort_order
    UNIQUE(sort_order) DEFERRABLE INITIALLY DEFERRED;
