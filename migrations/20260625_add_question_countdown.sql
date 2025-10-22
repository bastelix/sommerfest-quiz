-- Add countdown configuration and question timer support
ALTER TABLE questions ADD COLUMN IF NOT EXISTS countdown INTEGER;
ALTER TABLE config ADD COLUMN IF NOT EXISTS countdownEnabled BOOLEAN;
ALTER TABLE config ADD COLUMN IF NOT EXISTS countdown INTEGER;
