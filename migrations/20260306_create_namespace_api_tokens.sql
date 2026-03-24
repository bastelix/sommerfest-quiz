-- Namespace API tokens for public CMS API.

CREATE TABLE IF NOT EXISTS namespace_api_tokens (
    id BIGSERIAL PRIMARY KEY,
    namespace TEXT NOT NULL,
    label TEXT NOT NULL DEFAULT '',
    token_hash TEXT NOT NULL,
    scopes JSONB NOT NULL DEFAULT '[]'::jsonb,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    last_used_ip TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_namespace_api_tokens_namespace ON namespace_api_tokens(namespace);
CREATE INDEX IF NOT EXISTS idx_namespace_api_tokens_revoked_at ON namespace_api_tokens(revoked_at);
