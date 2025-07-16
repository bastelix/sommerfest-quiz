ALTER TABLE public.config ADD COLUMN IF NOT EXISTS qrremember BOOLEAN DEFAULT FALSE;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'config' AND column_name = 'QRRemember'
    ) THEN
        EXECUTE 'UPDATE public.config SET qrremember = COALESCE(qrremember, "QRRemember")';
    END IF;
END$$;

ALTER TABLE public.config DROP COLUMN IF EXISTS "QRRemember";
