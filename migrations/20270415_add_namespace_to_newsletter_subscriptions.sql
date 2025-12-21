-- Add namespace support to newsletter subscriptions.
ALTER TABLE newsletter_subscriptions
    ADD COLUMN namespace TEXT NOT NULL DEFAULT 'default';

DROP INDEX IF EXISTS idx_newsletter_subscriptions_email;
DROP INDEX IF EXISTS idx_newsletter_subscriptions_status;

CREATE UNIQUE INDEX IF NOT EXISTS idx_newsletter_subscriptions_email
    ON newsletter_subscriptions(namespace, email);
CREATE INDEX IF NOT EXISTS idx_newsletter_subscriptions_status
    ON newsletter_subscriptions(namespace, status);
