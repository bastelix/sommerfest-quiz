-- Add sort_order column to events for manual ordering
ALTER TABLE events ADD COLUMN IF NOT EXISTS sort_order INTEGER NOT NULL DEFAULT 0;
