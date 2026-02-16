-- Add namespace column to team name tables for consistent namespace scoping.
--
-- Depends on:
--   20241028_create_team_names.sql          (team_names table)
--   20260916_create_team_name_ai_cache.sql  (team_name_ai_cache table)
--   20291211_add_namespace_to_events.sql    (events.namespace column)
--
-- Affected tables: team_names, team_name_ai_cache.
--
-- Rollback: ALTER TABLE team_names DROP COLUMN namespace;
--           ALTER TABLE team_name_ai_cache DROP COLUMN namespace;

-- team_names
ALTER TABLE team_names ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE team_names SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = team_names.event_id LIMIT 1
) WHERE namespace IS NULL AND event_id IS NOT NULL;
UPDATE team_names SET namespace = 'default' WHERE namespace IS NULL;
CREATE INDEX IF NOT EXISTS idx_team_names_namespace ON team_names(namespace);

-- team_name_ai_cache
ALTER TABLE team_name_ai_cache ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE team_name_ai_cache SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = team_name_ai_cache.event_id LIMIT 1
) WHERE namespace IS NULL AND event_id IS NOT NULL;
UPDATE team_name_ai_cache SET namespace = 'default' WHERE namespace IS NULL;
CREATE INDEX IF NOT EXISTS idx_team_name_ai_cache_namespace ON team_name_ai_cache(namespace);
