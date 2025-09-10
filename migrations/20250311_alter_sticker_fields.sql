-- Refine sticker QR size field type
ALTER TABLE config
  ALTER COLUMN "stickerQrSizePct" TYPE NUMERIC(6,2)
    USING REPLACE(CAST("stickerQrSizePct" AS TEXT), ',', '.')::NUMERIC;
