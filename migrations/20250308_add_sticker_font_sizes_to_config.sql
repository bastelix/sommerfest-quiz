-- Add sticker font size fields to config table
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerHeaderFontSize INTEGER;
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerSubheaderFontSize INTEGER;
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerCatalogFontSize INTEGER;
ALTER TABLE config ADD COLUMN IF NOT EXISTS stickerDescFontSize INTEGER;
