INSERT INTO pages (slug, title, content)
VALUES (
    'calserver',
    'calServer',
    '<!-- Placeholder content for calServer marketing page -->'
)
ON CONFLICT (slug) DO NOTHING;
