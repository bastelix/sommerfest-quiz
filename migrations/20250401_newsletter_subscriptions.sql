-- Track marketing newsletter subscriptions and consent metadata.
CREATE TABLE IF NOT EXISTS newsletter_subscriptions (
    email TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    consent_requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    consent_confirmed_at TIMESTAMP NULL,
    unsubscribe_at TIMESTAMP NULL,
    consent_metadata TEXT NULL,
    attributes TEXT NULL,
    unsubscribe_metadata TEXT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_newsletter_subscriptions_email
    ON newsletter_subscriptions(email);
CREATE INDEX IF NOT EXISTS idx_newsletter_subscriptions_status
    ON newsletter_subscriptions(status);
