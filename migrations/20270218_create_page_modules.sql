-- Create table for page modules
CREATE TABLE IF NOT EXISTS page_modules (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    type TEXT NOT NULL,
    config JSONB,
    position TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS page_modules_page_position_idx
    ON page_modules(page_id, position, id);
