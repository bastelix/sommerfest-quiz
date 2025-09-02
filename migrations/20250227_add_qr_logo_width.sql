-- Add configurable logo width for QR codes
ALTER TABLE config ADD COLUMN IF NOT EXISTS qrLogoWidth INTEGER;
