-- Add Lago billing fields to namespace_projects
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS lago_customer_id VARCHAR(255);
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS lago_subscription_id VARCHAR(255);
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS lago_plan_code VARCHAR(100);
ALTER TABLE namespace_projects ADD COLUMN IF NOT EXISTS lago_status VARCHAR(50);

CREATE INDEX IF NOT EXISTS idx_namespace_projects_lago_customer
    ON namespace_projects(lago_customer_id);
