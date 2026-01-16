ALTER TABLE config
    ADD COLUMN IF NOT EXISTS results_view_mode TEXT DEFAULT 'split';
