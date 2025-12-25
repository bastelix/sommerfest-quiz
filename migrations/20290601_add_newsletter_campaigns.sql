-- Create newsletter campaign tracking table
CREATE TABLE IF NOT EXISTS newsletter_campaigns (
    id SERIAL PRIMARY KEY,
    namespace VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    news_ids JSONB NOT NULL DEFAULT '[]'::jsonb,
    template_id VARCHAR(191),
    audience_id VARCHAR(191),
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    provider_campaign_id VARCHAR(191),
    provider_message_id VARCHAR(191),
    scheduled_for TIMESTAMPTZ,
    sent_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_newsletter_campaigns_namespace
    ON newsletter_campaigns(namespace);
