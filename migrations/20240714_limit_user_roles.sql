ALTER TABLE public.users ALTER COLUMN role SET DEFAULT 'catalog-editor';
UPDATE public.users SET role='catalog-editor' WHERE role NOT IN ('admin','catalog-editor','event-manager','analyst','team-manager');
ALTER TABLE public.users DROP CONSTRAINT IF EXISTS users_role_check;
ALTER TABLE public.users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','catalog-editor','event-manager','analyst','team-manager'));
