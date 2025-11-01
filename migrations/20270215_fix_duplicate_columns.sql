DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'tenants'
          AND column_name = 'onboarding_state'
    ) THEN
        ALTER TABLE public.tenants
            ADD COLUMN onboarding_state TEXT NOT NULL DEFAULT 'pending';
    END IF;
END
$$;

UPDATE public.tenants
SET onboarding_state = COALESCE(onboarding_state, 'pending');

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'question_results'
          AND column_name = 'time_left_sec'
    ) THEN
        ALTER TABLE public.question_results
            ADD COLUMN time_left_sec INTEGER DEFAULT 0;
    END IF;
END
$$;
