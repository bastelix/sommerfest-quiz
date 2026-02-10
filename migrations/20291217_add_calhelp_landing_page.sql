INSERT INTO pages (namespace, slug, title, content, content_source)
VALUES (
    'calhelp',
    'calhelp-landing',
    'calHelp – Praxiswissen für die Kalibrierbranche | René Buske',
    '<!-- calHelp Landing page rendered via Twig template -->',
    'marketing/calhelp-landing.twig'
)
ON CONFLICT (namespace, slug) DO NOTHING;
