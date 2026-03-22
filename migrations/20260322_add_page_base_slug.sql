ALTER TABLE pages ADD COLUMN base_slug TEXT;

-- Link existing English page variants to their base slugs
UPDATE pages SET base_slug = 'calserver', language = 'en' WHERE slug = 'calserver-en';
UPDATE pages SET base_slug = 'calserver-maintenance', language = 'en' WHERE slug = 'calserver-maintenance-en';
UPDATE pages SET base_slug = 'calserver-accessibility', language = 'en' WHERE slug = 'calserver-accessibility-en';
