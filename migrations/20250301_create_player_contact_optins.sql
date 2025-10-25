-- Track pending player contact confirmations for double opt-in.
CREATE TABLE IF NOT EXISTS player_contact_optins (
    id BIGSERIAL PRIMARY KEY,
    event_uid TEXT NOT NULL,
    player_uid TEXT NOT NULL,
    player_name TEXT NOT NULL,
    email TEXT NOT NULL,
    token_hash TEXT NOT NULL,
    request_ip TEXT NULL,
    confirmation_ip TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    consumed_at TIMESTAMPTZ NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_player_contact_optins_token
    ON player_contact_optins(token_hash);
CREATE INDEX IF NOT EXISTS idx_player_contact_optins_lookup
    ON player_contact_optins(event_uid, player_uid);
