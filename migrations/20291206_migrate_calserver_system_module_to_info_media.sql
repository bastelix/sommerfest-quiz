-- Migrate legacy system_module blocks to info_media switcher on the calServer page
UPDATE pages
SET content = REPLACE(
    REPLACE(content, '"type": "system_module"', '"type": "info_media"'),
    '"variant": "showcase"', '"variant": "switcher"'
  ),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver'
  AND content LIKE '%"type": "system_module"%';
