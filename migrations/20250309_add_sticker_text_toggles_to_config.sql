-- Add sticker text toggle fields to config table
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerPrintHeader BOOLEAN;
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerPrintSubheader BOOLEAN;
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerPrintCatalog BOOLEAN;
