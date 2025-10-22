ALTER TABLE question_results ADD COLUMN time_left_sec INTEGER;
ALTER TABLE question_results ADD COLUMN final_points INTEGER NOT NULL DEFAULT 0;
ALTER TABLE question_results ADD COLUMN efficiency DOUBLE PRECISION NOT NULL DEFAULT 0;
ALTER TABLE question_results ADD COLUMN is_correct BOOLEAN;
ALTER TABLE question_results ADD COLUMN scoring_version INTEGER NOT NULL DEFAULT 1;

UPDATE question_results
SET
    time_left_sec = NULL,
    final_points = points,
    efficiency = CASE WHEN correct = 1 THEN 1.0 ELSE 0.0 END,
    is_correct = CASE WHEN correct = 1 THEN TRUE ELSE FALSE END,
    scoring_version = 1;
