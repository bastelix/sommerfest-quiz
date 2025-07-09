ALTER TABLE public.photo_consents ADD COLUMN IF NOT EXISTS event_uid TEXT REFERENCES public.events(uid) ON DELETE CASCADE;
-- Set the active event UID for existing rows if not already assigned
UPDATE public.photo_consents
SET event_uid = (SELECT event_uid FROM public.config LIMIT 1)
WHERE event_uid IS NULL;
