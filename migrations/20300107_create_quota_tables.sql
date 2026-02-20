-- Namespace-Projects: Zentrale Organisationseinheit für Billing/Quotas
-- Separat von der bestehenden namespaces-Tabelle (Content-Namespaces mit TEXT PK)
CREATE TABLE IF NOT EXISTS namespace_projects (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug            VARCHAR(63) UNIQUE NOT NULL,
    owner_user_id   INT NOT NULL,
    display_name    VARCHAR(255) NOT NULL,
    plan            VARCHAR(20) NOT NULL DEFAULT 'free',
    stripe_sub_id   VARCHAR(255),
    status          VARCHAR(20) DEFAULT 'active',
    design_config   JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ DEFAULT now(),
    updated_at      TIMESTAMPTZ DEFAULT now()
);

-- Namespace-Project-Users: Welche User in welchem Projekt
CREATE TABLE IF NOT EXISTS namespace_project_users (
    namespace_id    UUID NOT NULL REFERENCES namespace_projects(id) ON DELETE CASCADE,
    user_id         INT NOT NULL,
    role            VARCHAR(30) NOT NULL DEFAULT 'editor',
    invited_at      TIMESTAMPTZ DEFAULT now(),
    PRIMARY KEY (namespace_id, user_id)
);

-- Quota-Usage: Live-Zähler pro Namespace
CREATE TABLE IF NOT EXISTS namespace_quota_usage (
    namespace_id    UUID NOT NULL REFERENCES namespace_projects(id) ON DELETE CASCADE,
    metric          VARCHAR(50) NOT NULL,
    current_value   INT NOT NULL DEFAULT 0,
    last_updated    TIMESTAMPTZ DEFAULT now(),
    PRIMARY KEY (namespace_id, metric)
);

-- Plan-Limits: Konfigurierbare Limits pro Plan
CREATE TABLE IF NOT EXISTS plan_limits (
    plan            VARCHAR(20) NOT NULL,
    metric          VARCHAR(50) NOT NULL,
    max_value       INT NOT NULL,
    PRIMARY KEY (plan, metric)
);

-- Index: Schnelle Quota-Prüfung
CREATE INDEX IF NOT EXISTS idx_quota_ns_metric
    ON namespace_quota_usage(namespace_id, metric);

-- Seed: Plan-Limits einfügen
INSERT INTO plan_limits (plan, metric, max_value) VALUES
    -- Free
    ('free', 'events', 1),
    ('free', 'teams', 3),
    ('free', 'catalogs', 2),
    ('free', 'questions', 10),
    ('free', 'pages', 1),
    ('free', 'wiki_entries', 2),
    ('free', 'news_articles', 1),
    ('free', 'chatbots', 0),
    ('free', 'custom_domains', 0),
    ('free', 'namespace_users', 1),
    ('free', 'storage_mb', 50),
    ('free', 'ai_generations_month', 0),
    -- Starter
    ('starter', 'events', 3),
    ('starter', 'teams', 30),
    ('starter', 'catalogs', 15),
    ('starter', 'questions', 100),
    ('starter', 'pages', 5),
    ('starter', 'wiki_entries', 10),
    ('starter', 'news_articles', 5),
    ('starter', 'chatbots', 1),
    ('starter', 'custom_domains', 0),
    ('starter', 'namespace_users', 2),
    ('starter', 'storage_mb', 500),
    ('starter', 'ai_generations_month', 0),
    -- Standard
    ('standard', 'events', 50),
    ('standard', 'teams', 200),
    ('standard', 'catalogs', 50),
    ('standard', 'questions', 500),
    ('standard', 'pages', 100),
    ('standard', 'wiki_entries', 100),
    ('standard', 'news_articles', 100),
    ('standard', 'chatbots', 10),
    ('standard', 'custom_domains', 3),
    ('standard', 'namespace_users', 10),
    ('standard', 'storage_mb', 5000),
    ('standard', 'ai_generations_month', 100)
ON CONFLICT (plan, metric) DO UPDATE SET max_value = EXCLUDED.max_value;
