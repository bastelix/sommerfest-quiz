-- Migrate legacy page-based menus into menu definitions and assignments.

CREATE TEMP TABLE tmp_marketing_menu_map (
    page_id INTEGER NOT NULL,
    namespace TEXT NOT NULL,
    locale TEXT NOT NULL,
    menu_id INTEGER NOT NULL,
    PRIMARY KEY (page_id, namespace, locale)
);

INSERT INTO tmp_marketing_menu_map (page_id, namespace, locale, menu_id)
SELECT
    legacy.page_id,
    legacy.namespace,
    legacy.locale,
    nextval('marketing_menus_id_seq')
FROM (
    SELECT DISTINCT page_id, namespace, locale
    FROM marketing_page_menu_items
) AS legacy;

INSERT INTO marketing_menus (id, namespace, label, locale, is_active)
OVERRIDING SYSTEM VALUE
SELECT
    map.menu_id,
    map.namespace,
    'Navigation – ' || pages.title,
    map.locale,
    TRUE
FROM tmp_marketing_menu_map AS map
JOIN pages ON pages.id = map.page_id;

INSERT INTO marketing_menu_assignments (menu_id, page_id, namespace, slot, locale, is_active)
SELECT
    map.menu_id,
    map.page_id,
    map.namespace,
    'main',
    map.locale,
    TRUE
FROM tmp_marketing_menu_map AS map
ON CONFLICT DO NOTHING;

WITH menu_item_offset AS (
    SELECT COALESCE(MAX(id), 0) AS value
    FROM marketing_menu_items
)
INSERT INTO marketing_menu_items (
    id,
    menu_id,
    parent_id,
    namespace,
    label,
    href,
    icon,
    position,
    is_external,
    locale,
    is_active,
    layout,
    detail_title,
    detail_text,
    detail_subline,
    is_startpage
)
OVERRIDING SYSTEM VALUE
SELECT
    legacy.id + menu_item_offset.value,
    map.menu_id,
    CASE
        WHEN legacy.parent_id IS NULL THEN NULL
        ELSE legacy.parent_id + menu_item_offset.value
    END,
    legacy.namespace,
    legacy.label,
    legacy.href,
    legacy.icon,
    legacy.position,
    legacy.is_external,
    legacy.locale,
    legacy.is_active,
    legacy.layout,
    legacy.detail_title,
    legacy.detail_text,
    legacy.detail_subline,
    legacy.is_startpage
FROM marketing_page_menu_items AS legacy
JOIN tmp_marketing_menu_map AS map
    ON map.page_id = legacy.page_id
    AND map.namespace = legacy.namespace
    AND map.locale = legacy.locale
CROSS JOIN menu_item_offset;

SELECT setval(
    'marketing_menu_items_id_seq',
    GREATEST((SELECT COALESCE(MAX(id), 0) FROM marketing_menu_items), 1),
    TRUE
);

INSERT INTO marketing_menu_assignments (menu_id, page_id, namespace, slot, locale, is_active)
SELECT
    map.menu_id,
    NULL,
    map.namespace,
    slots.slot,
    map.locale,
    TRUE
FROM tmp_marketing_menu_map AS map
JOIN pages ON pages.id = map.page_id AND pages.is_startpage = TRUE
CROSS JOIN (VALUES ('footer_1'), ('footer_2'), ('footer_3')) AS slots(slot)
ON CONFLICT DO NOTHING;
