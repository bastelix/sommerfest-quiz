ALTER TABLE public.catalogs DROP CONSTRAINT IF EXISTS catalogs_event_uid_fkey;
ALTER TABLE public.catalogs ADD CONSTRAINT catalogs_event_uid_fkey FOREIGN KEY(event_uid) REFERENCES public.events(uid) ON DELETE CASCADE;

ALTER TABLE public.teams DROP CONSTRAINT IF EXISTS teams_event_uid_fkey;
ALTER TABLE public.teams ADD CONSTRAINT teams_event_uid_fkey FOREIGN KEY(event_uid) REFERENCES public.events(uid) ON DELETE CASCADE;

ALTER TABLE public.results DROP CONSTRAINT IF EXISTS results_event_uid_fkey;
ALTER TABLE public.results ADD CONSTRAINT results_event_uid_fkey FOREIGN KEY(event_uid) REFERENCES public.events(uid) ON DELETE CASCADE;

ALTER TABLE public.question_results DROP CONSTRAINT IF EXISTS question_results_event_uid_fkey;
ALTER TABLE public.question_results ADD CONSTRAINT question_results_event_uid_fkey FOREIGN KEY(event_uid) REFERENCES public.events(uid) ON DELETE CASCADE;

ALTER TABLE public.config DROP CONSTRAINT IF EXISTS config_event_uid_fkey;
ALTER TABLE public.config ADD CONSTRAINT config_event_uid_fkey FOREIGN KEY(event_uid) REFERENCES public.events(uid) ON DELETE CASCADE;
