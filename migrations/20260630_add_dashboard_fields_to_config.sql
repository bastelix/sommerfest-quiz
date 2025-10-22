-- Add dashboard configuration fields
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboardEnabled BOOLEAN;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboardShareToken TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboardModules TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboardInfo TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboardMediaUrl TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboardRefreshInterval INTEGER;
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboardRankingLimit INTEGER;
