ALTER TABLE public.config ADD COLUMN IF NOT EXISTS "QRRemember" BOOLEAN DEFAULT FALSE;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'config' AND column_name = 'qrremember'
    ) THEN
        EXECUTE 'UPDATE public.config SET "QRRemember" = COALESCE("QRRemember", qrremember)';
    END IF;
END$$;

ALTER TABLE public.config DROP COLUMN IF EXISTS qrremember;
