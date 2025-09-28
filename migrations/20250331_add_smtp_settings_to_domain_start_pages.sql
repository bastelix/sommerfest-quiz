ALTER TABLE domain_start_pages
    ADD COLUMN smtp_host TEXT,
    ADD COLUMN smtp_user TEXT,
    ADD COLUMN smtp_pass TEXT,
    ADD COLUMN smtp_port INTEGER,
    ADD COLUMN smtp_encryption TEXT,
    ADD COLUMN smtp_dsn TEXT;
