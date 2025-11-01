DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'tenants'
          AND column_name = 'onboarding_state'
    ) THEN
        ALTER TABLE tenants
            ADD COLUMN onboarding_state TEXT NOT NULL DEFAULT 'pending';
    END IF;
END
$$;

UPDATE tenants
SET onboarding_state = COALESCE(onboarding_state, 'completed');
