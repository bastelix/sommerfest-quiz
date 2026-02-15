-- Add customer role to user_role enum
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_enum e JOIN pg_type t ON e.enumtypid = t.oid
      WHERE t.typname = 'user_role' AND e.enumlabel = 'customer'
    ) THEN
      ALTER TYPE user_role ADD VALUE 'customer';
    END IF;
  END IF;
END$$;
