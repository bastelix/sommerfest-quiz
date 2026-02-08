-- Add namespace column to event-related tables for consistent namespace scoping.
--
-- Affected tables: config, catalogs, results, question_results, teams, players,
-- photo_consents, summary_photos, active_event.
--
-- Rollback: ALTER TABLE <table> DROP COLUMN namespace;
-- Risk: Low – adds nullable column first, then backfills from events.namespace.

-- config
ALTER TABLE config ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE config SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = config.event_uid LIMIT 1
) WHERE namespace IS NULL AND event_uid IS NOT NULL;
UPDATE config SET namespace = 'default' WHERE namespace IS NULL;
CREATE INDEX IF NOT EXISTS idx_config_namespace ON config(namespace);

-- catalogs
ALTER TABLE catalogs ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE catalogs SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = catalogs.event_uid LIMIT 1
) WHERE namespace IS NULL AND event_uid IS NOT NULL;
UPDATE catalogs SET namespace = 'default' WHERE namespace IS NULL;
CREATE INDEX IF NOT EXISTS idx_catalogs_namespace ON catalogs(namespace);

-- results
ALTER TABLE results ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE results SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = results.event_uid LIMIT 1
) WHERE namespace IS NULL AND event_uid IS NOT NULL;
UPDATE results SET namespace = 'default' WHERE namespace IS NULL;
CREATE INDEX IF NOT EXISTS idx_results_namespace ON results(namespace);

-- question_results
ALTER TABLE question_results ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE question_results SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = question_results.event_uid LIMIT 1
) WHERE namespace IS NULL AND event_uid IS NOT NULL;
UPDATE question_results SET namespace = 'default' WHERE namespace IS NULL;
CREATE INDEX IF NOT EXISTS idx_qresults_namespace ON question_results(namespace);

-- teams
ALTER TABLE teams ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE teams SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = teams.event_uid LIMIT 1
) WHERE namespace IS NULL AND event_uid IS NOT NULL;
UPDATE teams SET namespace = 'default' WHERE namespace IS NULL;
CREATE INDEX IF NOT EXISTS idx_teams_namespace ON teams(namespace);

-- players
ALTER TABLE players ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE players SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = players.event_uid LIMIT 1
) WHERE namespace IS NULL AND event_uid IS NOT NULL;
UPDATE players SET namespace = 'default' WHERE namespace IS NULL;
CREATE INDEX IF NOT EXISTS idx_players_namespace ON players(namespace);

-- photo_consents
ALTER TABLE photo_consents ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE photo_consents SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = photo_consents.event_uid LIMIT 1
) WHERE namespace IS NULL AND event_uid IS NOT NULL;
UPDATE photo_consents SET namespace = 'default' WHERE namespace IS NULL;

-- summary_photos
ALTER TABLE summary_photos ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE summary_photos SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = summary_photos.event_uid LIMIT 1
) WHERE namespace IS NULL AND event_uid IS NOT NULL;
UPDATE summary_photos SET namespace = 'default' WHERE namespace IS NULL;

-- active_event
ALTER TABLE active_event ADD COLUMN IF NOT EXISTS namespace TEXT;
UPDATE active_event SET namespace = (
    SELECT e.namespace FROM events e WHERE e.uid = active_event.event_uid LIMIT 1
) WHERE namespace IS NULL AND event_uid IS NOT NULL;
UPDATE active_event SET namespace = 'default' WHERE namespace IS NULL;
