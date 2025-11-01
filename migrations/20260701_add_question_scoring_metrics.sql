ALTER TABLE question_results ADD COLUMN IF NOT EXISTS time_left_sec INTEGER;
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS final_points INTEGER NOT NULL DEFAULT 0;
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS efficiency DOUBLE PRECISION NOT NULL DEFAULT 0;
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS is_correct BOOLEAN;
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS scoring_version INTEGER NOT NULL DEFAULT 1;

UPDATE question_results
SET
    time_left_sec = NULL,
    final_points = points,
    efficiency = CASE WHEN correct = 1 THEN 1.0 ELSE 0.0 END,
    is_correct = CASE WHEN correct = 1 THEN TRUE ELSE FALSE END,
    scoring_version = 1;
