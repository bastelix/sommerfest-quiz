-- Ensure scoring columns exist on legacy installations and rehydrate missing final_points values.
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS time_left_sec INTEGER;
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS final_points INTEGER NOT NULL DEFAULT 0;
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS efficiency DOUBLE PRECISION NOT NULL DEFAULT 0;
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS is_correct BOOLEAN;
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS scoring_version INTEGER NOT NULL DEFAULT 1;

UPDATE question_results
SET final_points = points
WHERE points IS NOT NULL
  AND (final_points IS NULL OR final_points = 0)
  AND points <> COALESCE(final_points, 0);

UPDATE question_results
SET efficiency = CASE WHEN correct = 1 THEN 1.0 ELSE 0.0 END
WHERE efficiency IS NULL;

UPDATE question_results
SET is_correct = CASE WHEN correct = 1 THEN TRUE ELSE FALSE END
WHERE is_correct IS NULL;

UPDATE question_results
SET scoring_version = 1
WHERE scoring_version IS NULL OR scoring_version < 1;
