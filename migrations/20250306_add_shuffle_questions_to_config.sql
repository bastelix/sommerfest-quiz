ALTER TABLE config ADD COLUMN IF NOT EXISTS shuffleQuestions BOOLEAN DEFAULT TRUE;
UPDATE config SET shuffleQuestions = TRUE WHERE shuffleQuestions IS NULL;

