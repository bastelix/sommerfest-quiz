-- Allow configuration entries for namespaces that are not backed by events.
ALTER TABLE config DROP CONSTRAINT IF EXISTS fk_config_event;
