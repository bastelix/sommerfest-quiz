SET NOCOUNT ON;

ALTER TABLE public.question_results ADD COLUMN IF NOT EXISTS answer_text TEXT;
ALTER TABLE public.question_results ADD COLUMN IF NOT EXISTS photo TEXT;
ALTER TABLE public.question_results ADD COLUMN IF NOT EXISTS consent BOOLEAN;
