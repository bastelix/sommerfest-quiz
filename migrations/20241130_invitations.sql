CREATE TABLE IF NOT EXISTS invitations (
    email TEXT NOT NULL,
    token TEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_invitations_token ON invitations(token);
CREATE UNIQUE INDEX IF NOT EXISTS idx_invitations_email ON invitations(email);
