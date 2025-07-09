DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'questions_sort_order_key'
            AND table_name = 'questions'
    ) THEN
        ALTER TABLE public.questions DROP CONSTRAINT questions_sort_order_key;
    END IF;
END$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'questions_catalog_sort_order_unique'
    ) THEN
        ALTER TABLE public.questions
            ADD CONSTRAINT questions_catalog_sort_order_unique
            UNIQUE(catalog_uid, sort_order);
    END IF;
END$$;
