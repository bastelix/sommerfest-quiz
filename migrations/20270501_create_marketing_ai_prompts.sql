-- Create table for marketing AI prompt templates.
CREATE TABLE IF NOT EXISTS marketing_ai_prompts (
    id TEXT PRIMARY KEY,
    label TEXT NOT NULL,
    template TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
