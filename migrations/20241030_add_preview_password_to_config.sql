-- Add preview password hash for event configurations
ALTER TABLE config
    ADD COLUMN IF NOT EXISTS preview_password_hash TEXT;
