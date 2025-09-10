-- Change stickerQrSizePct to REAL to allow decimal percentages
ALTER TABLE config ALTER COLUMN stickerQrSizePct TYPE REAL USING stickerQrSizePct::REAL;
