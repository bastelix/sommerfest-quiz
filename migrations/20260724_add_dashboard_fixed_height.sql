-- Allow setting a fixed height for dashboard layouts
ALTER TABLE config ADD COLUMN IF NOT EXISTS dashboard_fixed_height TEXT;
