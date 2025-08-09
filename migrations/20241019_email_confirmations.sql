CREATE TABLE IF NOT EXISTS email_confirmations (
    email TEXT NOT NULL,
    token TEXT NOT NULL,
    confirmed INTEGER NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_email_confirmations_token ON email_confirmations(token);
CREATE UNIQUE INDEX IF NOT EXISTS idx_email_confirmations_email ON email_confirmations(email);
