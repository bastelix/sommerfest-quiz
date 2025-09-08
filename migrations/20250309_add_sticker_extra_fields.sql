-- Add missing sticker configuration fields
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerTextColor TEXT;
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerDescWidth REAL;
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerDescHeight REAL;
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerBgPath TEXT;
