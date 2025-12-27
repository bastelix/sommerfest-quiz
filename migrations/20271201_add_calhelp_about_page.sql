INSERT INTO pages (namespace, slug, title, content, content_source)
VALUES (
    'calhelp',
    'ueber-mich',
    'Über mich – calHelp',
    '<!-- calHelp Über mich page content rendered via Twig template -->',
    'marketing/calhelp-ueber.twig'
)
ON CONFLICT (namespace, slug) DO NOTHING;
