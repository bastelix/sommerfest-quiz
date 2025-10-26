ALTER TABLE username_blocklist
    DROP CONSTRAINT IF EXISTS username_blocklist_category_check;

ALTER TABLE username_blocklist
    ADD CONSTRAINT username_blocklist_category_check
        CHECK (category IN ('NSFW', '§86a/NS-Bezug', 'Beleidigung/Slur', 'Allgemein', 'Admin'));
