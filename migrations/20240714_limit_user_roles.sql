ALTER TABLE users ALTER COLUMN role SET DEFAULT 'catalog-editor';
UPDATE users SET role='catalog-editor' WHERE role NOT IN ('admin','catalog-editor','event-manager','analyst','team-manager');
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','catalog-editor','event-manager','analyst','team-manager'));
