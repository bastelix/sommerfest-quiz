ALTER TABLE public.config ADD COLUMN IF NOT EXISTS event_uid TEXT REFERENCES public.events(uid) ON DELETE CASCADE;
UPDATE public.config SET event_uid = activeEventUid WHERE event_uid IS NULL;
ALTER TABLE public.config DROP COLUMN IF EXISTS activeEventUid;
