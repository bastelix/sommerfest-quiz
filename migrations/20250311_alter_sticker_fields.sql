-- Refine sticker configuration field types
ALTER TABLE config
  ALTER COLUMN stickerqrsizepct TYPE NUMERIC(6,2)
    USING REPLACE(stickerqrsizepct::text, ',', '.')::numeric,
  ALTER COLUMN stickerdesctop TYPE NUMERIC(6,2)
    USING REPLACE(stickerdesctop::text, ',', '.')::numeric,
  ALTER COLUMN stickerdescleft TYPE NUMERIC(6,2)
    USING REPLACE(stickerdescleft::text, ',', '.')::numeric,
  ALTER COLUMN stickerdescwidth TYPE NUMERIC(6,2)
    USING REPLACE(stickerdescwidth::text, ',', '.')::numeric,
  ALTER COLUMN stickerdescheight TYPE NUMERIC(6,2)
    USING REPLACE(stickerdescheight::text, ',', '.')::numeric,
  ALTER COLUMN stickerqrtop TYPE NUMERIC(6,2)
    USING REPLACE(stickerqrtop::text, ',', '.')::numeric,
  ALTER COLUMN stickerqrleft TYPE NUMERIC(6,2)
    USING REPLACE(stickerqrleft::text, ',', '.')::numeric;
