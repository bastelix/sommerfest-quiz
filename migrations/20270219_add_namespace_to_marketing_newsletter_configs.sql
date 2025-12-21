-- Add namespace support to marketing newsletter CTA configurations
ALTER TABLE marketing_newsletter_configs
    ADD COLUMN namespace TEXT NOT NULL DEFAULT 'default';

DROP INDEX IF EXISTS idx_marketing_newsletter_configs_unique;
DROP INDEX IF EXISTS idx_marketing_newsletter_configs_slug;

CREATE UNIQUE INDEX IF NOT EXISTS idx_marketing_newsletter_configs_unique
    ON marketing_newsletter_configs(namespace, slug, position);

CREATE INDEX IF NOT EXISTS idx_marketing_newsletter_configs_slug
    ON marketing_newsletter_configs(namespace, slug);
