-- Track expected completion time and actual-to-expected duration ratio per attempt
ALTER TABLE results ADD COLUMN expected_duration_sec INTEGER;
ALTER TABLE results ADD COLUMN duration_ratio DOUBLE PRECISION;

WITH attempt_times AS (
    SELECT
        qr.name,
        qr.catalog,
        qr.attempt,
        qr.event_uid,
        COALESCE(SUM(CASE WHEN q.countdown IS NOT NULL AND q.countdown > 0 THEN q.countdown ELSE 0 END), 0) AS expected_duration,
        COALESCE(SUM(
            CASE
                WHEN q.countdown IS NULL OR q.countdown <= 0 THEN 0
                ELSE q.countdown - LEAST(GREATEST(COALESCE(qr.time_left_sec, 0), 0), q.countdown)
            END
        ), 0) AS used_duration
    FROM question_results qr
    JOIN questions q ON q.id = qr.question_id
    GROUP BY qr.name, qr.catalog, qr.attempt, qr.event_uid
)
UPDATE results r
SET
    expected_duration_sec = CASE WHEN attempt_times.expected_duration > 0 THEN attempt_times.expected_duration ELSE NULL END,
    duration_ratio = CASE
        WHEN attempt_times.expected_duration > 0 THEN (
            CASE
                WHEN r.duration_sec IS NOT NULL THEN r.duration_sec::DOUBLE PRECISION
                WHEN attempt_times.used_duration IS NOT NULL THEN attempt_times.used_duration::DOUBLE PRECISION
                ELSE NULL
            END
        ) / NULLIF(attempt_times.expected_duration::DOUBLE PRECISION, 0)
        ELSE NULL
    END
FROM attempt_times
WHERE r.name = attempt_times.name
    AND r.catalog = attempt_times.catalog
    AND r.attempt = attempt_times.attempt
    AND (
        (r.event_uid IS NULL AND attempt_times.event_uid IS NULL)
        OR r.event_uid = attempt_times.event_uid
    );

-- Clean up potential NaN results when both durations are missing
UPDATE results
SET duration_ratio = NULL
WHERE duration_ratio IS NOT NULL AND NOT (duration_ratio > -1e308 AND duration_ratio < 1e308);
