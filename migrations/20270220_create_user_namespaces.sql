CREATE TABLE IF NOT EXISTS user_namespaces (
    user_id INTEGER NOT NULL,
    namespace TEXT NOT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, namespace),
    CONSTRAINT fk_user_namespaces_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_user_namespaces_user ON user_namespaces (user_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_user_namespaces_default
    ON user_namespaces (user_id)
    WHERE is_default;

INSERT INTO user_namespaces (user_id, namespace, is_default)
SELECT id, 'default', TRUE
FROM users
ON CONFLICT (user_id, namespace) DO NOTHING;
