CREATE TABLE IF NOT EXISTS question_results (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    catalog TEXT NOT NULL,
    question_id INTEGER NOT NULL,
    attempt INTEGER NOT NULL,
    correct INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_qresults_catalog ON public.question_results(catalog);
CREATE INDEX IF NOT EXISTS idx_qresults_name ON public.question_results(name);
CREATE INDEX IF NOT EXISTS idx_qresults_question ON public.question_results(question_id);
