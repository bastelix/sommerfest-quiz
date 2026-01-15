ALTER TABLE events
    ADD COLUMN IF NOT EXISTS namespace TEXT NOT NULL DEFAULT 'default';

UPDATE events
SET namespace = 'default'
WHERE namespace IS NULL OR namespace = '';
