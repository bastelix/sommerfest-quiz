ALTER TABLE tenants ADD COLUMN IF NOT EXISTS stripe_subscription_id TEXT;
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS stripe_price_id TEXT;
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS stripe_status TEXT;
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS stripe_current_period_end TIMESTAMP WITH TIME ZONE;
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS stripe_cancel_at_period_end BOOLEAN;
