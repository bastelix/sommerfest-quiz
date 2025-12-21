-- Create namespace-specific imprint profiles for legal pages.
CREATE TABLE IF NOT EXISTS namespace_profile (
    namespace TEXT PRIMARY KEY,
    imprint_name TEXT,
    imprint_street TEXT,
    imprint_zip TEXT,
    imprint_city TEXT,
    imprint_email TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO namespace_profile (
    namespace,
    imprint_name,
    imprint_street,
    imprint_zip,
    imprint_city,
    imprint_email
)
SELECT
    'default',
    imprint_name,
    imprint_street,
    imprint_zip,
    imprint_city,
    imprint_email
FROM tenants
WHERE subdomain = 'main'
ON CONFLICT (namespace) DO NOTHING;
