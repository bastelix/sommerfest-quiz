SET NOCOUNT ON;

INSERT INTO public.events(uid,name,description)
SELECT '1', header, subheader FROM public.config LIMIT 1;
ALTER TABLE public.config DROP COLUMN IF EXISTS header;
ALTER TABLE public.config DROP COLUMN IF EXISTS subheader;
