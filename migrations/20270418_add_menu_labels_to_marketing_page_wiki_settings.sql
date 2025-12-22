ALTER TABLE marketing_page_wiki_settings
    ADD COLUMN IF NOT EXISTS menu_labels JSONB NOT NULL DEFAULT '{}'::jsonb;

UPDATE marketing_page_wiki_settings
SET menu_labels = jsonb_build_object('de', menu_label)
WHERE menu_label IS NOT NULL
  AND menu_label <> ''
  AND (menu_labels = '{}'::jsonb);
