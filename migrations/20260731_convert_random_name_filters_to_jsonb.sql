-- Convert random name filter columns to JSONB for structured storage
ALTER TABLE config ADD COLUMN IF NOT EXISTS random_name_domains JSONB DEFAULT '[]'::jsonb;
ALTER TABLE config ADD COLUMN IF NOT EXISTS random_name_tones JSONB DEFAULT '[]'::jsonb;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'config'
          AND column_name = 'random_name_domains'
          AND udt_name <> 'jsonb'
    ) THEN
        ALTER TABLE config ALTER COLUMN random_name_domains TYPE JSONB
            USING CASE
                WHEN random_name_domains IS NULL THEN '[]'::jsonb
                WHEN trim(BOTH FROM random_name_domains::text) = '' THEN '[]'::jsonb
                ELSE random_name_domains::jsonb
            END;
    END IF;
END $$;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'config'
          AND column_name = 'random_name_tones'
          AND udt_name <> 'jsonb'
    ) THEN
        ALTER TABLE config ALTER COLUMN random_name_tones TYPE JSONB
            USING CASE
                WHEN random_name_tones IS NULL THEN '[]'::jsonb
                WHEN trim(BOTH FROM random_name_tones::text) = '' THEN '[]'::jsonb
                ELSE random_name_tones::jsonb
            END;
    END IF;
END $$;

UPDATE config SET random_name_domains = '[]'::jsonb WHERE random_name_domains IS NULL;
UPDATE config SET random_name_tones = '[]'::jsonb WHERE random_name_tones IS NULL;

ALTER TABLE config ALTER COLUMN random_name_domains SET DEFAULT '[]'::jsonb;
ALTER TABLE config ALTER COLUMN random_name_tones SET DEFAULT '[]'::jsonb;
ALTER TABLE config ALTER COLUMN random_name_domains SET NOT NULL;
ALTER TABLE config ALTER COLUMN random_name_tones SET NOT NULL;
