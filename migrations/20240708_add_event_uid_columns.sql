ALTER TABLE public.catalogs ADD COLUMN IF NOT EXISTS event_uid TEXT REFERENCES public.events(uid) ON DELETE CASCADE;
ALTER TABLE public.teams ADD COLUMN IF NOT EXISTS event_uid TEXT REFERENCES public.events(uid) ON DELETE CASCADE;
ALTER TABLE public.results ADD COLUMN IF NOT EXISTS event_uid TEXT REFERENCES public.events(uid) ON DELETE CASCADE;
ALTER TABLE public.question_results ADD COLUMN IF NOT EXISTS event_uid TEXT REFERENCES public.events(uid) ON DELETE CASCADE;
