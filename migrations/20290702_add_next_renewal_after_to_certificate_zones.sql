-- Add tracking for scheduled certificate renewal windows
ALTER TABLE certificate_zones ADD COLUMN IF NOT EXISTS next_renewal_after TIMESTAMPTZ;

UPDATE certificate_zones
SET next_renewal_after = COALESCE(
    next_renewal_after,
    CASE WHEN last_issued_at IS NOT NULL THEN last_issued_at + INTERVAL '60 days' ELSE NULL END
);
