-- Add governed design token storage and extend user roles
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_enum e JOIN pg_type t ON e.enumtypid = t.oid
      WHERE t.typname = 'user_role' AND e.enumlabel = 'designer'
    ) THEN
      ALTER TYPE user_role ADD VALUE 'designer';
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM pg_enum e JOIN pg_type t ON e.enumtypid = t.oid
      WHERE t.typname = 'user_role' AND e.enumlabel = 'redakteur'
    ) THEN
      ALTER TYPE user_role ADD VALUE 'redakteur';
    END IF;
  END IF;
END$$;

ALTER TABLE config ADD COLUMN IF NOT EXISTS design_tokens JSONB;
