-- Per-namespace Stripe API keys (optional, falls back to global env vars)
ALTER TABLE namespace_projects ADD COLUMN stripe_secret_key VARCHAR(255);
ALTER TABLE namespace_projects ADD COLUMN stripe_publishable_key VARCHAR(255);
