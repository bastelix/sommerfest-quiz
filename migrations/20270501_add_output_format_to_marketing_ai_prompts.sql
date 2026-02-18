-- Add output_format column to distinguish block-contract from HTML templates.
ALTER TABLE marketing_ai_prompts ADD COLUMN IF NOT EXISTS output_format TEXT NOT NULL DEFAULT 'html';

-- Backfill existing block-contract templates based on their id suffix.
UPDATE marketing_ai_prompts
SET output_format = 'block-contract', updated_at = CURRENT_TIMESTAMP
WHERE id LIKE '%-block-contract' AND output_format = 'html';
