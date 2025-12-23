-- Create table for AI page generation jobs.
CREATE TABLE IF NOT EXISTS page_ai_jobs (
    id BIGSERIAL PRIMARY KEY,
    job_id TEXT NOT NULL,
    namespace TEXT NOT NULL,
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    theme TEXT NOT NULL,
    color_scheme TEXT NOT NULL,
    problem TEXT NOT NULL,
    prompt_template TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    html TEXT,
    error_code TEXT,
    error_message TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS page_ai_jobs_job_id_idx ON page_ai_jobs(job_id);
CREATE INDEX IF NOT EXISTS page_ai_jobs_status_idx ON page_ai_jobs(status);
