-- Normalize QRRemember legacy column to qrremember and backfill existing values.
DO $$
DECLARE
    current_schema_name text := current_schema();
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema_name
          AND table_name = 'config'
          AND column_name = 'qrremember'
    ) THEN
        EXECUTE format(
            'ALTER TABLE %I.config ADD COLUMN qrremember BOOLEAN DEFAULT FALSE;',
            current_schema_name
        );
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema_name
          AND table_name = 'config'
          AND column_name = 'QRRemember'
    ) THEN
        EXECUTE format(
            'UPDATE %I.config SET qrremember = COALESCE("QRRemember", qrremember);',
            current_schema_name
        );
        EXECUTE format(
            'ALTER TABLE %I.config DROP COLUMN "QRRemember";',
            current_schema_name
        );
    END IF;
END
$$;
