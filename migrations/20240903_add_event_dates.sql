ALTER TABLE public.events ADD COLUMN IF NOT EXISTS start_date TEXT DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.events ADD COLUMN IF NOT EXISTS end_date TEXT DEFAULT CURRENT_TIMESTAMP;
UPDATE public.events SET start_date = COALESCE(start_date, date), end_date = COALESCE(end_date, date) WHERE date IS NOT NULL;
ALTER TABLE public.events DROP COLUMN IF EXISTS date;
