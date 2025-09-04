-- Set event_uid on existing catalogs based on active event or config
UPDATE catalogs
SET event_uid = (SELECT event_uid FROM active_event LIMIT 1)
WHERE event_uid IS NULL;

UPDATE catalogs
SET event_uid = (SELECT event_uid FROM config LIMIT 1)
WHERE event_uid IS NULL;
