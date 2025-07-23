-- Add active column for users and registration setting
ALTER TABLE users ADD COLUMN IF NOT EXISTS active BOOLEAN NOT NULL DEFAULT TRUE;

INSERT INTO settings(key, value) VALUES('registration_enabled', '0')
ON CONFLICT (key) DO NOTHING;
