-- Create the namespaces table early so that subsequent migrations
-- (e.g. 20260217_create_calserver_cms_page) can insert into it.
-- The later migration 20270221_create_namespaces uses IF NOT EXISTS and
-- will skip table creation, then seed the data.

CREATE TABLE IF NOT EXISTS namespaces (
    namespace TEXT PRIMARY KEY,
    label TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
