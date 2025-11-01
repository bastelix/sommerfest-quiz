-- Ensure question points schema changes can run repeatedly without errors
ALTER TABLE questions ADD COLUMN IF NOT EXISTS points INTEGER NOT NULL DEFAULT 1;
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS points INTEGER NOT NULL DEFAULT 0;
ALTER TABLE results ADD COLUMN IF NOT EXISTS points INTEGER NOT NULL DEFAULT 0;
ALTER TABLE results ADD COLUMN IF NOT EXISTS max_points INTEGER NOT NULL DEFAULT 0;

UPDATE questions SET points = 1 WHERE points IS NULL;
UPDATE question_results SET points = 0 WHERE points IS NULL;
UPDATE results SET points = correct;
UPDATE results SET max_points = total;
