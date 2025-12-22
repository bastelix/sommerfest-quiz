CREATE TABLE IF NOT EXISTS page_ai_jobs (
    id TEXT PRIMARY KEY,
    namespace TEXT NOT NULL DEFAULT 'default',
    slug TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    payload TEXT NOT NULL,
    result_html TEXT,
    error_code TEXT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_page_ai_jobs_namespace_slug ON page_ai_jobs(namespace, slug);
CREATE INDEX IF NOT EXISTS idx_page_ai_jobs_status ON page_ai_jobs(status);
