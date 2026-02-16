-- Add namespace column to team name tables for consistent namespace scoping.
--
-- Affected tables: team_names, team_name_ai_cache.
--
-- Rollback: ALTER TABLE team_names DROP COLUMN namespace;
--           ALTER TABLE team_name_ai_cache DROP COLUMN namespace;
-- Risk: Low – adds nullable column first, then backfills from events.namespace.

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
