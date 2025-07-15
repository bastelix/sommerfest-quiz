ALTER TABLE public.catalogs DROP CONSTRAINT IF EXISTS catalogs_unique_sort_order;
ALTER TABLE public.catalogs ADD CONSTRAINT catalogs_unique_sort_order UNIQUE(event_uid, sort_order) DEFERRABLE INITIALLY DEFERRED;
