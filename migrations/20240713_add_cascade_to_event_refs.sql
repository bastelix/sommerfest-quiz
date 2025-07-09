ALTER TABLE catalogs DROP CONSTRAINT IF EXISTS catalogs_event_uid_fkey;
ALTER TABLE catalogs ADD CONSTRAINT catalogs_event_uid_fkey FOREIGN KEY(event_uid) REFERENCES events(uid) ON DELETE CASCADE;

ALTER TABLE teams DROP CONSTRAINT IF EXISTS teams_event_uid_fkey;
ALTER TABLE teams ADD CONSTRAINT teams_event_uid_fkey FOREIGN KEY(event_uid) REFERENCES events(uid) ON DELETE CASCADE;

ALTER TABLE results DROP CONSTRAINT IF EXISTS results_event_uid_fkey;
ALTER TABLE results ADD CONSTRAINT results_event_uid_fkey FOREIGN KEY(event_uid) REFERENCES events(uid) ON DELETE CASCADE;

ALTER TABLE question_results DROP CONSTRAINT IF EXISTS question_results_event_uid_fkey;
ALTER TABLE question_results ADD CONSTRAINT question_results_event_uid_fkey FOREIGN KEY(event_uid) REFERENCES events(uid) ON DELETE CASCADE;

ALTER TABLE config DROP CONSTRAINT IF EXISTS config_event_uid_fkey;
ALTER TABLE config ADD CONSTRAINT config_event_uid_fkey FOREIGN KEY(event_uid) REFERENCES events(uid) ON DELETE CASCADE;
