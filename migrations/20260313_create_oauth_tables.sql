-- OAuth 2.0 tables for MCP server authentication

CREATE TABLE IF NOT EXISTS oauth_clients (
    id TEXT PRIMARY KEY,
    secret_hash TEXT NOT NULL,
    name TEXT NOT NULL DEFAULT '',
    redirect_uris JSONB NOT NULL DEFAULT '[]'::jsonb,
    scope TEXT NOT NULL DEFAULT '',
    namespace TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
    code TEXT PRIMARY KEY,
    client_id TEXT NOT NULL REFERENCES oauth_clients(id) ON DELETE CASCADE,
    namespace TEXT NOT NULL,
    scopes JSONB NOT NULL DEFAULT '[]'::jsonb,
    redirect_uri TEXT NOT NULL,
    code_challenge TEXT,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS oauth_access_tokens (
    id BIGSERIAL PRIMARY KEY,
    token_hash TEXT NOT NULL,
    client_id TEXT NOT NULL REFERENCES oauth_clients(id) ON DELETE CASCADE,
    namespace TEXT NOT NULL,
    scopes JSONB NOT NULL DEFAULT '[]'::jsonb,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
