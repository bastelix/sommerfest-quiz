DO $$
DECLARE
    current_schema_name text := current_schema();
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema_name
          AND table_name = 'tenants'
          AND column_name = 'onboarding_state'
    ) THEN
        EXECUTE format(
            'ALTER TABLE %I.tenants ADD COLUMN onboarding_state TEXT NOT NULL DEFAULT ''pending'';',
            current_schema_name
        );
    END IF;

    EXECUTE format(
        'UPDATE %I.tenants SET onboarding_state = COALESCE(onboarding_state, ''pending'');',
        current_schema_name
    );
END
$$;

DO $$
DECLARE
    current_schema_name text := current_schema();
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema_name
          AND table_name = 'question_results'
          AND column_name = 'time_left_sec'
    ) THEN
        EXECUTE format(
            'ALTER TABLE %I.question_results ADD COLUMN time_left_sec INTEGER DEFAULT 0;',
            current_schema_name
        );
    END IF;
END
$$;
