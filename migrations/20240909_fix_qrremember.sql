ALTER TABLE public.config ADD COLUMN IF NOT EXISTS "QRRemember" BOOLEAN DEFAULT FALSE;
UPDATE public.config SET "QRRemember" = COALESCE("QRRemember", qrremember);
ALTER TABLE public.config DROP COLUMN IF EXISTS qrremember;
