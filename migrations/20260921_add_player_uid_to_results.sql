-- Store optional player references for aggregated and per-question results
ALTER TABLE results ADD COLUMN player_uid TEXT;
ALTER TABLE question_results ADD COLUMN player_uid TEXT;

CREATE INDEX IF NOT EXISTS idx_results_event_player_uid ON results(event_uid, player_uid);
CREATE INDEX IF NOT EXISTS idx_question_results_event_player_uid ON question_results(event_uid, player_uid);
