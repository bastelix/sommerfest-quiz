-- Link eforms English home page variant to its base slug
UPDATE pages SET language = 'en', base_slug = 'home'
WHERE slug = 'home-en' AND namespace = 'eforms';

-- Remove test page
DELETE FROM pages WHERE slug = 'seo-test' AND namespace = 'eforms';
