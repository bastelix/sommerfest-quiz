-- Persistent cache for AI generated team name suggestions.
CREATE TABLE IF NOT EXISTS team_name_ai_cache (
    id BIGSERIAL PRIMARY KEY,
    event_id TEXT NOT NULL,
    cache_key TEXT NOT NULL,
    name TEXT NOT NULL,
    filters JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_team_name_ai_cache_event FOREIGN KEY (event_id)
        REFERENCES events(uid) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_team_name_ai_cache_event_key_name
    ON team_name_ai_cache(event_id, cache_key, name);

CREATE INDEX IF NOT EXISTS ix_team_name_ai_cache_event
    ON team_name_ai_cache(event_id);
