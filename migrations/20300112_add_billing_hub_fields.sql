-- Extend namespace_projects for multi-product Stripe billing hub
-- Each namespace can represent a product (eforms, quizrace, calserver, edocs)
-- with its own Stripe Pricing Table and webhook forwarding URL.

ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS product VARCHAR(50);
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS stripe_pricing_table_id VARCHAR(255);
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS webhook_url VARCHAR(500);

COMMENT ON COLUMN namespace_projects.product IS 'Product identifier (eforms, quizrace, calserver, edocs)';
COMMENT ON COLUMN namespace_projects.stripe_pricing_table_id IS 'Stripe Pricing Table ID for embedded checkout';
COMMENT ON COLUMN namespace_projects.webhook_url IS 'URL to notify when subscription events occur for this namespace';
