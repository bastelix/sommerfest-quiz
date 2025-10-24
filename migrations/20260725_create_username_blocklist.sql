-- Create username blocklist table for moderation categories
CREATE TABLE IF NOT EXISTS username_blocklist (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    term TEXT NOT NULL,
    category TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT username_blocklist_category_check CHECK (
        category IN ('NSFW', '§86a/NS-Bezug', 'Beleidigung/Slur', 'Allgemein')
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_username_blocklist_term_category
    ON username_blocklist (LOWER(term), category);

CREATE INDEX IF NOT EXISTS idx_username_blocklist_category
    ON username_blocklist (category);
