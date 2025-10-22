-- Add tracking fields for quiz attempt timing
ALTER TABLE results ADD COLUMN started_at INTEGER;
ALTER TABLE results ADD COLUMN duration_sec INTEGER;
