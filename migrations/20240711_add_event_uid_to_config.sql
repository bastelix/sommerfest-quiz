ALTER TABLE config ADD COLUMN IF NOT EXISTS event_uid TEXT REFERENCES events(uid);
UPDATE config SET event_uid = activeEventUid WHERE event_uid IS NULL;
ALTER TABLE config DROP COLUMN IF EXISTS activeEventUid;
