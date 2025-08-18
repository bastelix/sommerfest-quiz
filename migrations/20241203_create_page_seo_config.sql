CREATE TABLE IF NOT EXISTS page_seo_config (
    page_id INTEGER PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE,
    meta_title TEXT,
    meta_description TEXT,
    slug TEXT UNIQUE NOT NULL,
    canonical_url TEXT,
    robots_meta TEXT,
    og_title TEXT,
    og_description TEXT,
    og_image TEXT,
    schema_json JSONB,
    hreflang TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS page_seo_config_history (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    meta_title TEXT,
    meta_description TEXT,
    slug TEXT,
    canonical_url TEXT,
    robots_meta TEXT,
    og_title TEXT,
    og_description TEXT,
    og_image TEXT,
    schema_json JSONB,
    hreflang TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_page_seo_config_slug ON page_seo_config(slug);
