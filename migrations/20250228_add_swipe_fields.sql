ALTER TABLE questions
    ADD COLUMN cards JSONB DEFAULT '[]'::JSONB,
    ADD COLUMN right_label TEXT,
    ADD COLUMN left_label TEXT;
