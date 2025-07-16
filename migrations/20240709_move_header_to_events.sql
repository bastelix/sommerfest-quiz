
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'config'
          AND column_name = 'header'
    ) THEN
        INSERT INTO public.events(uid,name,description)
        SELECT '1', header, subheader FROM public.config LIMIT 1;
    END IF;
END;
$$;

ALTER TABLE public.config DROP COLUMN IF EXISTS header;
ALTER TABLE public.config DROP COLUMN IF EXISTS subheader;
