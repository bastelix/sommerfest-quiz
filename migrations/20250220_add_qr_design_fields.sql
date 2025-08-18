-- Add QR design configuration fields
ALTER TABLE config ADD COLUMN IF NOT EXISTS qr_label_line1 TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS qr_label_line2 TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS qr_logo_path TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS qr_round_mode TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS qr_logo_punchout BOOLEAN;
