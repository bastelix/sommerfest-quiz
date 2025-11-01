ALTER TABLE tenants ADD COLUMN onboarding_state TEXT NOT NULL DEFAULT 'pending';

UPDATE tenants
SET onboarding_state = 'completed';
