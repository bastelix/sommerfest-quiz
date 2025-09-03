-- Add QR eye style configuration field
ALTER TABLE config ADD COLUMN IF NOT EXISTS qrEyeStyle TEXT;
