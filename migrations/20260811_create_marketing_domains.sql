-- Create a dedicated table for storing marketing domains managed via the admin UI
CREATE TABLE IF NOT EXISTS marketing_domains (
    id SERIAL PRIMARY KEY,
    host TEXT NOT NULL,
    normalized_host TEXT NOT NULL UNIQUE,
    label TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE OR REPLACE FUNCTION set_marketing_domains_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_marketing_domains_updated_at ON marketing_domains;
CREATE TRIGGER trg_marketing_domains_updated_at
BEFORE UPDATE ON marketing_domains
FOR EACH ROW
EXECUTE FUNCTION set_marketing_domains_updated_at();

-- Import existing values from MARKETING_DOMAINS once so they remain configurable via the UI
DO $$
DECLARE
    raw_config TEXT := NULL;
    entry TEXT;
    display_host TEXT;
    normalized_host TEXT;
BEGIN
    BEGIN
        raw_config := current_setting('MARKETING_DOMAINS', true);
    EXCEPTION WHEN others THEN
        raw_config := NULL;
    END;

    IF raw_config IS NULL OR trim(raw_config) = '' THEN
        BEGIN
            raw_config := current_setting('app.marketing_domains', true);
        EXCEPTION WHEN others THEN
            raw_config := NULL;
        END;
    END IF;

    IF raw_config IS NULL OR trim(raw_config) = '' THEN
        RETURN;
    END IF;

    FOR entry IN
        SELECT DISTINCT trim(lower(value)) AS value
        FROM regexp_split_to_table(raw_config, '[\s,]+') AS value
        WHERE trim(value) <> ''
    LOOP
        display_host := regexp_replace(entry, '^[a-z0-9+.-]+://', '', 'i');
        display_host := regexp_replace(display_host, '^www\\.', '', 'i');
        display_host := regexp_replace(display_host, '[^a-z0-9\-.]', '', 'gi');
        display_host := trim(both '.' FROM display_host);

        normalized_host := regexp_replace(display_host, '^(admin|assistant)\\.', '', 'i');
        normalized_host := trim(both '.' FROM normalized_host);

        IF normalized_host = '' THEN
            CONTINUE;
        END IF;

        IF display_host = '' THEN
            display_host := normalized_host;
        END IF;

        INSERT INTO marketing_domains (host, normalized_host, label)
        VALUES (display_host, normalized_host, NULL)
        ON CONFLICT (normalized_host) DO NOTHING;
    END LOOP;
END;
$$;
