CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user'
);

INSERT INTO users(username, password, role)
SELECT adminUser, adminPass, 'admin'
FROM config
WHERE adminUser IS NOT NULL AND adminPass IS NOT NULL;

ALTER TABLE config DROP COLUMN IF EXISTS adminUser;
ALTER TABLE config DROP COLUMN IF EXISTS adminPass;
