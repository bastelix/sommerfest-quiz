-- Ensure the question scoring columns exist without causing duplicate column failures
-- and backfill missing scoring metadata for schemas where the original migration
-- aborted before completion.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'question_results'
          AND column_name = 'time_left_sec'
    ) THEN
        ALTER TABLE question_results ADD COLUMN time_left_sec INTEGER;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'question_results'
          AND column_name = 'final_points'
    ) THEN
        ALTER TABLE question_results ADD COLUMN final_points INTEGER NOT NULL DEFAULT 0;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'question_results'
          AND column_name = 'efficiency'
    ) THEN
        ALTER TABLE question_results ADD COLUMN efficiency DOUBLE PRECISION NOT NULL DEFAULT 0;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'question_results'
          AND column_name = 'is_correct'
    ) THEN
        ALTER TABLE question_results ADD COLUMN is_correct BOOLEAN;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'question_results'
          AND column_name = 'scoring_version'
    ) THEN
        ALTER TABLE question_results ADD COLUMN scoring_version INTEGER NOT NULL DEFAULT 1;
    END IF;
END;
$$;

UPDATE question_results
SET
    time_left_sec = CASE
        WHEN scoring_version IS NULL THEN NULL
        ELSE time_left_sec
    END,
    final_points = CASE
        WHEN scoring_version IS NULL THEN points
        ELSE final_points
    END,
    efficiency = CASE
        WHEN scoring_version IS NULL THEN CASE WHEN correct = 1 THEN 1.0 ELSE 0.0 END
        ELSE efficiency
    END,
    is_correct = CASE
        WHEN scoring_version IS NULL THEN CASE WHEN correct = 1 THEN TRUE ELSE FALSE END
        ELSE is_correct
    END,
    scoring_version = COALESCE(scoring_version, 1)
WHERE scoring_version IS NULL;
