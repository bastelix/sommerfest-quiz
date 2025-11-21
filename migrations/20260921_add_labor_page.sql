-- Create initial entry for the Labor landing page.
INSERT INTO pages (slug, title, content)
VALUES (
    'labor',
    'Kalibrierlabor – DAkkS-akkreditiert',
    '<!-- Placeholder content for the Labor landing page -->'
)
ON CONFLICT (slug) DO NOTHING;
