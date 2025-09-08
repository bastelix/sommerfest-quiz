-- Add position column to users for manual ordering
ALTER TABLE users ADD COLUMN IF NOT EXISTS position INTEGER NOT NULL DEFAULT 0;
UPDATE users SET position = id WHERE position = 0;
