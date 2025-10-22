-- Add point tracking to questions and results
ALTER TABLE questions ADD COLUMN points INTEGER NOT NULL DEFAULT 1;
ALTER TABLE question_results ADD COLUMN points INTEGER NOT NULL DEFAULT 0;
ALTER TABLE results ADD COLUMN points INTEGER NOT NULL DEFAULT 0;
ALTER TABLE results ADD COLUMN max_points INTEGER NOT NULL DEFAULT 0;

UPDATE questions SET points = 1 WHERE points IS NULL;
UPDATE question_results SET points = 0 WHERE points IS NULL;
UPDATE results SET points = correct;
UPDATE results SET max_points = total;
