-- Add Google Sign-In subject identifier to users
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id TEXT UNIQUE;
