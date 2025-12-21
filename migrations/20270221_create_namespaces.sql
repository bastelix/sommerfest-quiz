CREATE TABLE IF NOT EXISTS namespaces (
    namespace TEXT PRIMARY KEY,
    label TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE FUNCTION trg_namespaces_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_namespaces_updated_at ON namespaces;
CREATE TRIGGER trg_namespaces_updated_at
    BEFORE UPDATE ON namespaces
    FOR EACH ROW
    EXECUTE FUNCTION trg_namespaces_updated_at();

INSERT INTO namespaces (namespace, is_active)
SELECT DISTINCT namespace, TRUE
FROM (
    SELECT namespace FROM namespace_profile
    UNION
    SELECT namespace FROM user_namespaces
    UNION
    SELECT 'default' AS namespace
) AS entries
ON CONFLICT (namespace) DO NOTHING;
