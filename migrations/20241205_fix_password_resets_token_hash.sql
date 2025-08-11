-- Ensure password_resets table has token_hash column and unique index
ALTER TABLE IF EXISTS password_resets
    ADD COLUMN IF NOT EXISTS token_hash TEXT;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'password_resets'
          AND column_name = 'token'
    ) THEN
        UPDATE password_resets SET token_hash = token WHERE token_hash IS NULL;
        ALTER TABLE password_resets DROP COLUMN token;
    END IF;
END $$;

ALTER TABLE IF EXISTS password_resets
    ALTER COLUMN token_hash SET NOT NULL;

DROP INDEX IF EXISTS idx_password_resets_token;
CREATE UNIQUE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token_hash);
