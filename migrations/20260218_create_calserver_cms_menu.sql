-- Create CMS menu for the calserver namespace page.
-- Requires the page from 20260217_create_calserver_cms_page.sql.

-- 1. Create the menu definition
INSERT INTO marketing_menus (namespace, label, locale, is_active)
SELECT 'calserver', 'Navigation – calServer', 'de', TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM marketing_menus WHERE namespace = 'calserver' AND locale = 'de'
);

-- 2. Assign the menu to the calserver page (slot: main)
INSERT INTO marketing_menu_assignments (menu_id, page_id, namespace, slot, locale, is_active)
SELECT
    m.id,
    p.id,
    'calserver',
    'main',
    'de',
    TRUE
FROM marketing_menus m
JOIN pages p ON p.namespace = 'calserver' AND p.slug = 'calserver'
WHERE m.namespace = 'calserver' AND m.locale = 'de'
ON CONFLICT DO NOTHING;

-- 3. Seed menu items based on the page block anchors (German)
-- Uses a CTE to resolve the menu id once.
WITH cs_menu AS (
    SELECT m.id AS menu_id
    FROM marketing_menus m
    WHERE m.namespace = 'calserver' AND m.locale = 'de'
    LIMIT 1
)
INSERT INTO marketing_menu_items (menu_id, namespace, label, href, icon, position, is_external, locale, is_active, layout, is_startpage)
SELECT menu_id, 'calserver', v.label, v.href, v.icon, v.pos, FALSE, 'de', TRUE, 'link', v.is_start
FROM cs_menu,
(VALUES
    ('Funktionen',  '#funktionen', 'settings',    0, TRUE),
    ('Referenzen',  '#referenzen', 'users',       1, FALSE),
    ('Stimmen',     '#stimmen',    'comment',      2, FALSE),
    ('Preise',      '#preise',     'credit-card',  3, FALSE),
    ('Kontakt',     '#kontakt',    'mail',         4, FALSE)
) AS v(label, href, icon, pos, is_start)
WHERE NOT EXISTS (
    SELECT 1 FROM marketing_menu_items WHERE menu_id = cs_menu.menu_id AND namespace = 'calserver' AND locale = 'de'
);

-- 4. Repeat for English locale
INSERT INTO marketing_menus (namespace, label, locale, is_active)
SELECT 'calserver', 'Navigation – calServer', 'en', TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM marketing_menus WHERE namespace = 'calserver' AND locale = 'en'
);

INSERT INTO marketing_menu_assignments (menu_id, page_id, namespace, slot, locale, is_active)
SELECT
    m.id,
    p.id,
    'calserver',
    'main',
    'en',
    TRUE
FROM marketing_menus m
JOIN pages p ON p.namespace = 'calserver' AND p.slug = 'calserver'
WHERE m.namespace = 'calserver' AND m.locale = 'en'
ON CONFLICT DO NOTHING;

WITH cs_menu_en AS (
    SELECT m.id AS menu_id
    FROM marketing_menus m
    WHERE m.namespace = 'calserver' AND m.locale = 'en'
    LIMIT 1
)
INSERT INTO marketing_menu_items (menu_id, namespace, label, href, icon, position, is_external, locale, is_active, layout, is_startpage)
SELECT menu_id, 'calserver', v.label, v.href, v.icon, v.pos, FALSE, 'en', TRUE, 'link', v.is_start
FROM cs_menu_en,
(VALUES
    ('Features',       '#funktionen', 'settings',    0, TRUE),
    ('References',     '#referenzen', 'users',       1, FALSE),
    ('Testimonials',   '#stimmen',    'comment',      2, FALSE),
    ('Pricing',        '#preise',     'credit-card',  3, FALSE),
    ('Contact',        '#kontakt',    'mail',         4, FALSE)
) AS v(label, href, icon, pos, is_start)
WHERE NOT EXISTS (
    SELECT 1 FROM marketing_menu_items WHERE menu_id = cs_menu_en.menu_id AND namespace = 'calserver' AND locale = 'en'
);
