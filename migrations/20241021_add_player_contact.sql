-- Add contact details tracking for players
ALTER TABLE players
    ADD COLUMN contact_email TEXT NULL;

ALTER TABLE players
    ADD COLUMN consent_granted_at TIMESTAMPTZ NULL;
