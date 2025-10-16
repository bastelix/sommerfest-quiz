-- Ensure the FLUKE MET/CAL landing page points to the dedicated screenshot placeholder
UPDATE page_seo_config
   SET og_image = '/uploads/fluke-metcal-placeholder.webp',
       updated_at = CURRENT_TIMESTAMP
 WHERE slug = 'fluke-metcal'
   AND og_image IS DISTINCT FROM '/uploads/fluke-metcal-placeholder.webp';
