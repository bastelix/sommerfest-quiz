-- Persistent pool for curated team names with reservation metadata
CREATE TABLE IF NOT EXISTS team_names (
    id BIGSERIAL PRIMARY KEY,
    event_id TEXT NOT NULL,
    name TEXT NOT NULL,
    lexicon_version INTEGER NOT NULL DEFAULT 1,
    reservation_token TEXT NOT NULL,
    reserved_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_at TIMESTAMPTZ,
    released_at TIMESTAMPTZ,
    fallback BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_team_names_event FOREIGN KEY (event_id)
        REFERENCES events(uid) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_team_names_active
    ON team_names(event_id, name)
    WHERE released_at IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_team_names_token
    ON team_names(reservation_token);
