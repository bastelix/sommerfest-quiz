UPDATE pages
SET content = REPLACE(
        content,
        'SSO (z. B. EntraID/Google) für nahtlosen Zugriff',
        'SSO (EntraID/Active Directory) für nahtlosen Zugriff'
    ),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver';
