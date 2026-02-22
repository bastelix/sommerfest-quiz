-- Stripe-Felder für namespace_projects: Jeder Namespace kann ein eigenes Abo haben
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255);
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(255);
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS stripe_status VARCHAR(50);
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS stripe_current_period_end TIMESTAMPTZ;
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS stripe_cancel_at_period_end BOOLEAN DEFAULT FALSE;

-- Index für Stripe-Customer-Lookup
CREATE INDEX IF NOT EXISTS idx_namespace_projects_stripe_customer
    ON namespace_projects(stripe_customer_id)
    WHERE stripe_customer_id IS NOT NULL;
