-- Central accounts for cross-app identity (edocs.cloud as IdP)
CREATE TABLE IF NOT EXISTS accounts (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255),
    stripe_customer_id VARCHAR(255),
    status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (status IN ('active', 'suspended', 'deleted')),
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- OAuth identities (multiple providers per account)
CREATE TABLE IF NOT EXISTS oauth_identities (
    id SERIAL PRIMARY KEY,
    account_id INT NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    provider VARCHAR(50) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    provider_email VARCHAR(255),
    provider_data JSONB,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (provider, provider_user_id)
);

CREATE INDEX IF NOT EXISTS idx_oauth_identities_account_id ON oauth_identities(account_id);

-- App subscriptions (which account has which app booked)
CREATE TABLE IF NOT EXISTS app_subscriptions (
    id SERIAL PRIMARY KEY,
    account_id INT NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    app VARCHAR(50) NOT NULL,
    stripe_subscription_id VARCHAR(255),
    plan VARCHAR(50),
    status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (status IN ('active', 'past_due', 'canceled', 'trialing')),
    valid_until TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_app_subscriptions_account_id ON app_subscriptions(account_id);
CREATE INDEX IF NOT EXISTS idx_app_subscriptions_stripe_sub ON app_subscriptions(stripe_subscription_id);
